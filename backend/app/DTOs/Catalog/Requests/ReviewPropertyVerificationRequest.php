<?php

declare(strict_types=1);

namespace App\DTOs\Catalog\Requests;

final readonly class ReviewPropertyVerificationRequest
{
    public function __construct(public bool $approve, public ?string $reason) {}
}
