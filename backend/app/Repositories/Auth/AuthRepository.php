<?php

namespace App\Repositories\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use SensitiveParameter;

class AuthRepository
{
    /**
     * @param array{name: string, email: string, password: string, role: UserRole, status: UserStatus, email_verified_at: null} $attributes Prepared by the service, including the password hash.
     */
    public function createUser(#[SensitiveParameter] array $attributes): User
    {
        return User::create($attributes);
    }
}
