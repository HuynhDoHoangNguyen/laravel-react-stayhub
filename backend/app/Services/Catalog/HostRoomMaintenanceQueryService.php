<?php

namespace App\Services\Catalog;

use App\DTOs\Catalog\Requests\HostRoomMaintenanceListQuery;
use App\Mappers\Catalog\CatalogMapper;
use App\Models\User;
use App\Repositories\Catalog\CatalogRepository;
use App\Validators\Catalog\CatalogValidator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class HostRoomMaintenanceQueryService
{
    public function __construct(
        private readonly CatalogRepository $repository,
        private readonly CatalogMapper $mapper,
        private readonly CatalogValidator $validator,
    ) {}

    public function listing(User $actor, HostRoomMaintenanceListQuery $input): ?LengthAwarePaginator
    {
        $this->validator->authorizeHost($actor);
        if ($this->repository->findHostRoomPropertyId($actor->getKey(), $input->roomId) === null) {
            return null;
        }

        $page = $this->repository->paginateHostRoomMaintenances($actor->getKey(), $input->roomId, $input->page);
        $page->setCollection($page->getCollection()->map($this->mapper->toHostRoomMaintenance(...)));

        return $page;
    }
}
