<?php

namespace App\Services\Catalog;

use App\DTOs\Catalog\Requests\CreateHostRoomTypeRequest;
use App\DTOs\Catalog\Requests\DeleteHostRoomTypeRequest;
use App\DTOs\Catalog\Requests\UpdateHostRoomTypeRequest;
use App\DTOs\Catalog\Responses\HostRoomTypeResponse;
use App\DTOs\Catalog\Responses\DeleteHostRoomTypeResponse;
use App\Mappers\Catalog\CatalogMapper;
use App\Models\User;
use App\Repositories\Catalog\CatalogRepository;
use App\Validators\Catalog\CatalogValidator;
use App\Exceptions\AppException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class HostRoomTypeService
{
    public function __construct(
        private readonly CatalogRepository $repository,
        private readonly CatalogMapper $mapper,
        private readonly CatalogValidator $validator,
    ) {}

    public function delete(User $actor, DeleteHostRoomTypeRequest $input): ?DeleteHostRoomTypeResponse
    {
        $this->validator->authorizeHost($actor);

        return DB::transaction(function () use ($actor, $input): ?DeleteHostRoomTypeResponse {
            $propertyId = $this->repository->findHostRoomTypePropertyId($actor->getKey(), $input->roomTypeId);
            $property = $propertyId === null ? null : $this->repository->lockHostProperty($actor->getKey(), $propertyId);
            $roomType = $property === null ? null : $this->repository->lockPropertyRoomType($property, $input->roomTypeId);
            if ($roomType === null) {
                return null;
            }
            if ($this->repository->roomTypeHasReferences($roomType)) {
                throw new AppException('CATALOG_ROOM_TYPE_IN_USE', 'Loại phòng đang được sử dụng và không thể xóa.', 409);
            }

            $this->repository->deleteHostRoomType($roomType);
            DB::afterCommit(fn () => Log::info('Xóa loại phòng thành công.', [
                'property_id' => $propertyId,
                'room_type_id' => $input->roomTypeId,
            ]));

            return new DeleteHostRoomTypeResponse($input->roomTypeId);
        });
    }

    public function update(User $actor, UpdateHostRoomTypeRequest $input): ?HostRoomTypeResponse
    {
        $this->validator->authorizeHost($actor);

        return DB::transaction(function () use ($actor, $input): ?HostRoomTypeResponse {
            $propertyId = $this->repository->findHostRoomTypePropertyId($actor->getKey(), $input->roomTypeId);
            $property = $propertyId === null ? null : $this->repository->lockHostProperty($actor->getKey(), $propertyId);
            $roomType = $property === null ? null : $this->repository->lockPropertyRoomType($property, $input->roomTypeId);
            if ($roomType === null) {
                return null;
            }
            if ($roomType->name !== $input->name && $this->repository->hostRoomTypeNameExists($property, $input->name, $input->roomTypeId)) {
                throw new AppException('CATALOG_ROOM_TYPE_NAME_CONFLICT', 'Cơ sở đã có loại phòng cùng tên.', 409);
            }

            $changed = $this->repository->updateHostRoomType($roomType, $input->name, $input->description);
            $result = $this->mapper->toHostRoomType($roomType);
            if ($changed) {
                DB::afterCommit(fn () => Log::info('Cập nhật loại phòng thành công.', [
                    'property_id' => $propertyId,
                    'room_type_id' => $result->id,
                ]));
            }

            return $result;
        });
    }

    public function create(User $actor, CreateHostRoomTypeRequest $input): ?HostRoomTypeResponse
    {
        $this->validator->authorizeHost($actor);

        return DB::transaction(function () use ($actor, $input): ?HostRoomTypeResponse {
            $property = $this->repository->lockHostProperty($actor->getKey(), $input->propertyId);
            if ($property === null) {
                return null;
            }
            if ($this->repository->hostRoomTypeNameExists($property, $input->name)) {
                throw new AppException('CATALOG_ROOM_TYPE_NAME_CONFLICT', 'Cơ sở đã có loại phòng cùng tên.', 409);
            }

            $roomType = $this->repository->createHostRoomType($property, $input->name, $input->description);
            $result = $this->mapper->toHostRoomType($roomType);
            DB::afterCommit(fn () => Log::info('Thêm loại phòng thành công.', [
                'property_id' => $property->getKey(),
                'room_type_id' => $result->id,
            ]));

            return $result;
        });
    }
}
