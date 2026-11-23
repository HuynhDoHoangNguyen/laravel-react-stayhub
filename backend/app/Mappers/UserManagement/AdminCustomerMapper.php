<?php

namespace App\Mappers\UserManagement;

use App\DTOs\UserManagement\Responses\AdminCustomerListItemResponse;
use App\DTOs\UserManagement\Responses\AdminCustomerDetailResponse;
use App\Mappers\EloquentObjectMapper;
use App\Models\User;

final class AdminCustomerMapper
{
    public function __construct(private readonly EloquentObjectMapper $mapper) {}

    public function toListItem(User $user): AdminCustomerListItemResponse
    {
        return $this->mapper->map($user, AdminCustomerListItemResponse::class,
            ['id', 'name', 'email', 'phone', 'status', 'created_at']);
    }

    public function toDetail(User $user): AdminCustomerDetailResponse
    {
        return $this->mapper->map($user, AdminCustomerDetailResponse::class,
            ['id', 'name', 'email', 'phone', 'address', 'gender', 'date_of_birth', 'status', 'created_at', 'updated_at']);
    }
}
