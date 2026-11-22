<?php

namespace App\Mappers\UserManagement;

use App\DTOs\UserManagement\Responses\AdminHostListItemResponse;
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
}
