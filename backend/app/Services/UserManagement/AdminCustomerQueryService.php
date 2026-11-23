<?php

namespace App\Services\UserManagement;

use App\DTOs\UserManagement\Requests\AdminCustomerQueryRequest;
use App\DTOs\UserManagement\Requests\AdminCustomerDetailQuery;
use App\DTOs\UserManagement\Responses\AdminCustomerDetailResponse;
use App\Mappers\UserManagement\AdminCustomerMapper;
use App\Models\User;
use App\Repositories\UserManagement\AdminCustomerRepository;
use App\Validators\UserManagement\AdminCustomerValidator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class AdminCustomerQueryService
{
    public function __construct(
        private readonly AdminCustomerRepository $repository,
        private readonly AdminCustomerMapper $mapper,
        private readonly AdminCustomerValidator $validator,
    ) {}

    public function listing(User $actor, AdminCustomerQueryRequest $input): LengthAwarePaginator
    {
        $this->validator->authorize($actor);
        $page = $this->repository->paginate($input);
        $page->setCollection($page->getCollection()->map($this->mapper->toListItem(...)));

        return $page;
    }

    public function detail(User $actor, AdminCustomerDetailQuery $input): ?AdminCustomerDetailResponse
    {
        $this->validator->authorize($actor);
        $customer = $this->repository->findCustomer($input->customerId);

        return $customer === null ? null : $this->mapper->toDetail($customer);
    }
}
