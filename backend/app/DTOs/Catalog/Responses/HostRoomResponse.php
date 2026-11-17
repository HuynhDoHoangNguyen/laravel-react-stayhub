<?php

namespace App\DTOs\Catalog\Responses;

use App\Enums\RoomStatus;
use JsonSerializable;
use Symfony\Component\ObjectMapper\Attribute\Map;

final readonly class HostRoomResponse implements JsonSerializable
{
    /** @param array{id: int, name: string}|null $room_type */
    public function __construct(
        #[Map(source: 'id')] public int $id,
        #[Map(source: 'room_number')] public string $room_number,
        #[Map(source: 'name')] public string $name,
        #[Map(source: 'description')] public ?string $description,
        #[Map(source: 'capacity')] public int $capacity,
        #[Map(source: 'price_per_night')] public string $price_per_night,
        #[Map(source: 'status', transform: [self::class, 'statusValue'])] public string $status,
        #[Map(source: 'room_type')] public ?array $room_type,
    ) {}

    public static function statusValue(RoomStatus $status): string
    {
        return $status->value;
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'room_number' => $this->room_number,
            'name' => $this->name,
            'description' => $this->description,
            'capacity' => $this->capacity,
            'price_per_night' => $this->price_per_night,
            'status' => $this->status,
            'room_type' => $this->room_type,
        ];
    }
}
