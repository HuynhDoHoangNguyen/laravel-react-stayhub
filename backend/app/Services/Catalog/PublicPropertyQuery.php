<?php

namespace App\Services\Catalog;

use App\DTOs\Catalog\Requests\PublicPropertyDetailQuery;
use App\DTOs\Catalog\Requests\PublicPropertyListQuery;
use App\DTOs\Catalog\Responses\PublicPropertyDetailResponse;
use App\Mappers\Catalog\CatalogMapper;
use App\Repositories\Catalog\CatalogRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PublicPropertyQuery
{
    public function __construct(
        private readonly CatalogRepository $repository,
        private readonly CatalogMapper $mapper,
    ) {}

    public function listing(PublicPropertyListQuery $filters): LengthAwarePaginator
    {
        $page = $this->repository->paginatePublicProperties($filters->perPage, $filters->page);
        $page->setCollection($page->getCollection()->map($this->mapper->toPublicListItem(...)));

        return $page->withQueryString();
    }

    public function detail(PublicPropertyDetailQuery $input): ?PublicPropertyDetailResponse
    {
        $property = $this->repository->findPublicProperty($input->propertyId);

        return $property === null ? null : $this->mapper->toPublicDetail($property);
    }
}
