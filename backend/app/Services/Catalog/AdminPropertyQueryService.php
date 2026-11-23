<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\DTOs\Catalog\Requests\AdminPropertyQueryRequest;
use App\Mappers\Catalog\AdminPropertyMapper;
use App\Models\Property;
use App\Repositories\Catalog\AdminPropertyRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class AdminPropertyQueryService
{
    public function __construct(
        private readonly AdminPropertyRepository $repository,
        private readonly AdminPropertyMapper $mapper,
    ) {}

    public function paginate(AdminPropertyQueryRequest $filters): LengthAwarePaginator
    {
        $page = $this->repository->paginate($filters);
        $page->through(fn (Property $property) => $this->mapper->toListItem($property));

        return $page;
    }
}
