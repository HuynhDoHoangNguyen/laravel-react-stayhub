<?php

namespace App\DTOs\UserManagement\Requests;

use App\Enums\UserStatus;

final readonly class UpdateAccountStatusRequest
{
    public function __construct(
        public int $userId,
        public UserStatus $status,
        public UserStatus $expectedStatus,
    ) {}
}
