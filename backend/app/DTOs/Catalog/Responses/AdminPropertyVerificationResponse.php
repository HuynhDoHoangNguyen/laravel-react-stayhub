<?php

declare(strict_types=1);

namespace App\DTOs\Catalog\Responses;

use JsonSerializable;

final readonly class AdminPropertyVerificationResponse implements JsonSerializable
{
    public function __construct(
        public int $id,
        public string $status,
        public string $address_snapshot,
        public string $address_current,
        public string $submitted_at,
        public ?string $reviewed_at,
        public ?string $rejection_reason,
    ) {}

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
