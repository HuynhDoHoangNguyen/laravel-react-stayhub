<?php

namespace App\DTOs\Booking\Requests;

use App\Support\DecimalMoney;

class UpdateServiceRequest
{
    public function __construct(
        public readonly string $name,
        public readonly string $unit,
        public readonly string $price,
        public readonly ?string $description = null
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) ($data['name'] ?? ''),
            unit: (string) ($data['unit'] ?? ''),
            price: DecimalMoney::normalizeMoney($data['price'] ?? 0, 'price'),
            description: isset($data['description']) && $data['description'] !== '' ? (string) $data['description'] : null
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'unit' => $this->unit,
            'price' => $this->price,
        ];
    }
}
