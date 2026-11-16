<?php

namespace App\DTOs\Catalog\Responses;

use App\Enums\PropertyType;
use JsonSerializable;
use Symfony\Component\ObjectMapper\Attribute\Map;

final readonly class PublicPropertyListItemResponse implements JsonSerializable
{
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
        #[Map(source: 'check_in_time')]
        public string $check_in_time,
        #[Map(source: 'check_out_time')]
        public string $check_out_time,
        #[Map(source: 'primary_image')]
        public ?string $primary_image,
        #[Map(source: 'starting_price')]
        public ?string $starting_price,
        #[Map(source: 'rating')]
        public ?float $rating,
        #[Map(source: 'review_count')]
        public int $review_count,
    ) {}

    public static function typeValue(PropertyType $type): string
    {
        return $type->value;
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'address' => $this->address,
            'description' => $this->description,
            'check_in_time' => $this->check_in_time,
            'check_out_time' => $this->check_out_time,
            'primary_image' => $this->primary_image,
            'starting_price' => $this->starting_price,
            'currency' => 'VND',
            'price_unit' => 'night',
            'rating' => $this->rating,
            'review_count' => $this->review_count,
        ];
    }
}
