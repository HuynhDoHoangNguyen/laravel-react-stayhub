<?php

namespace App\Validators\Booking;

use App\DTOs\Booking\Requests\PreviewBookingRequest;
use App\DTOs\Booking\Requests\StoreBookingRequest;
use Illuminate\Contracts\Validation\Factory;

class BookingValidator
{
    public function __construct(
        protected Factory $validation
    ) {}

    /**
     * Validate raw input and build PreviewBookingRequest DTO.
     *
     * @param array<string, mixed> $input
     */
    public function validatePreview(array $input): PreviewBookingRequest
    {
        $validated = $this->validation->make($input, [
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'check_in_date' => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:today'],
            'check_out_date' => ['required', 'date', 'date_format:Y-m-d', 'after:check_in_date'],
            'guest_count' => ['required', 'integer', 'min:1'],
        ])->validate();

        return PreviewBookingRequest::fromArray($validated);
    }

    /**
     * Validate raw input and build StoreBookingRequest DTO.
     *
     * @param array<string, mixed> $input
     */
    public function validateStore(array $input): StoreBookingRequest
    {
        $validated = $this->validation->make($input, [
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'check_in_date' => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:today'],
            'check_out_date' => ['required', 'date', 'date_format:Y-m-d', 'after:check_in_date'],
            'guest_count' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:1000'],
        ])->validate();

        return StoreBookingRequest::fromArray($validated);
    }
}
