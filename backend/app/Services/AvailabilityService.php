<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\MaintenanceStatus;
use App\Enums\PropertyStatus;
use App\Enums\RoomStatus;
use App\Models\Booking;
use App\Models\Maintenance;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class AvailabilityService
{
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
        $property = $room->relationLoaded('property') ? $room->property : $room->property()->first();
        $propertyStatus = $property->status instanceof PropertyStatus ? $property->status->value : (string) $property->status;
        if ($propertyStatus !== PropertyStatus::ACTIVE->value) {
            $reasons[] = 'PROPERTY_NOT_ACTIVE';
        }

        // 3. Capacity
        if ($guestCount > $room->capacity) {
            $reasons[] = 'CAPACITY_EXCEEDED';
        }

        // 4. Maintenance Overlap
        $hasMaintenanceOverlap = Maintenance::query()
            ->where('room_id', $room->id)
            ->whereNotIn('status', [MaintenanceStatus::CANCELLED->value, MaintenanceStatus::COMPLETED->value])
            ->where('start_date', '<', $out)
            ->where('end_date', '>', $in)
            ->exists();

        if ($hasMaintenanceOverlap) {
            $reasons[] = 'MAINTENANCE_OVERLAP';
        }

        // 5. Booking Overlap
        $bookingQuery = Booking::query()
            ->where('room_id', $room->id)
            ->holdingRoom()
            ->overlapping($in, $out);

        if ($ignoreBookingId !== null) {
            $bookingQuery->where('id', '!=', $ignoreBookingId);
        }

        if ($bookingQuery->exists()) {
            $reasons[] = 'BOOKING_OVERLAP';
        }

        return [
            'available' => empty($reasons),
            'reasons' => $reasons,
        ];
    }

    /**
     * Get Query Builder for available rooms matching date & guest filters.
     * Uses database SQL constraints only.
     */
    public function availableRooms(array $filters): Builder
    {
        $in = Carbon::parse($filters['check_in_date'])->format('Y-m-d');
        $out = Carbon::parse($filters['check_out_date'])->format('Y-m-d');
        $guestCount = (int) $filters['guest_count'];
        $ignoreBookingId = $filters['ignore_booking_id'] ?? null;

        $query = Room::query()
            ->where('status', RoomStatus::ACTIVE->value)
            ->where('capacity', '>=', $guestCount)
            ->whereHas('property', function (Builder $q) {
                $q->where('status', PropertyStatus::ACTIVE->value);
            })
            ->whereDoesntHave('maintenances', function (Builder $q) use ($in, $out) {
                $q->whereNotIn('status', [MaintenanceStatus::CANCELLED->value, MaintenanceStatus::COMPLETED->value])
                    ->where('start_date', '<', $out)
                    ->where('end_date', '>', $in);
            })
            ->whereDoesntHave('bookings', function (Builder $q) use ($in, $out, $ignoreBookingId) {
                $q->holdingRoom()->overlapping($in, $out);
                if ($ignoreBookingId !== null) {
                    $q->where('id', '!=', $ignoreBookingId);
                }
            });

        return $query;
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
        $bookings = Booking::query()
            ->where('room_id', $room->id)
            ->holdingRoom()
            ->overlapping($from, $to)
            ->get(['check_in_date', 'check_out_date']);

        foreach ($bookings as $booking) {
            $ranges[] = [
                'start_date' => Carbon::parse($booking->check_in_date)->format('Y-m-d'),
                'end_date' => Carbon::parse($booking->check_out_date)->format('Y-m-d'),
                'type' => 'BOOKING',
            ];
        }

        // Maintenances
        $maintenances = Maintenance::query()
            ->where('room_id', $room->id)
            ->whereNotIn('status', [MaintenanceStatus::CANCELLED->value, MaintenanceStatus::COMPLETED->value])
            ->where('start_date', '<', $to)
            ->where('end_date', '>', $from)
            ->get(['start_date', 'end_date']);

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
