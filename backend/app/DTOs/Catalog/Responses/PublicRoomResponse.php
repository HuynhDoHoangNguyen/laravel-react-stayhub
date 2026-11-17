<?php

namespace App\DTOs\Catalog\Responses;

use JsonSerializable;
use Symfony\Component\ObjectMapper\Attribute\Map;

final readonly class PublicRoomResponse implements JsonSerializable
{
    /**
     * @param array{id: int, name: string} $room_type
     * @param list<string> $images
     * @param list<array{id: int, name: string}> $amenities
     */
    public function __construct(
        #[Map(source: 'id')]
        public int $id,
        #[Map(source: 'room_number')]
        public string $room_number,
        #[Map(source: 'name')]
        public string $name,
        #[Map(source: 'description')]
        public ?string $description,
        #[Map(source: 'capacity')]
        public int $capacity,
        #[Map(source: 'price_per_night')]
        public string $price_per_night,
        #[Map(source: 'room_type')]
        public array $room_type,
        #[Map(source: 'images')]
        public array $images,
        #[Map(source: 'amenities')]
        public array $amenities,
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'room_number' => $this->room_number,
            'name' => $this->name,
            'description' => $this->description,
            'capacity' => $this->capacity,
            'price_per_night' => $this->price_per_night,
            'room_type' => $this->room_type,
            'images' => $this->images,
            'amenities' => $this->amenities,
        ];
    }
}
