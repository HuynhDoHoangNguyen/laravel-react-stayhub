<?php

namespace App\DTOs\Booking\Requests;

final readonly class SearchRoomsQuery
{
    /**
     * @param list<int>|null $amenityIds
     * @param list<int>|null $roomTypeIds
     */
    public function __construct(
        public string $checkInDate,
        public string $checkOutDate,
        public int $guestCount,
        public ?int $propertyId = null,
        public ?string $keyword = null,
        public ?int $roomTypeId = null,
        public ?string $minPrice = null,
        public ?string $maxPrice = null,
        public ?array $amenityIds = null,
        public ?string $sort = null,
        public ?int $perPage = null,
        public ?array $roomTypeIds = null,
        public ?float $minRating = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'check_in_date' => $this->checkInDate,
            'check_out_date' => $this->checkOutDate,
            'guest_count' => $this->guestCount,
            'property_id' => $this->propertyId,
            'keyword' => $this->keyword,
            'room_type_id' => $this->roomTypeId,
            'min_price' => $this->minPrice,
            'max_price' => $this->maxPrice,
            'amenity_ids' => $this->amenityIds,
            'sort' => $this->sort,
            'per_page' => $this->perPage,
            'room_type_ids' => $this->roomTypeIds,
            'min_rating' => $this->minRating,
        ];
    }
}
