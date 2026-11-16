<?php

namespace App\DTOs\Auth\Requests;

use SensitiveParameter;

final readonly class RegisterRequest
{
    public function __construct(
        public string $name,
        public string $email,
        #[SensitiveParameter]
        public string $password,
    ) {}
}
