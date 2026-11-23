<?php

namespace App\Services\Catalog;

use App\DTOs\Catalog\Requests\CreateHostRoomMaintenanceRequest;
use App\DTOs\Catalog\Requests\CheckRoomMaintenanceQuery;
use App\DTOs\Catalog\Requests\UpdateHostRoomMaintenanceRequest;
use App\DTOs\Catalog\Responses\HostRoomMaintenanceResponse;
use App\Enums\MaintenanceStatus;
use App\Exceptions\AppException;
use App\Mappers\Catalog\CatalogMapper;
use App\Models\User;
use App\Repositories\Catalog\CatalogRepository;
use App\Validators\Catalog\CatalogValidator;
use App\Validators\Catalog\HostRoomMaintenanceValidator;
use App\Validators\Catalog\RoomMaintenanceValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class HostRoomMaintenanceService
{
    public function __construct(
        private readonly CatalogRepository $repository,
        private readonly CatalogMapper $mapper,
        private readonly CatalogValidator $catalogValidator,
        private readonly HostRoomMaintenanceValidator $validator,
        private readonly RoomMaintenanceValidator $maintenanceRules,
    ) {}

    public function create(User $actor, CreateHostRoomMaintenanceRequest $input): ?HostRoomMaintenanceResponse
    {
        $this->catalogValidator->authorizeHost($actor);
        if ($input->roomId < 1) {
            return null;
        }
        $period = $this->validator->validatePeriod($input->roomId, $input->startDate, $input->endDate, $input->reason);
        $propertyId = $this->repository->findHostRoomPropertyId($actor->getKey(), $input->roomId);
        if ($propertyId === null) {
            return null;
        }

        return DB::transaction(function () use ($actor, $input, $period, $propertyId): ?HostRoomMaintenanceResponse {
            $property = $this->repository->lockHostProperty($actor->getKey(), $propertyId);
            $room = $property === null ? null : $this->repository->lockPropertyRoom($property, $input->roomId);
            if ($room === null) {
                return null;
            }

            $today = now(config('app.timezone'))->toDateString();
            $this->validator->validateStartDate($input->startDate, $today);

            $bookingFacts = $this->repository->lockRoomBookingFacts($room);
            $this->assertNoBookingConflict($bookingFacts, $period, $today);
            $this->assertNoMaintenanceConflict($this->repository->lockRoomMaintenanceFacts($room), $period);

            $maintenance = $this->repository->createHostRoomMaintenance($room, $input);
            $response = $this->mapper->toHostRoomMaintenance($maintenance);
            DB::afterCommit(fn () => Log::info('Tạo lịch bảo trì phòng thành công.', [
                'room_id' => $room->getKey(),
                'maintenance_id' => $response->id,
            ]));

            return $response;
        });
    }

    public function update(User $actor, UpdateHostRoomMaintenanceRequest $input): ?HostRoomMaintenanceResponse
    {
        $this->catalogValidator->authorizeHost($actor);
        $location = $this->repository->findHostMaintenanceLocation($actor->getKey(), $input->maintenanceId);
        if ($location === null) {
            return null;
        }
        $period = $this->validator->validatePeriod($location['room_id'], $input->startDate, $input->endDate, $input->reason);

        return DB::transaction(function () use ($actor, $input, $location, $period): ?HostRoomMaintenanceResponse {
            $property = $this->repository->lockHostProperty($actor->getKey(), $location['property_id']);
            $room = $property === null ? null : $this->repository->lockPropertyRoom($property, $location['room_id']);
            if ($room === null) {
                return null;
            }

            $today = now(config('app.timezone'))->toDateString();
            $bookingFacts = $this->repository->lockRoomBookingFacts($room);
            $facts = $this->repository->lockRoomMaintenanceFacts($room, $input->maintenanceId)
                ->keyBy('id');
            $target = $facts->pull($input->maintenanceId);
            if ($target === null) {
                return null;
            }
            if ($target->status !== MaintenanceStatus::SCHEDULED->value) {
                throw new AppException('CATALOG_ROOM_MAINTENANCE_STATUS_CONFLICT', 'Chỉ có thể sửa lịch bảo trì ở trạng thái Đã lên lịch.', 409);
            }

            $this->validator->validateStartDate($input->startDate, $today);
            $this->assertNoBookingConflict($bookingFacts, $period, $today);
            $this->assertNoMaintenanceConflict($facts, $period);
            $maintenance = $this->repository->lockRoomMaintenance($room, $input->maintenanceId);
            if ($maintenance === null) {
                return null;
            }
            if ($target->start_date === $input->startDate && $target->end_date === $input->endDate
                && $maintenance->reason === $input->reason) {
                return $this->mapper->toHostRoomMaintenance($maintenance);
            }

            $updated = $this->repository->updateHostRoomMaintenance($maintenance, $input);
            DB::afterCommit(fn () => Log::info('Cập nhật lịch bảo trì phòng thành công.', [
                'room_id' => $room->getKey(),
                'maintenance_id' => $input->maintenanceId,
            ]));

            return $this->mapper->toHostRoomMaintenance($updated);
        });
    }

    public function cancel(User $actor, int $maintenanceId): ?HostRoomMaintenanceResponse
    {
        return $this->closeSchedule($actor, $maintenanceId, MaintenanceStatus::SCHEDULED, MaintenanceStatus::CANCELLED,
            'Chỉ có thể hủy lịch bảo trì ở trạng thái Đã lên lịch.', 'Hủy lịch bảo trì phòng thành công.');
    }

    public function complete(User $actor, int $maintenanceId): ?HostRoomMaintenanceResponse
    {
        return $this->closeSchedule($actor, $maintenanceId, MaintenanceStatus::IN_PROGRESS, MaintenanceStatus::COMPLETED,
            'Chỉ có thể hoàn tất lịch bảo trì đang thực hiện.', 'Hoàn tất lịch bảo trì phòng thành công.');
    }

    public function start(User $actor, int $maintenanceId): ?HostRoomMaintenanceResponse
    {
        $this->catalogValidator->authorizeHost($actor);
        $location = $this->repository->findHostMaintenanceLocation($actor->getKey(), $maintenanceId);
        if ($location === null) {
            return null;
        }

        return DB::transaction(function () use ($actor, $maintenanceId, $location): ?HostRoomMaintenanceResponse {
            $property = $this->repository->lockHostProperty($actor->getKey(), $location['property_id']);
            $room = $property === null ? null : $this->repository->lockPropertyRoom($property, $location['room_id']);
            if ($room === null) {
                return null;
            }

            $today = now(config('app.timezone'))->toDateString();
            $bookings = $this->repository->lockRoomBookingFacts($room);
            $facts = $this->repository->lockRoomMaintenanceFacts($room, $maintenanceId)->keyBy('id');
            $target = $facts->pull($maintenanceId);
            if ($target === null) {
                return null;
            }

            $maintenance = $this->repository->lockRoomMaintenance($room, $maintenanceId);
            if ($maintenance === null) {
                return null;
            }
            if ($maintenance->status === MaintenanceStatus::IN_PROGRESS) {
                return $this->mapper->toHostRoomMaintenance($maintenance);
            }
            if ($maintenance->status !== MaintenanceStatus::SCHEDULED) {
                throw new AppException('CATALOG_ROOM_MAINTENANCE_STATUS_CONFLICT', 'Chỉ có thể bắt đầu lịch bảo trì ở trạng thái Đã lên lịch.', 409);
            }

            $period = $this->validator->validateStartWindow($room->getKey(), $target->start_date, $target->end_date, $today);
            if ($this->validator->hasBookingBlockingMaintenanceStart($bookings, $today)) {
                throw new AppException('CATALOG_ROOM_BOOKING_CONFLICT', 'Phòng đang có khách lưu trú hoặc có booking còn giữ phòng.', 409);
            }
            $this->assertNoMaintenanceConflict($facts, $period);

            $this->repository->changeHostRoomMaintenanceStatus($maintenance, MaintenanceStatus::IN_PROGRESS);
            DB::afterCommit(fn () => Log::info('Bắt đầu lịch bảo trì phòng thành công.', [
                'room_id' => $room->getKey(),
                'maintenance_id' => $maintenanceId,
            ]));

            return $this->mapper->toHostRoomMaintenance($maintenance);
        });
    }

    private function closeSchedule(
        User $actor,
        int $maintenanceId,
        MaintenanceStatus $source,
        MaintenanceStatus $destination,
        string $conflictMessage,
        string $successMessage,
    ): ?HostRoomMaintenanceResponse
    {
        $this->catalogValidator->authorizeHost($actor);
        $location = $this->repository->findHostMaintenanceLocation($actor->getKey(), $maintenanceId);
        if ($location === null) {
            return null;
        }

        return DB::transaction(function () use ($actor, $maintenanceId, $location, $source, $destination, $conflictMessage, $successMessage): ?HostRoomMaintenanceResponse {
            $property = $this->repository->lockHostProperty($actor->getKey(), $location['property_id']);
            $room = $property === null ? null : $this->repository->lockPropertyRoom($property, $location['room_id']);
            $maintenance = $room === null ? null : $this->repository->lockRoomMaintenance($room, $maintenanceId);
            if ($maintenance === null) {
                return null;
            }

            if ($maintenance->status === $destination) {
                return $this->mapper->toHostRoomMaintenance($maintenance);
            }
            if ($maintenance->status !== $source) {
                throw new AppException('CATALOG_ROOM_MAINTENANCE_STATUS_CONFLICT', $conflictMessage, 409);
            }

            $this->repository->changeHostRoomMaintenanceStatus($maintenance, $destination);
            DB::afterCommit(fn () => Log::info($successMessage, [
                'room_id' => $location['room_id'],
                'maintenance_id' => $maintenanceId,
            ]));

            return $this->mapper->toHostRoomMaintenance($maintenance);
        });
    }

    private function assertNoBookingConflict(iterable $facts, CheckRoomMaintenanceQuery $period, string $today): void
    {
        if ($this->validator->hasBlockingBookingFacts($facts, $period, $today)) {
            throw new AppException('CATALOG_ROOM_BOOKING_CONFLICT', 'Khoảng ngày này xung đột với booking đang giữ phòng hoặc lưu trú.', 409);
        }
    }

    private function assertNoMaintenanceConflict(iterable $facts, CheckRoomMaintenanceQuery $period): void
    {
        if ($this->maintenanceRules->hasBlockingFacts($facts, $period)) {
            throw new AppException('CATALOG_ROOM_MAINTENANCE_CONFLICT', 'Khoảng ngày này xung đột với lịch bảo trì của phòng.', 409);
        }
    }
}
