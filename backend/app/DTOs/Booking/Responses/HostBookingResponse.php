<?php

namespace App\DTOs\Booking\Responses;

use JsonSerializable;

final readonly class HostBookingResponse implements JsonSerializable
{
    /**
     * @param array<string, mixed>|null $customer
     * @param array<string, mixed>|null $room
     * @param list<BookingServiceResponse>|null $bookingServices
     */
    public function __construct(
        public int $id,
        public string $bookingCode,
        public int $customerId,
        public int $roomId,
        public string $checkInDate,
        public string $checkOutDate,
        public int $guestCount,
        public int $numberOfNights,
        public ?string $pricePerNight,
        public ?string $roomTotal,
        public string $status,
        public string $statusLabel,
        public ?string $note,
        public ?string $servicesTotal = null,
        public ?array $customer = null,
        public ?array $room = null,
        public ?array $bookingServices = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        $data = [
            'id' => $this->id,
            'booking_code' => $this->bookingCode,
            'customer_id' => $this->customerId,
            'room_id' => $this->roomId,
            'check_in_date' => $this->checkInDate,
            'check_out_date' => $this->checkOutDate,
            'guest_count' => $this->guestCount,
            'number_of_nights' => $this->numberOfNights,
            'price_per_night' => $this->pricePerNight,
            'room_total' => $this->roomTotal,
            'status' => $this->status,
            'status_label' => $this->statusLabel,
            'note' => $this->note,
        ];

        if ($this->servicesTotal !== null) {
            $data['services_total'] = $this->servicesTotal;
        }

        if ($this->customer !== null) {
            $data['customer'] = $this->customer;
        }

        if ($this->room !== null) {
            $data['room'] = $this->room;
        }

        if ($this->bookingServices !== null) {
            $data['booking_services'] = $this->bookingServices;
        }

        $data['created_at'] = $this->createdAt;
        $data['updated_at'] = $this->updatedAt;

        return $data;
    }
}
