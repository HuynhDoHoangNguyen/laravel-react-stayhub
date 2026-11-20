<?php

namespace App\DTOs\Booking\Requests;

class SetServiceStatusRequest
{
    public function __construct(
        public readonly bool $status
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            status: filter_var($data['status'], FILTER_VALIDATE_BOOLEAN)
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
        ];
    }
}
