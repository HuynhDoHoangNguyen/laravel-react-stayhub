<?php

namespace App\DTOs\Invoice\Responses;

use JsonSerializable;

final readonly class HostEligibleBookingResponse implements JsonSerializable
{
    public function __construct(
        public int $booking_id,
        public string $booking_code,
        public string $customer_name,
        public string $property_name,
        public string $room_name,
        public string $check_in_date,
        public string $check_out_date,
    ) {}

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
