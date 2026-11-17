<?php

namespace App\DTOs\Catalog\Responses;

use JsonSerializable;
use Symfony\Component\ObjectMapper\Attribute\Map;

final readonly class HostRoomDetailResponse implements JsonSerializable
{
    /**
     * @param array{id: int, name: string, status: string} $property
     * @param list<string> $images
     * @param list<array{id: int, name: string, status: bool}> $amenities
     */
    public function __construct(
        #[Map(source: 'room')] public HostRoomResponse $room,
        #[Map(source: 'property')] public array $property,
        #[Map(source: 'images')] public array $images,
        #[Map(source: 'amenities')] public array $amenities,
    ) {}

    public function jsonSerialize(): array
    {
        return $this->room->jsonSerialize() + [
            'property' => $this->property,
            'images' => $this->images,
            'amenities' => $this->amenities,
        ];
    }
}
