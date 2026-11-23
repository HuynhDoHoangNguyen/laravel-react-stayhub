<?php

namespace App\DTOs\Catalog\Requests;

final readonly class UpdateAdminAmenityRequest
{
    public function __construct(
        public int $amenityId,
        public CreateAdminAmenityRequest $values,
        public string $version,
    ) {}
}
