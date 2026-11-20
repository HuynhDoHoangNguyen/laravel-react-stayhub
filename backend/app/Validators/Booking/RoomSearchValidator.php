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
            'property_id' => ['nullable', 'integer', 'min:1'],
            'keyword' => ['nullable', 'string'],
            'room_type_id' => ['nullable', 'integer', 'min:1'],
            'room_type_ids' => ['nullable', 'array'],
            'room_type_ids.*' => ['integer', 'min:1'],
            'min_rating' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'min_price' => [
                'nullable',
                'numeric',
                'min:0',
                function ($attribute, $value, $fail) {
                    if (is_scalar($value) && is_numeric($value)) {
                        if (preg_match('/[eE]/', (string) $value) || ! preg_match('/^[+-]?\d+(\.\d{1,2})?$/', (string) $value)) {
                            $fail("The {$attribute} field format is invalid.");
                            return;
                        }
                        try {
                            if (DecimalMoney::compare((string) $value, DecimalMoney::MAX_MONEY) > 0) {
                                $fail("The {$attribute} must not be greater than " . DecimalMoney::MAX_MONEY . '.');
                            }
                        } catch (\Throwable) {
                            $fail("The {$attribute} field format is invalid.");
                        }
                    }
                },
            ],
            'max_price' => [
                'nullable',
                'numeric',
                'min:0',
                function ($attribute, $value, $fail) use ($input) {
                    if (is_scalar($value) && is_numeric($value)) {
                        if (preg_match('/[eE]/', (string) $value) || ! preg_match('/^[+-]?\d+(\.\d{1,2})?$/', (string) $value)) {
                            $fail("The {$attribute} field format is invalid.");
                            return;
                        }
                        try {
                            if (DecimalMoney::compare((string) $value, DecimalMoney::MAX_MONEY) > 0) {
                                $fail("The {$attribute} must not be greater than " . DecimalMoney::MAX_MONEY . '.');
                                return;
                            }
                        } catch (\Throwable) {
                            $fail("The {$attribute} field format is invalid.");
                            return;
                        }
                    }
                    $minPrice = $input['min_price'] ?? null;
                    if (
                        $minPrice !== null && $minPrice !== ''
                        && is_scalar($minPrice) && is_numeric($minPrice)
                        && is_scalar($value) && is_numeric($value)
                    ) {
                        try {
                            if (DecimalMoney::compare((string) $value, (string) $minPrice) < 0) {
                                $fail('The max price field must be greater than or equal to min price.');
                            }
                        } catch (\Throwable) {
                            $fail("The {$attribute} field format is invalid.");
                        }
                    }
                },
            ],
            'amenity_ids' => ['nullable', 'array'],
            'amenity_ids.*' => ['integer', 'min:1'],
            'sort' => ['nullable', 'string', 'in:price_asc,price_desc,newest'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ])->validate();

        try {
            $minPrice = isset($validated['min_price']) && $validated['min_price'] !== ''
                ? DecimalMoney::queryDecimal($validated['min_price'], 'min_price')
                : null;

            $maxPrice = isset($validated['max_price']) && $validated['max_price'] !== ''
                ? DecimalMoney::queryDecimal($validated['max_price'], 'max_price')
                : null;
        } catch (\Throwable) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'min_price' => ['Định dạng giá tìm kiếm không hợp lệ.'],
            ]);
        }

        $amenityIds = isset($validated['amenity_ids']) && is_array($validated['amenity_ids'])
            ? array_values(array_unique(array_map('intval', $validated['amenity_ids'])))
            : null;

        $roomTypeIds = isset($validated['room_type_ids']) && is_array($validated['room_type_ids'])
            ? array_values(array_unique(array_map('intval', $validated['room_type_ids'])))
            : null;

        $minRating = isset($validated['min_rating']) && $validated['min_rating'] !== ''
            ? (float) $validated['min_rating']
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
            roomTypeIds: $roomTypeIds,
            minRating: $minRating,
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
