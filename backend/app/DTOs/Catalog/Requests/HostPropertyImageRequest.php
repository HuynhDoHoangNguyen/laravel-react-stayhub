<?php

namespace App\DTOs\Catalog\Requests;

final readonly class HostPropertyImageRequest
{
    public function __construct(public int $propertyId, public int $imageId) {}
}
