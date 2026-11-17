<?php

namespace App\DTOs\Catalog\Requests;

final readonly class UpdateHostRoomTypeRequest
{
    public function __construct(
        public int $roomTypeId,
        public string $name,
        public ?string $description,
    ) {}
}
