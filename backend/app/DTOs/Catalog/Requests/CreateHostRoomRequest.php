<?php

namespace App\DTOs\Catalog\Requests;

final readonly class CreateHostRoomRequest
{
    public function __construct(
        public int $propertyId,
        public int $roomTypeId,
        public string $roomNumber,
        public string $name,
        public ?string $description,
        public int $capacity,
        public string $pricePerNight,
    ) {}
}
