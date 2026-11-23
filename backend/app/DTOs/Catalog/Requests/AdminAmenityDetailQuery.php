<?php

namespace App\DTOs\Catalog\Requests;

final readonly class AdminAmenityDetailQuery
{
    public function __construct(public int $amenityId) {}
}
