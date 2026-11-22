<?php

namespace App\Services\UserManagement;

use App\DTOs\UserManagement\Requests\AdminHostQueryRequest;
use App\DTOs\UserManagement\Requests\AdminHostDetailQuery;
use App\DTOs\UserManagement\Responses\AdminHostDetailResponse;
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

    public function detail(User $actor, AdminHostDetailQuery $input): ?AdminHostDetailResponse
    {
        $this->validator->authorize($actor);
        $host = $this->repository->findHost($input->hostId);

        return $host === null ? null : $this->mapper->toDetail($host);
    }
}
