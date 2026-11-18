<?php

namespace App\DTOs\Catalog\Responses;

use JsonSerializable;
use Symfony\Component\ObjectMapper\Attribute\Map;

final readonly class PublicRoomListItemResponse implements JsonSerializable
{
    /** @param array{id:int,name:string,type:string,address:string,rating:?float,review_count:int} $property */
    public function __construct(
        #[Map(source: 'room')] public PublicRoomResponse $room,
        #[Map(source: 'property')] public array $property,
    ) {}

    public function jsonSerialize(): array
    {
        return $this->room->jsonSerialize() + [
            'property' => $this->property, 'currency' => 'VND', 'price_unit' => 'night',
        ];
    }
}
