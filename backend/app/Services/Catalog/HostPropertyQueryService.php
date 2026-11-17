<?php

namespace App\Services\Catalog;

use App\DTOs\Catalog\Requests\HostPropertyDetailQuery;
use App\DTOs\Catalog\Responses\HostPropertyDetailResponse;
use App\DTOs\Catalog\Responses\HostPropertyResponse;
use App\Mappers\Catalog\CatalogMapper;
use App\Models\User;
use App\Repositories\Catalog\CatalogRepository;
use App\Validators\Catalog\CatalogValidator;
use Illuminate\Support\Collection;

class HostPropertyQueryService
{
    public function __construct(
        private readonly CatalogRepository $repository,
        private readonly CatalogMapper $mapper,
        private readonly CatalogValidator $validator,
    ) {}

    /** @return Collection<int, HostPropertyResponse> */
    public function listForHost(User $host): Collection
    {
        $this->validator->authorizeHost($host);

        return $this->repository->getHostProperties($host->getKey())
            ->map($this->mapper->toHostProperty(...));
    }

    public function detailForHost(User $host, HostPropertyDetailQuery $input): ?HostPropertyDetailResponse
    {
        $this->validator->authorizeHost($host);
        $property = $this->repository->findHostProperty($host->getKey(), $input->propertyId);

        return $property === null ? null : $this->mapper->toHostPropertyDetail($property);
    }
}
