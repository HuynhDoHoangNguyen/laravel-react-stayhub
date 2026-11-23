<?php

namespace App\DTOs\UserManagement\Responses;

use JsonSerializable;

final readonly class AccountStatusResponse implements JsonSerializable
{
    public function __construct(
        public int $id,
        public string $status,
        public ?string $updated_at,
        public bool $changed,
    ) {}

    public function jsonSerialize(): array
    {
        return ['id' => $this->id, 'status' => $this->status,
            'updated_at' => $this->updated_at, 'changed' => $this->changed];
    }
}
