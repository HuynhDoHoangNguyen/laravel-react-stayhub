<?php

namespace App\DTOs\Catalog\Responses;

use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use Carbon\CarbonInterface;
use JsonSerializable;
use Symfony\Component\ObjectMapper\Attribute\Map;

final readonly class AdminPropertyListItemResponse implements JsonSerializable
{
    /** @param array{id: int, name: string, email: string} $host */
    public function __construct(
        public int $id,
        public string $name,
        #[Map(source: 'type', transform: [self::class, 'typeValue'])]
        public string $type,
        public string $address,
        #[Map(source: 'status', transform: [self::class, 'statusValue'])]
        public string $status,
        public int $rooms_count,
        #[Map(source: 'created_at', transform: [self::class, 'timestamp'])]
        public ?string $created_at,
        public array $host,
    ) {}

    public static function typeValue(PropertyType $type): string
    {
        return $type->value;
    }

    public static function statusValue(PropertyStatus $status): string
    {
        return $status->value;
    }

    public static function timestamp(?CarbonInterface $date): ?string
    {
        return $date?->toISOString();
    }

    public function jsonSerialize(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'type' => $this->type,
            'address' => $this->address, 'status' => $this->status, 'rooms_count' => $this->rooms_count,
            'created_at' => $this->created_at, 'host' => $this->host];
    }
}
