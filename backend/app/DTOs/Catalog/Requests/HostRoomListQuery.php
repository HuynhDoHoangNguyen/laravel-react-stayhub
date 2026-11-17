<?php

namespace App\DTOs\Catalog\Requests;

final readonly class HostRoomListQuery
{
    public function __construct(public int $propertyId) {}
}
