<?php

namespace App\DTOs\Booking\Requests;

use App\Support\DecimalMoney;

class AddBookingServiceRequest
{
    public function __construct(
        public readonly int $serviceId,
        public readonly string $quantity
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            serviceId: (int) ($data['service_id'] ?? $data['serviceId']),
            quantity: DecimalMoney::normalizeQuantity($data['quantity'])
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'service_id' => $this->serviceId,
            'quantity' => $this->quantity,
        ];
    }
}
