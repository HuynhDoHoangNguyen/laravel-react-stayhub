<?php

namespace App\DTOs\Catalog\Requests;

final readonly class HostRoomAmenityTarget
{
    public function __construct(public int $roomId, public int $amenityId) {}
}
