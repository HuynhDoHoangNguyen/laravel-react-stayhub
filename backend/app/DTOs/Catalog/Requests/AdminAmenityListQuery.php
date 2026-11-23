<?php

namespace App\DTOs\Catalog\Requests;

final readonly class AdminAmenityListQuery
{
    public function __construct(
        public string $search,
        public ?bool $status,
        public int $page,
        public int $perPage,
    ) {}
}
