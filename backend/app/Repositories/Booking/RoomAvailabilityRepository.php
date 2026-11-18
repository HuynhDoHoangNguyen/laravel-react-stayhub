<?php

namespace App\Repositories\Booking;

use App\Enums\BookingStatus;
use App\Enums\MaintenanceStatus;
use App\Enums\PropertyStatus;
use App\Enums\RoomStatus;
use App\Models\Booking;
use App\Models\Maintenance;
use App\Models\Property;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class RoomAvailabilityRepository
{
    public function propertyForRoom(Room $room): ?Property
    {
        return $room->relationLoaded('property')
            ? $room->getRelation('property')
            : $room->property()->first();
    }

    public function hasBlockingMaintenanceOverlap(int $roomId, string $checkIn, string $checkOut): bool
    {
        return Maintenance::query()
            ->where('room_id', $roomId)
            ->whereNotIn('status', [MaintenanceStatus::CANCELLED->value, MaintenanceStatus::COMPLETED->value])
            ->where('start_date', '<', $checkOut)
            ->where('end_date', '>', $checkIn)
            ->exists();
    }

    public function hasHoldingBookingOverlap(int $roomId, string $checkIn, string $checkOut, ?int $ignoreBookingId = null): bool
    {
        return $this->holdingBookings($roomId, $checkIn, $checkOut, $ignoreBookingId)->exists();
    }

    /** @return Collection<int, Booking> */
    public function blockingBookings(Room $room, string $fromDate, string $toDate): Collection
    {
        return $this->holdingBookings($room->id, $fromDate, $toDate)
            ->get(['check_in_date', 'check_out_date']);
    }

    /** @return Collection<int, Maintenance> */
    public function blockingMaintenances(Room $room, string $fromDate, string $toDate): Collection
    {
        return Maintenance::query()
            ->where('room_id', $room->id)
            ->whereNotIn('status', [MaintenanceStatus::CANCELLED->value, MaintenanceStatus::COMPLETED->value])
            ->where('start_date', '<', $toDate)
            ->where('end_date', '>', $fromDate)
            ->get(['start_date', 'end_date']);
    }

    public function searchAvailableRooms(array $filters, string $sort, int $perPage): LengthAwarePaginator
    {
        $checkIn = Carbon::parse($filters['check_in_date'])->format('Y-m-d');
        $checkOut = Carbon::parse($filters['check_out_date'])->format('Y-m-d');
        $guestCount = (int) $filters['guest_count'];
        $ignoreBookingId = $filters['ignore_booking_id'] ?? null;

        $query = Room::query()
            ->where('status', RoomStatus::ACTIVE->value)
            ->where('capacity', '>=', $guestCount)
            ->whereHas('property', function (Builder $query): void {
                $query->where('status', PropertyStatus::ACTIVE->value);
            })
            ->whereDoesntHave('maintenances', function (Builder $query) use ($checkIn, $checkOut): void {
                $query->whereNotIn('status', [MaintenanceStatus::CANCELLED->value, MaintenanceStatus::COMPLETED->value])
                    ->where('start_date', '<', $checkOut)
                    ->where('end_date', '>', $checkIn);
            })
            ->whereDoesntHave('bookings', function (Builder $query) use ($checkIn, $checkOut, $ignoreBookingId): void {
                $query->holdingRoom()->overlapping($checkIn, $checkOut);
                if ($ignoreBookingId !== null) {
                    $query->where('id', '!=', $ignoreBookingId);
                }
            });

        if (! empty($filters['property_id'])) {
            $query->where('property_id', $filters['property_id']);
        }

        if (! empty($filters['keyword'])) {
            $keyword = $filters['keyword'];
            $query->whereHas('property', function (Builder $query) use ($keyword): void {
                $query->where(function (Builder $query) use ($keyword): void {
                    $query->where('name', 'like', "%{$keyword}%")
                        ->orWhere('address', 'like', "%{$keyword}%");
                });
            });
        }

        if (! empty($filters['room_type_id'])) {
            $query->where('room_type_id', $filters['room_type_id']);
        }

        if (isset($filters['min_price']) && $filters['min_price'] !== '') {
            $query->where('price_per_night', '>=', $filters['min_price']);
        }

        if (isset($filters['max_price']) && $filters['max_price'] !== '') {
            $query->where('price_per_night', '<=', $filters['max_price']);
        }

        if (! empty($filters['amenity_ids']) && is_array($filters['amenity_ids'])) {
            $amenityIds = array_filter(array_map('intval', $filters['amenity_ids']));
            if (! empty($amenityIds)) {
                $query->whereHas('amenities', function (Builder $query) use ($amenityIds): void {
                    $query->whereIn('amenities.id', $amenityIds);
                }, '=', count($amenityIds));
            }
        }

        match ($sort) {
            'price_desc' => $query->orderBy('price_per_night', 'desc'),
            'newest' => $query->orderBy('created_at', 'desc'),
            default => $query->orderBy('price_per_night', 'asc'),
        };

        return $query->with(['property', 'roomType', 'amenities', 'images'])->paginate($perPage);
    }

    private function holdingBookings(int $roomId, string $checkIn, string $checkOut, ?int $ignoreBookingId = null): Builder
    {
        $query = Booking::query()
            ->where('room_id', $roomId)
            ->holdingRoom()
            ->overlapping($checkIn, $checkOut);

        if ($ignoreBookingId !== null) {
            $query->where('id', '!=', $ignoreBookingId);
        }

        return $query;
    }
}
