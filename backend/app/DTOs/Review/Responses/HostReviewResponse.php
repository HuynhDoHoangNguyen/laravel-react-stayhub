<?php

namespace App\DTOs\Review\Responses;

use Carbon\CarbonInterface;
use JsonSerializable;
use Symfony\Component\ObjectMapper\Attribute\Map;

final readonly class HostReviewResponse implements JsonSerializable
{
    public function __construct(
        #[Map(source: 'id', transform: 'intval')]
        public int $id,
        #[Map(source: 'booking_id', transform: 'intval')]
        public int $booking_id,
        #[Map(source: 'booking_code')]
        public ?string $booking_code,
        #[Map(source: 'property_id', transform: 'intval')]
        public int $property_id,
        #[Map(source: 'property_name')]
        public ?string $property_name,
        #[Map(source: 'room_name')]
        public ?string $room_name,
        #[Map(source: 'customer_name')]
        public string $customer_name,
        #[Map(source: 'rating', transform: 'intval')]
        public int $rating,
        #[Map(source: 'comment', transform: 'strval')]
        public string $comment,
        #[Map(source: 'created_at', transform: [self::class, 'toIsoString'])]
        public ?string $created_at,
    ) {}

    public static function toIsoString(?CarbonInterface $date): ?string
    {
        return $date?->toISOString();
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'booking_code' => $this->booking_code,
            'property_id' => $this->property_id,
            'property_name' => $this->property_name,
            'room_name' => $this->room_name,
            'customer_name' => $this->customer_name,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'created_at' => $this->created_at,
        ];
    }
}
