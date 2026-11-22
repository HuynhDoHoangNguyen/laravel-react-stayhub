<?php

namespace App\DTOs\Catalog\Requests;

use App\Enums\PropertyStatus;
use App\Enums\PropertyType;

final readonly class AdminPropertyQueryRequest
{
    public function __construct(
        public ?PropertyStatus $status = null,
        public int $page = 1,
        public int $perPage = 10,
        public string $search = '',
        public ?PropertyType $type = null,
        public ?int $hostId = null,
    ) {}
}
