<?php

namespace App\DTOs\Catalog\Responses;

use JsonSerializable;
use Symfony\Component\ObjectMapper\Attribute\Map;

final readonly class AdminPropertyDetailResponse implements JsonSerializable
{
    /** @param array{id: int, name: string, email: string} $host */
    public function __construct(
        public int $id,
        public string $name,
        #[Map(source: 'type', transform: [AdminPropertyListItemResponse::class, 'typeValue'])]
        public string $type,
        public string $address,
        #[Map(source: 'status', transform: [AdminPropertyListItemResponse::class, 'statusValue'])]
        public string $status,
        public int $rooms_count,
        #[Map(source: 'created_at', transform: [AdminPropertyListItemResponse::class, 'timestamp'])]
        public ?string $created_at,
        public array $host,
        public ?string $description,
        public ?string $phone,
        public string $check_in_time,
        public string $check_out_time,
        public int $room_types_count,
        #[Map(source: 'updated_at', transform: [AdminPropertyListItemResponse::class, 'timestamp'])]
        public ?string $updated_at,
    ) {}

    public function jsonSerialize(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'type' => $this->type,
            'address' => $this->address, 'status' => $this->status, 'rooms_count' => $this->rooms_count,
            'created_at' => $this->created_at, 'host' => $this->host,
            'description' => $this->description, 'phone' => $this->phone,
            'check_in_time' => $this->check_in_time, 'check_out_time' => $this->check_out_time,
            'room_types_count' => $this->room_types_count, 'updated_at' => $this->updated_at];
    }
}
