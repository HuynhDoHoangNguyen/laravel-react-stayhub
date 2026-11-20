<?php

namespace App\DTOs\Booking\Responses;

use JsonSerializable;

final readonly class PropertyServiceResponse implements JsonSerializable
{
    /**
     * @param array{id: int, name: string}|null $property
     */
    public function __construct(
        public int $id,
        public int $propertyId,
        public string $name,
        public ?string $description,
        public string $unit,
        public ?string $price,
        public bool $status,
        public ?array $property = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        $data = [
            'id' => $this->id,
            'property_id' => $this->propertyId,
            'name' => $this->name,
            'description' => $this->description,
            'unit' => $this->unit,
            'price' => $this->price,
            'status' => $this->status,
        ];

        if ($this->property !== null) {
            $data['property'] = $this->property;
        }

        $data['created_at'] = $this->createdAt;
        $data['updated_at'] = $this->updatedAt;

        return $data;
    }
}
