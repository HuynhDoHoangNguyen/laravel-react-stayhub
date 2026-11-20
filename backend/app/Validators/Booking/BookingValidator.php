<?php

namespace App\Validators\Booking;

use App\DTOs\Booking\Requests\CustomerBookingQuery;
use App\DTOs\Booking\Requests\HostBookingQuery;
use App\DTOs\Booking\Requests\PreviewBookingRequest;
use App\DTOs\Booking\Requests\RejectBookingRequest;
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
            'room_id' => ['required', 'integer', 'min:1'],
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
            'room_id' => ['required', 'integer', 'min:1'],
            'check_in_date' => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:today'],
            'check_out_date' => ['required', 'date', 'date_format:Y-m-d', 'after:check_in_date'],
            'guest_count' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:1000'],
        ])->validate();

        return StoreBookingRequest::fromArray($validated);
    }

    /**
     * Validate raw input and build RejectBookingRequest DTO.
     *
     * @param array<string, mixed> $input
     */
    public function validateReject(array $input): RejectBookingRequest
    {
        $validated = $this->validation->make($input, [
            'reason' => ['nullable', 'string', 'max:500'],
        ])->validate();

        return RejectBookingRequest::fromArray($validated);
    }

    /**
     * Validate raw customer query and build CustomerBookingQuery DTO.
     *
     * @param array<string, mixed> $query
     */
    public function validateCustomerQuery(array $query): CustomerBookingQuery
    {
        $validated = $this->validation->make($query, [
            'status' => ['nullable', 'string'],
            'per_page' => ['nullable'],
            'page' => ['nullable'],
        ])->validate();

        return CustomerBookingQuery::fromArray($validated);
    }

    /**
     * Validate raw host query and build HostBookingQuery DTO.
     *
     * @param array<string, mixed> $query
     */
    public function validateHostQuery(array $query): HostBookingQuery
    {
        $validated = $this->validation->make($query, [
            'status' => ['nullable', 'string'],
            'property_id' => ['nullable'],
            'start_date' => ['nullable'],
            'end_date' => ['nullable'],
            'keyword' => ['nullable'],
            'per_page' => ['nullable'],
            'page' => ['nullable'],
        ])->validate();

        return HostBookingQuery::fromArray($validated);
    }
}
