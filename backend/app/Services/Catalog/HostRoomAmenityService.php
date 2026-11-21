<?php

namespace App\Services\Catalog;

use App\DTOs\Catalog\Requests\HostRoomAmenityTarget;
use App\DTOs\Catalog\Requests\HostRoomDetailQuery;
use App\DTOs\Catalog\Responses\HostRoomAmenitiesResponse;
use App\Mappers\Catalog\CatalogMapper;
use App\Models\Room;
use App\Models\User;
use App\Repositories\Catalog\CatalogRepository;
use App\Validators\Catalog\CatalogValidator;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class HostRoomAmenityService
{
    public function __construct(
        private readonly CatalogRepository $repository,
        private readonly CatalogMapper $mapper,
        private readonly CatalogValidator $validator,
    ) {}

    public function listing(User $actor, HostRoomDetailQuery $input): ?HostRoomAmenitiesResponse
    {
        $this->validator->authorizeHost($actor);
        if ($this->repository->findHostRoomPropertyId($actor->getKey(), $input->roomId) === null) {
            return null;
        }

        return $this->mapper->toHostRoomAmenities($this->repository->getHostRoomAmenityOptions($input->roomId));
    }

    public function assign(User $actor, HostRoomAmenityTarget $input): ?HostRoomAmenitiesResponse
    {
        return $this->changeForOwnedRoom($actor, $input->roomId, function (Room $room) use ($input): void {
            $amenity = $this->repository->lockAmenity($input->amenityId);
            if ($amenity === null || !$amenity->status) {
                throw ValidationException::withMessages([
                    'amenity_id' => ['Tiện nghi không tồn tại hoặc đã ngưng hoạt động.'],
                ]);
            }

            if ($this->repository->assignRoomAmenity($room, $amenity)) {
                DB::afterCommit(fn () => Log::info('Gán tiện nghi phòng thành công.', [
                    'room_id' => $room->id,
                    'amenity_id' => $amenity->id,
                ]));
            }

        });
    }

    public function remove(User $actor, HostRoomAmenityTarget $input): ?HostRoomAmenitiesResponse
    {
        return $this->changeForOwnedRoom($actor, $input->roomId, function (Room $room) use ($input): void {
            if ($this->repository->removeRoomAmenity($room, $input->amenityId)) {
                DB::afterCommit(fn () => Log::info('Gỡ tiện nghi phòng thành công.', [
                    'room_id' => $room->id,
                    'amenity_id' => $input->amenityId,
                ]));
            }
        });
    }

    /** @param Closure(Room): void $change */
    private function changeForOwnedRoom(User $actor, int $roomId, Closure $change): ?HostRoomAmenitiesResponse
    {
        $this->validator->authorizeHost($actor);
        $propertyId = $this->repository->findHostRoomPropertyId($actor->getKey(), $roomId);
        if ($propertyId === null) {
            return null;
        }

        // Both writes lock the room before any ordinary read creates a transaction snapshot.
        return DB::transaction(function () use ($actor, $roomId, $propertyId, $change): ?HostRoomAmenitiesResponse {
            $property = $this->repository->lockHostProperty($actor->getKey(), $propertyId);
            $room = $property === null ? null : $this->repository->lockPropertyRoom($property, $roomId);
            if ($room === null) {
                return null;
            }

            $change($room);

            return $this->mapper->toHostRoomAmenities($this->repository->getHostRoomAmenityOptions($roomId));
        });
    }
}
