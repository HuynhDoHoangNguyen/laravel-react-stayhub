<?php

namespace App\Validators\Booking;

use App\DTOs\Booking\Requests\AddBookingServiceRequest;
use App\DTOs\Booking\Requests\HostServiceQuery;
use App\DTOs\Booking\Requests\SetServiceStatusRequest;
use App\DTOs\Booking\Requests\StoreServiceRequest;
use App\DTOs\Booking\Requests\UpdateBookingServiceRequest;
use App\DTOs\Booking\Requests\UpdateServiceRequest;
use Illuminate\Contracts\Validation\Factory;

class ServiceValidator
{
    public function __construct(
        protected Factory $validation
    ) {}

    /**
     * Validate raw input and build StoreServiceRequest DTO.
     *
     * @param array<string, mixed> $input
     */
    public function validateStoreService(array $input): StoreServiceRequest
    {
        $validated = $this->validation->make($input, [
            'property_id' => ['required', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'unit' => ['required', 'string', 'max:50'],
            'price' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'status' => ['nullable', 'boolean'],
        ])->validate();

        return StoreServiceRequest::fromArray($validated);
    }

    /**
     * Validate raw input and build UpdateServiceRequest DTO.
     *
     * @param array<string, mixed> $input
     */
    public function validateUpdateService(array $input): UpdateServiceRequest
    {
        $validated = $this->validation->make($input, [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'unit' => ['required', 'string', 'max:50'],
            'price' => ['required', 'numeric', 'min:0', 'max:100000000'],
        ])->validate();

        return UpdateServiceRequest::fromArray($validated);
    }

    /**
     * Validate raw input and build SetServiceStatusRequest DTO.
     *
     * @param array<string, mixed> $input
     */
    public function validateSetStatus(array $input): SetServiceStatusRequest
    {
        $validated = $this->validation->make($input, [
            'status' => ['required', 'boolean'],
        ])->validate();

        return SetServiceStatusRequest::fromArray($validated);
    }

    /**
     * Validate raw input and build AddBookingServiceRequest DTO.
     *
     * @param array<string, mixed> $input
     */
    public function validateAddBookingService(array $input): AddBookingServiceRequest
    {
        $validated = $this->validation->make($input, [
            'service_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'numeric', 'min:0.01', 'max:9999.99'],
        ])->validate();

        return AddBookingServiceRequest::fromArray($validated);
    }

    /**
     * Validate raw input and build UpdateBookingServiceRequest DTO.
     *
     * @param array<string, mixed> $input
     */
    public function validateUpdateBookingService(array $input): UpdateBookingServiceRequest
    {
        $validated = $this->validation->make($input, [
            'quantity' => ['required', 'numeric', 'min:0.01', 'max:9999.99'],
        ])->validate();

        return UpdateBookingServiceRequest::fromArray($validated);
    }

    /**
     * Validate raw query and build HostServiceQuery DTO.
     *
     * @param array<string, mixed> $query
     */
    public function validateServiceQuery(array $query): HostServiceQuery
    {
        $validated = $this->validation->make($query, [
            'property_id' => ['nullable'],
            'status' => ['nullable'],
            'keyword' => ['nullable'],
            'per_page' => ['nullable'],
            'page' => ['nullable'],
        ])->validate();

        return HostServiceQuery::fromArray($validated);
    }
}
