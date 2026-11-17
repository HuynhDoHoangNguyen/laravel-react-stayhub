<?php

namespace App\Repositories\Booking;

use App\Models\BookingService;
use Illuminate\Database\Eloquent\Collection;

class BookingServiceRepository
{
    /** @return Collection<int, BookingService> */
    public function listForBooking(int $bookingId): Collection
    {
        return BookingService::query()->where('booking_id', $bookingId)->get();
    }

    public function findForBookingAndService(int $bookingId, int $serviceId): ?BookingService
    {
        return BookingService::query()->where('booking_id', $bookingId)
            ->where('service_id', $serviceId)->lockForUpdate()->first();
    }

    public function findForBookingOrFail(int $bookingServiceId, int $bookingId): BookingService
    {
        return BookingService::query()->whereKey($bookingServiceId)
            ->where('booking_id', $bookingId)->lockForUpdate()->firstOrFail();
    }

    public function create(array $attributes): BookingService
    {
        return BookingService::query()->create($attributes);
    }

    public function update(BookingService $bookingService, array $attributes): BookingService
    {
        $bookingService->update($attributes);

        return $bookingService;
    }

    public function delete(BookingService $bookingService): void
    {
        $bookingService->delete();
    }

    public function totalForBooking(int $bookingId): string|int|float|null
    {
        return BookingService::query()->where('booking_id', $bookingId)->sum('amount');
    }
}
