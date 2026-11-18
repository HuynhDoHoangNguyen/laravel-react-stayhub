<?php

namespace App\Validators\Booking;

use App\DTOs\Booking\Requests\CheckAvailabilityQuery;
use App\DTOs\Booking\Requests\SearchRoomsQuery;
use App\Support\DecimalMoney;
use Illuminate\Contracts\Validation\Factory;

class RoomSearchValidator
{
    public function __construct(
        protected Factory $validation
    ) {}

    /**
     * Validate raw input and build SearchRoomsQuery DTO.
     *
     * @param array<string, mixed> $input
     */
    public function validateSearchRooms(array $input): SearchRoomsQuery
    {
        $validated = $this->validation->make($input, [
            'check_in_date' => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:today'],
            'check_out_date' => ['required', 'date', 'date_format:Y-m-d', 'after:check_in_date'],
            'guest_count' => ['required', 'integer', 'min:1'],
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
            'keyword' => ['nullable', 'string'],
            'room_type_id' => ['nullable', 'integer', 'exists:room_types,id'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => [
                'nullable',
                'numeric',
                'min:0',
                function ($attribute, $value, $fail) use ($input) {
                    $minPrice = $input['min_price'] ?? null;
                    if ($minPrice !== null && $minPrice !== '' && DecimalMoney::compare($value, $minPrice) < 0) {
                        $fail('The max price field must be greater than or equal to min price.');
                    }
                },
            ],
            'amenity_ids' => ['nullable', 'array'],
            'amenity_ids.*' => ['integer', 'exists:amenities,id'],
            'sort' => ['nullable', 'string', 'in:price_asc,price_desc,newest'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ])->validate();

        $minPrice = isset($validated['min_price']) && $validated['min_price'] !== ''
            ? DecimalMoney::queryDecimal($validated['min_price'], 'min_price')
            : null;

        $maxPrice = isset($validated['max_price']) && $validated['max_price'] !== ''
            ? DecimalMoney::queryDecimal($validated['max_price'], 'max_price')
            : null;

        $amenityIds = isset($validated['amenity_ids']) && is_array($validated['amenity_ids'])
            ? array_values(array_map('intval', $validated['amenity_ids']))
            : null;

        return new SearchRoomsQuery(
            checkInDate: (string) $validated['check_in_date'],
            checkOutDate: (string) $validated['check_out_date'],
            guestCount: (int) $validated['guest_count'],
            propertyId: isset($validated['property_id']) && $validated['property_id'] !== '' ? (int) $validated['property_id'] : null,
            keyword: isset($validated['keyword']) && $validated['keyword'] !== '' ? (string) $validated['keyword'] : null,
            roomTypeId: isset($validated['room_type_id']) && $validated['room_type_id'] !== '' ? (int) $validated['room_type_id'] : null,
            minPrice: $minPrice,
            maxPrice: $maxPrice,
            amenityIds: $amenityIds,
            sort: isset($validated['sort']) && $validated['sort'] !== '' ? (string) $validated['sort'] : null,
            perPage: isset($validated['per_page']) && $validated['per_page'] !== '' ? (int) $validated['per_page'] : null,
        );
    }

    /**
     * Validate raw input and build CheckAvailabilityQuery DTO.
     *
     * @param array<string, mixed> $input
     */
    public function validateCheckAvailability(array $input): CheckAvailabilityQuery
    {
        $validated = $this->validation->make($input, [
            'check_in_date' => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:today'],
            'check_out_date' => ['required', 'date', 'date_format:Y-m-d', 'after:check_in_date'],
            'guest_count' => ['required', 'integer', 'min:1'],
        ])->validate();

        return new CheckAvailabilityQuery(
            checkInDate: (string) $validated['check_in_date'],
            checkOutDate: (string) $validated['check_out_date'],
            guestCount: (int) $validated['guest_count'],
        );
    }
}
