<?php

namespace App\DTOs\Booking\Responses;

use JsonSerializable;

final readonly class RoomAvailabilityResponse implements JsonSerializable
{
    /**
     * @param list<string> $reasons
     * @param list<array<string, string>> $blockedRanges
     */
    public function __construct(
        public bool $available,
        public array $reasons = [],
        public array $blockedRanges = [],
    ) {}

    /** @return array{available: bool, reasons: list<string>, blocked_ranges: list<array<string, string>>} */
    public function jsonSerialize(): array
    {
        return [
            'available' => $this->available,
            'reasons' => $this->reasons,
            'blocked_ranges' => $this->blockedRanges,
        ];
    }
}
