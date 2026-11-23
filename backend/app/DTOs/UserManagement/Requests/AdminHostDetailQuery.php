<?php

namespace App\DTOs\UserManagement\Requests;

final readonly class AdminHostDetailQuery
{
    public function __construct(public int $hostId) {}
}
