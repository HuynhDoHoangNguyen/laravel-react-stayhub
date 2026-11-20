<?php

namespace App\DTOs\Booking\Requests;

use App\Support\DecimalMoney;

class UpdateBookingServiceRequest
{
    public function __construct(
        public readonly string $quantity
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            quantity: DecimalMoney::normalizeQuantity($data['quantity'])
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'quantity' => $this->quantity,
        ];
    }
}
