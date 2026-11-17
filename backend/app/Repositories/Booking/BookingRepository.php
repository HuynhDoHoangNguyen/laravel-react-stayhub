<?php

namespace App\Repositories\Booking;

use App\Models\Booking;
use App\Models\Room;

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
}
