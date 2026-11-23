<?php

namespace App\DTOs\UserManagement\Requests;

use App\Enums\UserStatus;

final readonly class AdminHostQueryRequest
{
    public function __construct(
        public string $search = '',
        public ?UserStatus $status = null,
        public int $page = 1,
        public int $perPage = 10,
    ) {}
}
