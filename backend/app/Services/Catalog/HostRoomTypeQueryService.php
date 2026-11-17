<?php

namespace App\Services\Catalog;

use App\DTOs\Catalog\Requests\HostRoomTypeListQuery;
use App\DTOs\Catalog\Responses\HostRoomTypeResponse;
use App\Mappers\Catalog\CatalogMapper;
use App\Models\User;
use App\Repositories\Catalog\CatalogRepository;
use App\Validators\Catalog\CatalogValidator;

final class HostRoomTypeQueryService
{
    public function __construct(
        private readonly CatalogRepository $repository,
        private readonly CatalogMapper $mapper,
        private readonly CatalogValidator $validator,
    ) {}

    /** @return list<HostRoomTypeResponse>|null */
    public function listing(User $actor, HostRoomTypeListQuery $input): ?array
    {
        $this->validator->authorizeHost($actor);

        return $this->repository->getHostRoomTypes($actor->getKey(), $input->propertyId)
            ?->map($this->mapper->toHostRoomType(...))->all();
    }
}
