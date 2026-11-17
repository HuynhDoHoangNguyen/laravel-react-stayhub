<?php

namespace App\DTOs\Review\Requests;

final readonly class CreateReviewRequest
{
    public function __construct(
        public int $bookingId,
        // Laravel's integer rule accepts true. Domain validation rejects it after eligibility checks.
        public int|bool $rating,
        public string $comment,
    ) {}
}
