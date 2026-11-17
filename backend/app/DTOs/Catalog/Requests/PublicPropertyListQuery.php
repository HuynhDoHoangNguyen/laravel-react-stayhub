<?php

namespace App\DTOs\Catalog\Requests;

final readonly class PublicPropertyListQuery
{
    public function __construct(
        public int $page,
        public int $perPage,
    ) {}
}
