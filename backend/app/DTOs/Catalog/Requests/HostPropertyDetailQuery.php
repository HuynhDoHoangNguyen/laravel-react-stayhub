<?php

namespace App\DTOs\Catalog\Requests;

final readonly class HostPropertyDetailQuery
{
    public function __construct(public int $propertyId) {}
}
