<?php

namespace App\Mappers\Auth;

use App\DTOs\Auth\Responses\AuthUserResponse;
use App\Mappers\EloquentObjectMapper;
use App\Models\User;
use App\Services\Profile\AvatarService;

final class AuthUserMapper
{
    public function __construct(private readonly EloquentObjectMapper $objectMapper) {}

    public function toResponse(User $user): AuthUserResponse
    {
        $fields = ['id', 'name', 'email', 'role', 'status'];
        $avatarPath = AvatarService::managedPath($user);
        $prepared = [
            'avatar' => $avatarPath !== null
                ? route('profile.avatar', ['v' => substr(hash('sha256', $avatarPath), 0, 16)])
                : $user->avatar,
        ];

        $attributes = $user->getAttributes();
        foreach (['phone', 'address', 'gender', 'date_of_birth', 'email_verified_at', 'created_at', 'updated_at'] as $field) {
            if (array_key_exists($field, $attributes)) {
                $fields[] = $field;
            } else {
                $prepared[$field] = null;
            }
        }

        return $this->objectMapper->map($user, AuthUserResponse::class, $fields, $prepared);
    }
}
