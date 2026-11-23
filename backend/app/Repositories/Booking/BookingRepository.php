<?php

namespace App\Repositories\Booking;

use App\Models\Booking;
use App\Models\Room;
use App\Support\DecimalMoney;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class BookingRepository
{
    public function findRoomOrFail(int $roomId): Room
    {
        return Room::query()->findOrFail($roomId);
    }

    public function findRoom(int $roomId): ?Room
    {
        return Room::query()->find($roomId);
    }

    public function findRoomForUpdate(int $roomId): ?Room
    {
        return Room::query()
            ->whereKey($roomId)
            ->lockForUpdate()
            ->first();
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

    public function listForHost(int $hostId, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Booking::query()
            ->forHost($hostId)
            ->with(['customer', 'room.property', 'bookingServices'])
            ->orderBy('created_at', 'desc');

        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }
        if (isset($filters['property_id']) && $filters['property_id'] !== '') {
            $propertyId = $filters['property_id'];
            $query->whereHas('room', fn (Builder $roomQuery) => $roomQuery->where('property_id', $propertyId));
        }
        if (isset($filters['start_date']) && $filters['start_date'] !== '') {
            $query->where('check_in_date', '>=', $filters['start_date']);
        }
        if (isset($filters['end_date']) && $filters['end_date'] !== '') {
            $query->where('check_out_date', '<=', $filters['end_date']);
        }
        if (isset($filters['keyword']) && $filters['keyword'] !== '') {
            $keyword = $filters['keyword'];
            $query->where(function (Builder $subQuery) use ($keyword): void {
                $subQuery->where('booking_code', 'like', "%{$keyword}%")
                    ->orWhereHas('customer', fn (Builder $customerQuery) => $customerQuery
                        ->where('name', 'like', "%{$keyword}%")
                        ->orWhere('email', 'like', "%{$keyword}%"));
            });
        }

        return $query->paginate($perPage);
    }

    public function findForHost(int $bookingId, int $hostId): Booking
    {
        return Booking::query()
            ->forHost($hostId)
            ->with(['customer', 'room.property', 'bookingServices'])
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

    public function loadForHost(Booking $booking): Booking
    {
        return $booking->loadMissing(['customer', 'room.property', 'bookingServices']);
    }

    public function loadForCustomer(Booking $booking): Booking
    {
        return $booking->loadMissing(['room.property', 'bookingServices']);
    }

    public function loadForCustomerCreation(Booking $booking): Booking
    {
        return $booking->loadMissing(['room.property']);
    }

    public function calculateServicesTotal(Booking $booking): string
    {
        if (! $booking->relationLoaded('bookingServices')) {
            $booking->loadMissing('bookingServices');
        }

        return DecimalMoney::sum($booking->bookingServices->pluck('amount'));
    }

    public function attachServicesTotal(Booking $booking): Booking
    {
        $booking->services_total = $this->calculateServicesTotal($booking);

        return $booking;
    }
}
