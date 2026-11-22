<?php

namespace App\Mappers\UserManagement;

use App\DTOs\UserManagement\Responses\AdminHostListItemResponse;
use App\DTOs\UserManagement\Responses\AdminHostDetailResponse;
use App\Mappers\EloquentObjectMapper;
use App\Models\User;

final class AdminHostMapper
{
    public function __construct(private readonly EloquentObjectMapper $mapper) {}

    public function toListItem(User $user): AdminHostListItemResponse
    {
        return $this->mapper->map($user, AdminHostListItemResponse::class,
            ['id', 'name', 'email', 'phone', 'status', 'created_at'],
            ['properties_count' => (int) $user->getAttribute('properties_count')]);
    }

    public function toDetail(User $user): AdminHostDetailResponse
    {
        return $this->mapper->map($user, AdminHostDetailResponse::class,
            ['id', 'name', 'email', 'phone', 'address', 'gender', 'date_of_birth', 'status', 'created_at', 'updated_at'],
            ['properties_count' => (int) $user->getAttribute('properties_count')]);
    }
}
