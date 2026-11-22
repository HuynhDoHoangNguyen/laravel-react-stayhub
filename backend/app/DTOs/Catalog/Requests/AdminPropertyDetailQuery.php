<?php

namespace App\DTOs\Catalog\Requests;

final readonly class AdminPropertyDetailQuery
{
    public function __construct(public int $propertyId) {}
}
