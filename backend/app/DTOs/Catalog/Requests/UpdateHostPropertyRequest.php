<?php

namespace App\DTOs\Catalog\Requests;

final readonly class UpdateHostPropertyRequest
{
    public function __construct(
        public string $name,
        public string $type,
        public string $address,
        public ?string $description,
        public ?string $phone,
        public string $checkInTime,
        public string $checkOutTime,
    ) {}
}
