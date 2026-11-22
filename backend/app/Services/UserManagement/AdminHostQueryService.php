<?php

namespace App\Services\UserManagement;

use App\DTOs\UserManagement\Requests\AdminHostQueryRequest;
use App\Mappers\UserManagement\AdminHostMapper;
use App\Models\User;
use App\Repositories\UserManagement\AdminHostRepository;
use App\Validators\UserManagement\AdminHostValidator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class AdminHostQueryService
{
    public function __construct(
        private readonly AdminHostRepository $repository,
        private readonly AdminHostMapper $mapper,
        private readonly AdminHostValidator $validator,
    ) {}

    public function listing(User $actor, AdminHostQueryRequest $input): LengthAwarePaginator
    {
        $this->validator->authorize($actor);
        $page = $this->repository->paginate($input);
        $page->setCollection($page->getCollection()->map($this->mapper->toListItem(...)));

        return $page;
    }
}
