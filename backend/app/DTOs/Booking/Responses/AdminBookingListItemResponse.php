<?php

declare(strict_types=1);

namespace App\DTOs\Booking\Responses;

use DateTimeInterface;
use JsonSerializable;
use Symfony\Component\ObjectMapper\Attribute\Map;

final readonly class AdminBookingListItemResponse implements JsonSerializable
{
    public function __construct(
        #[Map(transform: 'intval')]
        public int $id,
        public string $booking_code,
        #[Map(transform: 'intval')]
        public int $customer_id,
        public ?string $customer_name,
        #[Map(transform: 'intval')]
        public int $room_id,
        public ?string $room_name,
        public ?string $room_number,
        public ?int $property_id,
        public ?string $property_name,
        public ?int $host_id,
        public ?string $host_name,
        #[Map(transform: [self::class, 'dateString'])]
        public string $check_in_date,
        #[Map(transform: [self::class, 'dateString'])]
        public string $check_out_date,
        #[Map(transform: 'intval')]
        public int $guest_count,
        #[Map(transform: 'intval')]
        public int $number_of_nights,
        public string $price_per_night,
        public string $room_total,
        public string $status,
        #[Map(transform: [self::class, 'timestamp'])]
        public ?string $created_at,
    ) {}

    public static function dateString(DateTimeInterface $value): string
    {
        return $value->format('Y-m-d');
    }

    public static function timestamp(?DateTimeInterface $value): ?string
    {
        return $value === null ? null : \DateTimeImmutable::createFromInterface($value)
            ->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');
    }

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
