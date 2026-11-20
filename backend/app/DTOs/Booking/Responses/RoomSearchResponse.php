<?php

namespace App\DTOs\Booking\Responses;

use JsonSerializable;

final readonly class RoomSearchResponse implements JsonSerializable
{
    /**
     * @param array<string, mixed>|null $property
     * @param array<string, mixed>|null $roomType
     * @param list<array<string, mixed>>|null $amenities
     * @param list<array<string, mixed>>|null $images
     */
    public function __construct(
        public int $id,
        public int $propertyId,
        public int $roomTypeId,
        public string $roomNumber,
        public string $name,
        public ?string $description,
        public int $capacity,
        public ?string $pricePerNight,
        public string $status,
        public ?array $property = null,
        public ?array $roomType = null,
        public ?array $amenities = null,
        public ?array $images = null,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        $data = [
            'id' => $this->id,
            'property_id' => $this->propertyId,
            'room_type_id' => $this->roomTypeId,
            'room_number' => $this->roomNumber,
            'name' => $this->name,
            'description' => $this->description,
            'capacity' => $this->capacity,
            'price_per_night' => $this->pricePerNight,
            'status' => $this->status,
        ];

        if ($this->property !== null) {
            $data['property'] = $this->property;
        }

        if ($this->roomType !== null) {
            $data['room_type'] = $this->roomType;
        }

        if ($this->amenities !== null) {
            $data['amenities'] = $this->amenities;
        }

        if ($this->images !== null) {
            $data['images'] = $this->images;
        }

        return $data;
    }
}
