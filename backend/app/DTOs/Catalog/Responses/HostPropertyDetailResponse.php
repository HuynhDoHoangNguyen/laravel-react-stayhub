<?php

namespace App\DTOs\Catalog\Responses;

use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use JsonSerializable;
use Symfony\Component\ObjectMapper\Attribute\Map;

final readonly class HostPropertyDetailResponse implements JsonSerializable
{
    /** @param list<string> $images */
    public function __construct(
        #[Map(source: 'id')]
        public int $id,
        #[Map(source: 'name')]
        public string $name,
        #[Map(source: 'type', transform: [self::class, 'typeValue'])]
        public string $type,
        #[Map(source: 'address')]
        public string $address,
        #[Map(source: 'description')]
        public ?string $description,
        #[Map(source: 'phone')]
        public ?string $phone,
        #[Map(source: 'check_in_time')]
        public string $check_in_time,
        #[Map(source: 'check_out_time')]
        public string $check_out_time,
        #[Map(source: 'status', transform: [self::class, 'statusValue'])]
        public string $status,
        #[Map(source: 'images')]
        public array $images,
    ) {}

    public static function typeValue(PropertyType $value): string
    {
        return $value->value;
    }

    public static function statusValue(PropertyStatus $value): string
    {
        return $value->value;
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'address' => $this->address,
            'description' => $this->description,
            'phone' => $this->phone,
            'check_in_time' => $this->check_in_time,
            'check_out_time' => $this->check_out_time,
            'status' => $this->status,
            'images' => $this->images,
        ];
    }
}
