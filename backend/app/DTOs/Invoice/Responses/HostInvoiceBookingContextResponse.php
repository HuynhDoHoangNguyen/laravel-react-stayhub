<?php

namespace App\DTOs\Invoice\Responses;

use JsonSerializable;

final readonly class HostInvoiceBookingContextResponse implements JsonSerializable
{
    /** @param array{id: int, invoice_code: string}|null $invoice */
    public function __construct(
        public int $booking_id,
        public string $booking_code,
        public string $customer_name,
        public string $property_name,
        public string $room_name,
        public string $check_in_date,
        public string $check_out_date,
        public string $booking_status,
        public bool $can_create,
        public ?array $invoice,
    ) {}

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
