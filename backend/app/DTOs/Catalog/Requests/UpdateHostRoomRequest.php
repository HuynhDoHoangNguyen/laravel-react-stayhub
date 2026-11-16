<?php

namespace App\DTOs\Catalog\Requests;

final readonly class UpdateHostRoomRequest
{
    public function __construct(
        public int $roomId,
        public int $roomTypeId,
        public string $roomNumber,
        public string $name,
        public ?string $description,
        public int $capacity,
        public string $pricePerNight,
    ) {}
}
