<?php

namespace App\DTOs\Booking\Responses;

use JsonSerializable;

final readonly class BookingServiceResponse implements JsonSerializable
{
    public function __construct(
        public int $id,
        public int $bookingId,
        public int $serviceId,
        public string $serviceName,
        public string $unit,
        public ?string $quantity,
        public ?string $unitPrice,
        public ?string $amount,
        public ?string $createdAt = null,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'booking_id' => $this->bookingId,
            'service_id' => $this->serviceId,
            'service_name' => $this->serviceName,
            'unit' => $this->unit,
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice,
            'amount' => $this->amount,
            'created_at' => $this->createdAt,
        ];
    }
}
