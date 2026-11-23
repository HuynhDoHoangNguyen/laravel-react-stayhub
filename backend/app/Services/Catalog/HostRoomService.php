<?php

namespace App\Services\Catalog;

use App\DTOs\Catalog\Requests\CreateHostRoomRequest;
use App\DTOs\Catalog\Requests\ChangeHostRoomStatusRequest;
use App\Enums\RoomStatus;
use App\DTOs\Catalog\Requests\UpdateHostRoomRequest;
use App\DTOs\Catalog\Responses\HostRoomResponse;
use App\Mappers\Catalog\CatalogMapper;
use App\Models\User;
use App\Repositories\Catalog\CatalogRepository;
use App\Validators\Catalog\CatalogValidator;
use App\Exceptions\AppException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class HostRoomService
{
    public function __construct(
        private readonly CatalogRepository $repository,
        private readonly CatalogMapper $mapper,
        private readonly CatalogValidator $validator,
    ) {}

    public function changeStatus(User $actor, ChangeHostRoomStatusRequest $input): ?HostRoomResponse
    {
        $this->validator->authorizeHost($actor);

        return DB::transaction(function () use ($actor, $input): ?HostRoomResponse {
            $propertyId = $this->repository->findHostRoomPropertyId($actor->getKey(), $input->roomId);
            $property = $propertyId === null ? null : $this->repository->lockHostProperty($actor->getKey(), $propertyId);
            $room = $property === null ? null : $this->repository->lockPropertyRoom($property, $input->roomId);
            if ($room === null) {
                return null;
            }

            $changed = $room->status !== $input->status;
            if ($changed) {
                $day = now(config('app.timezone'))->toDateString();
                if (in_array($input->status, [RoomStatus::INACTIVE, RoomStatus::MAINTENANCE], true)
                    && $this->validator->hostRoomHasBlockingBookings($this->repository->lockRoomBookingFacts($room), $day)) {
                    throw new AppException('CATALOG_ROOM_BOOKING_CONFLICT', 'Phòng còn booking đang giữ chỗ hoặc đang lưu trú.', 409);
                }
                if (($input->status === RoomStatus::ACTIVE
                    || ($room->status === RoomStatus::MAINTENANCE && $input->status === RoomStatus::INACTIVE))
                    && $this->validator->hostRoomHasBlockingMaintenances($this->repository->lockRoomMaintenanceFacts($room), $day)) {
                    throw new AppException('CATALOG_ROOM_MAINTENANCE_CONFLICT', 'Phòng còn lịch bảo trì đang có hiệu lực hoặc chưa hoàn tất.', 409);
                }
                $this->repository->changeHostRoomStatus($room, $input->status);
            }

            $room->setRelation('roomType', $this->repository->lockPropertyRoomType($property, $room->room_type_id));
            $response = $this->mapper->toHostRoom($room);
            if ($changed) {
                DB::afterCommit(fn () => Log::info('Cập nhật trạng thái phòng thành công.', [
                    'property_id' => $property->getKey(), 'room_id' => $response->id,
                ]));
            }

            return $response;
        });
    }

    public function update(User $actor, UpdateHostRoomRequest $input): ?HostRoomResponse
    {
        $this->validator->authorizeHost($actor);

        return DB::transaction(function () use ($actor, $input): ?HostRoomResponse {
            $propertyId = $this->repository->findHostRoomPropertyId($actor->getKey(), $input->roomId);
            $property = $propertyId === null ? null : $this->repository->lockHostProperty($actor->getKey(), $propertyId);
            $room = $property === null ? null : $this->repository->lockPropertyRoom($property, $input->roomId);
            if ($room === null) {
                return null;
            }
            $type = $this->repository->lockPropertyRoomType($property, $input->roomTypeId);
            if ($type === null) {
                throw ValidationException::withMessages([
                    'room_type_id' => ['Loại phòng không thuộc cơ sở này hoặc không còn tồn tại.'],
                ]);
            }
            if ($this->repository->hostRoomNumberExists($property, $input->roomNumber, $room->getKey())) {
                throw new AppException('CATALOG_ROOM_NUMBER_CONFLICT', 'Cơ sở đã có phòng cùng số.', 409);
            }

            $changed = $this->repository->updateHostRoom($room, $type, $input);
            $response = $this->mapper->toHostRoom($room);
            if ($changed) {
                DB::afterCommit(fn () => Log::info('Cập nhật phòng thành công.', [
                    'property_id' => $property->getKey(), 'room_id' => $response->id,
                ]));
            }

            return $response;
        });
    }

    public function create(User $actor, CreateHostRoomRequest $input): ?HostRoomResponse
    {
        $this->validator->authorizeHost($actor);

        return DB::transaction(function () use ($actor, $input): ?HostRoomResponse {
            $property = $this->repository->lockHostProperty($actor->getKey(), $input->propertyId);
            if ($property === null) {
                return null;
            }
            $type = $this->repository->lockPropertyRoomType($property, $input->roomTypeId);
            if ($type === null) {
                throw ValidationException::withMessages([
                    'room_type_id' => ['Loại phòng không thuộc cơ sở này hoặc không còn tồn tại.'],
                ]);
            }
            if ($this->repository->hostRoomNumberExists($property, $input->roomNumber)) {
                throw new AppException('CATALOG_ROOM_NUMBER_CONFLICT', 'Cơ sở đã có phòng cùng số.', 409);
            }

            $room = $this->repository->createHostRoom($property, $type, $input);
            $response = $this->mapper->toHostRoom($room);
            DB::afterCommit(fn () => Log::info('Thêm phòng thành công.', [
                'property_id' => $property->getKey(), 'room_id' => $response->id,
            ]));

            return $response;
        });
    }
}
