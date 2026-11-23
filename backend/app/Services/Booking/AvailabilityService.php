<?php

namespace App\Services\Booking;

use App\DTOs\Booking\Requests\CheckAvailabilityQuery;
use App\Enums\PropertyStatus;
use App\Enums\RoomStatus;
use App\Models\Room;
use App\Repositories\Booking\RoomAvailabilityRepository;
use Carbon\Carbon;

class AvailabilityService
{
    public function __construct(private readonly RoomAvailabilityRepository $repository) {}

    /**
     * Check availability of a specific room using CheckAvailabilityQuery DTO.
     *
     * @return array{available: bool, reasons: string[]}
     */
    public function checkRoom(Room $room, CheckAvailabilityQuery $input): array
    {
        return $this->check(
            $room,
            $input->checkInDate,
            $input->checkOutDate,
            $input->guestCount
        );
    }

    /**
     * Check availability of a specific room for given dates and guest count.
     *
     * @return array{available: bool, reasons: string[]}
     */
    public function check(
        Room $room,
        mixed $checkInDate,
        mixed $checkOutDate,
        int $guestCount,
        ?int $ignoreBookingId = null
    ): array {
        $reasons = [];

        $in = $checkInDate instanceof \DateTimeInterface
            ? $checkInDate->format('Y-m-d')
            : Carbon::parse($checkInDate)->format('Y-m-d');

        $out = $checkOutDate instanceof \DateTimeInterface
            ? $checkOutDate->format('Y-m-d')
            : Carbon::parse($checkOutDate)->format('Y-m-d');

        // 1. Room Status
        $roomStatus = $room->status instanceof RoomStatus ? $room->status->value : (string) $room->status;
        if ($roomStatus !== RoomStatus::ACTIVE->value) {
            $reasons[] = 'ROOM_NOT_ACTIVE';
        }

        // 2. Property Status
        $property = $this->repository->propertyForRoom($room);
        $propertyStatus = $property->status instanceof PropertyStatus ? $property->status->value : (string) $property->status;
        if ($propertyStatus !== PropertyStatus::ACTIVE->value) {
            $reasons[] = 'PROPERTY_NOT_ACTIVE';
        }

        // 3. Capacity
        if ($guestCount > $room->capacity) {
            $reasons[] = 'CAPACITY_EXCEEDED';
        }

        // 4. Maintenance Overlap
        if ($this->hasMaintenanceBlock($room, $in, $out)) {
            $reasons[] = 'MAINTENANCE_OVERLAP';
        }

        // 5. Booking Overlap
        if ($this->repository->hasHoldingBookingOverlap($room->id, $in, $out, $ignoreBookingId)) {
            $reasons[] = 'BOOKING_OVERLAP';
        }

        return [
            'available' => empty($reasons),
            'reasons' => $reasons,
        ];
    }

    public function hasMaintenanceBlock(Room $room, string $checkIn, string $checkOut): bool
    {
        return $this->repository->hasBlockingMaintenanceOverlap($room->id, $checkIn, $checkOut);
    }

    /**
     * Get blocked date ranges for a room (bookings and maintenances).
     *
     * @return array<int, array{start_date: string, end_date: string, type: string}>
     */
    public function blockedRanges(Room $room, string $fromDate, string $toDate): array
    {
        $from = Carbon::parse($fromDate)->format('Y-m-d');
        $to = Carbon::parse($toDate)->format('Y-m-d');

        $ranges = [];

        // Bookings
        $bookings = $this->repository->blockingBookings($room, $from, $to);

        foreach ($bookings as $booking) {
            $ranges[] = [
                'start_date' => Carbon::parse($booking->check_in_date)->format('Y-m-d'),
                'end_date' => Carbon::parse($booking->check_out_date)->format('Y-m-d'),
                'type' => 'BOOKING',
            ];
        }

        // Maintenances
        $maintenances = $this->repository->blockingMaintenances($room, $from, $to);

        foreach ($maintenances as $m) {
            $ranges[] = [
                'start_date' => Carbon::parse($m->start_date)->format('Y-m-d'),
                'end_date' => Carbon::parse($m->end_date)->format('Y-m-d'),
                'type' => 'MAINTENANCE',
            ];
        }

        return $ranges;
    }
}
