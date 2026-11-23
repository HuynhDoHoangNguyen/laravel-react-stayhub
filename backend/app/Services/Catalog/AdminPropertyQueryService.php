<?php

namespace App\Services\Catalog;

use App\DTOs\Catalog\Requests\AdminPropertyQueryRequest;
use App\DTOs\Catalog\Requests\AdminPropertyDetailQuery;
use App\DTOs\Catalog\Responses\AdminPropertyDetailResponse;
use App\Mappers\Catalog\AdminPropertyMapper;
use App\Models\User;
use App\Repositories\Catalog\AdminPropertyRepository;
use App\Validators\Catalog\AdminPropertyValidator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class AdminPropertyQueryService
{
    public function __construct(
        private readonly AdminPropertyRepository $repository,
        private readonly AdminPropertyMapper $mapper,
        private readonly AdminPropertyValidator $validator,
    ) {}

    public function listing(User $actor, AdminPropertyQueryRequest $input): LengthAwarePaginator
    {
        $this->validator->authorize($actor);
        $page = $this->repository->paginate($input);
        $page->setCollection($page->getCollection()->map($this->mapper->toListItem(...)));

        return $page;
    }

    public function detail(User $actor, AdminPropertyDetailQuery $input): ?AdminPropertyDetailResponse
    {
        $this->validator->authorize($actor);
        $property = $this->repository->findProperty($input->propertyId);

        return $property === null ? null : $this->mapper->toDetail($property);
    }
}
