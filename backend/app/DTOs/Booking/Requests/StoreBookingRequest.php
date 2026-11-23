<?php

namespace App\DTOs\Booking\Requests;

class StoreBookingRequest
{
    public function __construct(
        public readonly int $roomId,
        public readonly string $checkInDate,
        public readonly string $checkOutDate,
        public readonly int $guestCount,
        public readonly ?string $note = null
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            roomId: (int) ($data['room_id'] ?? $data['roomId']),
            checkInDate: (string) ($data['check_in_date'] ?? $data['checkInDate']),
            checkOutDate: (string) ($data['check_out_date'] ?? $data['checkOutDate']),
            guestCount: (int) ($data['guest_count'] ?? $data['guestCount']),
            note: isset($data['note']) && $data['note'] !== '' ? (string) $data['note'] : null
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'room_id' => $this->roomId,
            'check_in_date' => $this->checkInDate,
            'check_out_date' => $this->checkOutDate,
            'guest_count' => $this->guestCount,
            'note' => $this->note,
        ];
    }
}
