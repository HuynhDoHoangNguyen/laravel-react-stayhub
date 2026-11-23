<?php

namespace App\Services\Catalog;

use App\DTOs\Catalog\Requests\HostRoomListQuery;
use App\DTOs\Catalog\Requests\HostRoomDetailQuery;
use App\DTOs\Catalog\Responses\HostRoomDetailResponse;
use App\DTOs\Catalog\Responses\HostRoomResponse;
use App\Mappers\Catalog\CatalogMapper;
use App\Models\User;
use App\Repositories\Catalog\CatalogRepository;
use App\Validators\Catalog\CatalogValidator;

final class HostRoomQueryService
{
    public function __construct(
        private readonly CatalogRepository $repository,
        private readonly CatalogMapper $mapper,
        private readonly CatalogValidator $validator,
    ) {}

    public function detail(User $actor, HostRoomDetailQuery $input): ?HostRoomDetailResponse
    {
        $this->validator->authorizeHost($actor);
        $room = $this->repository->findHostRoom($actor->getKey(), $input->roomId);

        return $room === null ? null : $this->mapper->toHostRoomDetail($room);
    }

    /** @return list<HostRoomResponse>|null */
    public function listing(User $actor, HostRoomListQuery $input): ?array
    {
        $this->validator->authorizeHost($actor);

        return $this->repository->getHostRooms($actor->getKey(), $input->propertyId)
            ?->map($this->mapper->toHostRoom(...))->all();
    }
}
