<?php

namespace App\DTOs\Catalog\Requests;

final readonly class CreateHostRoomTypeRequest
{
    public function __construct(
        public int $propertyId,
        public string $name,
        public ?string $description,
    ) {}
}
