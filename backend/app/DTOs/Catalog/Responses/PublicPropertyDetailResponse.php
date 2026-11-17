<?php

namespace App\DTOs\Catalog\Responses;

use JsonSerializable;
use Symfony\Component\ObjectMapper\Attribute\Map;

final readonly class PublicPropertyDetailResponse implements JsonSerializable
{
    /**
     * @param list<string> $images
     * @param list<PublicRoomResponse> $rooms
     * @param list<array{id: int, name: string}> $amenities
     */
    public function __construct(
        #[Map(source: 'header')]
        public PublicPropertyListItemResponse $header,
        #[Map(source: 'images')]
        public array $images,
        #[Map(source: 'rooms')]
        public array $rooms,
        #[Map(source: 'amenities')]
        public array $amenities,
    ) {}

    public function jsonSerialize(): array
    {
        return $this->header->jsonSerialize() + [
            'images' => $this->images,
            'rooms' => $this->rooms,
            'amenities' => $this->amenities,
        ];
    }
}
