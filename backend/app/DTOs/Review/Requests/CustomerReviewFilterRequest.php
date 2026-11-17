<?php

namespace App\DTOs\Review\Requests;

final readonly class CustomerReviewFilterRequest
{
    public function __construct(public int $page, public int $perPage) {}
}
