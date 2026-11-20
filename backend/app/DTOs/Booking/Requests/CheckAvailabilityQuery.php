<?php

namespace App\DTOs\Booking\Requests;

final readonly class CheckAvailabilityQuery
{
    public function __construct(
        public string $checkInDate,
        public string $checkOutDate,
        public int $guestCount,
    ) {}
}
