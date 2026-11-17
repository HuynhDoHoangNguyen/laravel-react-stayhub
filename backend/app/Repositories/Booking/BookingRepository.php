<?php

namespace App\Repositories\Booking;

use App\Models\Booking;
use App\Models\Room;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BookingRepository
{
    public function findRoomOrFail(int $roomId): Room
    {
        return Room::query()->findOrFail($roomId);
    }

    public function lockRoomForUpdate(int $roomId): Room
    {
        return Room::query()
            ->whereKey($roomId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    public function bookingCodeExists(string $bookingCode): bool
    {
        return Booking::query()->where('booking_code', $bookingCode)->exists();
    }

    public function create(array $attributes): Booking
    {
        return Booking::query()->create($attributes);
    }

    public function listForCustomer(int $customerId, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Booking::query()
            ->forCustomer($customerId)
            ->with(['room.property', 'bookingServices'])
            ->orderBy('created_at', 'desc');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($perPage);
    }

    public function findForCustomer(int $bookingId, int $customerId): Booking
    {
        return Booking::query()
            ->forCustomer($customerId)
            ->with(['room.property', 'bookingServices'])
            ->findOrFail($bookingId);
    }

    public function lockForUpdate(int $bookingId): Booking
    {
        return Booking::query()
            ->whereKey($bookingId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    public function save(Booking $booking): bool
    {
        return $booking->save();
    }
}
