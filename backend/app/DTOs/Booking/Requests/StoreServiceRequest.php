<?php

namespace App\DTOs\Booking\Requests;

use App\Support\DecimalMoney;

class StoreServiceRequest
{
    public function __construct(
        public readonly int $propertyId,
        public readonly string $name,
        public readonly string $unit,
        public readonly string $price,
        public readonly bool $status = true,
        public readonly ?string $description = null
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $status = true;
        if (array_key_exists('status', $data) && $data['status'] !== null) {
            $status = filter_var($data['status'], FILTER_VALIDATE_BOOLEAN);
        }

        return new self(
            propertyId: (int) ($data['property_id'] ?? $data['propertyId']),
            name: (string) ($data['name'] ?? ''),
            unit: (string) ($data['unit'] ?? ''),
            price: DecimalMoney::normalizeMoney($data['price'] ?? 0, 'price'),
            status: $status,
            description: isset($data['description']) && $data['description'] !== '' ? (string) $data['description'] : null
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'property_id' => $this->propertyId,
            'name' => $this->name,
            'description' => $this->description,
            'unit' => $this->unit,
            'price' => $this->price,
            'status' => $this->status,
        ];
    }
}
