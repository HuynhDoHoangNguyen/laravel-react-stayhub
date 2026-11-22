<?php

namespace App\Services\Catalog;

use App\DTOs\Catalog\Requests\CreateHostRoomMaintenanceRequest;
use App\DTOs\Catalog\Responses\HostRoomMaintenanceResponse;
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
        $period = $this->validator->validatePeriod($input);
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

            if ($this->validator->hasBlockingBookingFacts($this->repository->lockRoomBookingFacts($room), $period, $today)) {
                throw new AppException('CATALOG_ROOM_BOOKING_CONFLICT', 'Khoảng ngày này xung đột với booking đang giữ phòng hoặc lưu trú.', 409);
            }
            if ($this->maintenanceRules->hasBlockingFacts($this->repository->lockRoomMaintenanceFacts($room), $period)) {
                throw new AppException('CATALOG_ROOM_MAINTENANCE_CONFLICT', 'Khoảng ngày này xung đột với lịch bảo trì của phòng.', 409);
            }

            $maintenance = $this->repository->createHostRoomMaintenance($room, $input);
            $response = $this->mapper->toHostRoomMaintenance($maintenance);
            DB::afterCommit(fn () => Log::info('Tạo lịch bảo trì phòng thành công.', [
                'room_id' => $room->getKey(),
                'maintenance_id' => $response->id,
            ]));

            return $response;
        });
    }
}
