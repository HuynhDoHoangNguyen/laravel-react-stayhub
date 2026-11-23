<?php

namespace App\DTOs\Catalog\Requests;

final readonly class PublicRoomFilterQuery
{
    /** @param list<int> $roomTypeIds @param list<int> $amenityIds */
    public function __construct(
        public ?string $minPrice = null,
        public ?string $maxPrice = null,
        public ?int $guestCount = null,
        public array $roomTypeIds = [],
        public array $amenityIds = [],
        public string $sort = 'price_asc',
        public int $page = 1,
        public int $perPage = 12,
        public ?string $minRating = null,
        public ?int $propertyId = null,
        public ?string $maxPriceExclusive = null,
    ) {}
}
