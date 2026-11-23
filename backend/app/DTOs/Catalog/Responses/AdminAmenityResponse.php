<?php

namespace App\DTOs\Catalog\Responses;

use Carbon\CarbonInterface;
use JsonSerializable;
use Symfony\Component\ObjectMapper\Attribute\Map;

final readonly class AdminAmenityResponse implements JsonSerializable
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $icon,
        public ?string $description,
        public bool $status,
        public int $rooms_count,
        #[Map(source: 'created_at', transform: [self::class, 'timestamp'])]
        public ?string $created_at,
        #[Map(source: 'updated_at', transform: [self::class, 'timestamp'])]
        public ?string $updated_at,
        public string $version,
    ) {}

    public static function timestamp(?CarbonInterface $date): ?string
    {
        return $date?->toISOString();
    }

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
