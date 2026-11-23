<?php

declare(strict_types=1);

namespace App\DTOs\Catalog\Requests;

use App\Enums\PropertyStatus;

final readonly class AdminPropertyQueryRequest
{
    public function __construct(
        public ?string $search,
        public ?PropertyStatus $status,
        public int $page,
        public int $perPage,
    ) {}

    public function toQuery(): array
    {
        return array_filter([
            'search' => $this->search,
            'status' => $this->status?->value,
            'per_page' => $this->perPage,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
