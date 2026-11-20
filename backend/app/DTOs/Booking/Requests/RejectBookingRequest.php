<?php

namespace App\DTOs\Booking\Requests;

final readonly class RejectBookingRequest
{
    public function __construct(
        public ?string $reason = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $reason = isset($data['reason']) && is_string($data['reason']) ? trim($data['reason']) : null;
        if ($reason === '') {
            $reason = null;
        }

        return new self(
            reason: $reason,
        );
    }
}
