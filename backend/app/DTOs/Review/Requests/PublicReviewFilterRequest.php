<?php

namespace App\DTOs\Review\Requests;

final readonly class PublicReviewFilterRequest
{
    public function __construct(
        public int $propertyId,
        public int $page,
        public int $perPage,
        public ?int $rating,
        public string $sortBy,
    ) {}
}
