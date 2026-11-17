<?php

namespace App\DTOs\Auth\Requests;

use App\Enums\UserRole;
use SensitiveParameter;

final readonly class LoginRequest
{
    public function __construct(
        public string $email,
        #[SensitiveParameter]
        public string $password,
        public ?UserRole $expectedRole,
    ) {}
}
