<?php

namespace App\DTOs\Catalog\Responses;

use JsonSerializable;

final readonly class PublicRoomFilterOptionsResponse implements JsonSerializable
{
    public function __construct(public array $roomTypes, public array $amenities) {}

    public function jsonSerialize(): array
    {
        return ['room_types' => $this->roomTypes, 'amenities' => $this->amenities];
    }
}
