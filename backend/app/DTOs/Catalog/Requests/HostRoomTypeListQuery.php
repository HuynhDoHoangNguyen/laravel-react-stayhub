<?php

namespace App\DTOs\Catalog\Requests;

final readonly class HostRoomTypeListQuery
{
    public function __construct(public int $propertyId) {}
}
