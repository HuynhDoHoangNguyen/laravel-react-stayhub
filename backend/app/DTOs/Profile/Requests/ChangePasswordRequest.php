<?php

namespace App\DTOs\Profile\Requests;

use SensitiveParameter;

final readonly class ChangePasswordRequest
{
    public function __construct(
        #[SensitiveParameter]
        public string $currentPassword,
        #[SensitiveParameter]
        public string $newPassword,
    ) {}
}
