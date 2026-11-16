<?php

namespace App\DTOs\Review\Requests;

final readonly class HostReviewFilterRequest
{
    public function __construct(
        public int $page,
        public int $perPage,
        public ?int $propertyId,
        public ?int $rating,
        public ?string $search,
    ) {}
}
