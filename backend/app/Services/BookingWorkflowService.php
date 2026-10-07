<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Exceptions\BookingCancellationNotAllowedException;
use App\Exceptions\InvalidBookingTransitionException;
use App\Exceptions\RoomNotAvailableException;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BookingWorkflowService
{
    public function __construct(
        protected AvailabilityService $availabilityService
    ) {}

    /**
     * Confirm a pending booking (PENDING -> CONFIRMED).
     *
     * @throws InvalidBookingTransitionException|RoomNotAvailableException
     */
    public function confirm(Booking $booking): Booking
    {
        return DB::transaction(function () use ($booking) {
            $currentBooking = Booking::query()
                ->where('id', $booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            $currentStatus = $currentBooking->status instanceof BookingStatus
                ? $currentBooking->status
                : BookingStatus::from($currentBooking->status);

            if (! $currentStatus->canTransitionTo(BookingStatus::CONFIRMED)) {
                throw new InvalidBookingTransitionException($currentStatus, BookingStatus::CONFIRMED);
            }

            // Re-check room availability (excluding current booking)
            $checkResult = $this->availabilityService->check(
                $currentBooking->room,
                $currentBooking->check_in_date,
                $currentBooking->check_out_date,
                $currentBooking->guest_count,
                ignoreBookingId: $currentBooking->id
            );

            if (! $checkResult['available']) {
                throw new RoomNotAvailableException(
                    'Phòng không còn khả dụng để xác nhận đặt phòng.',
                    $checkResult['reasons']
                );
            }

            $currentBooking->status = BookingStatus::CONFIRMED;
            $currentBooking->save();

            return $currentBooking;
        });
    }

    /**
     * Reject a pending booking (PENDING -> REJECTED).
     *
     * @throws InvalidBookingTransitionException
     */
    public function reject(Booking $booking, ?string $reason = null): Booking
    {
        return DB::transaction(function () use ($booking, $reason) {
            $currentBooking = Booking::query()
                ->where('id', $booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            $currentStatus = $currentBooking->status instanceof BookingStatus
                ? $currentBooking->status
                : BookingStatus::from($currentBooking->status);

            if (! $currentStatus->canTransitionTo(BookingStatus::REJECTED)) {
                throw new InvalidBookingTransitionException($currentStatus, BookingStatus::REJECTED);
            }

            $currentBooking->status = BookingStatus::REJECTED;
            if ($reason !== null && trim($reason) !== '') {
                $currentBooking->note = ($currentBooking->note ? $currentBooking->note . "\nLý do từ chối: " : 'Lý do từ chối: ') . trim($reason);
            }
            $currentBooking->save();

            return $currentBooking;
        });
    }

    /**
     * Check-in a confirmed booking (CONFIRMED -> CHECKED_IN).
     *
     * @throws InvalidBookingTransitionException
     */
    public function checkIn(Booking $booking): Booking
    {
        return DB::transaction(function () use ($booking) {
            $currentBooking = Booking::query()
                ->where('id', $booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            $currentStatus = $currentBooking->status instanceof BookingStatus
                ? $currentBooking->status
                : BookingStatus::from($currentBooking->status);

            if (! $currentStatus->canTransitionTo(BookingStatus::CHECKED_IN)) {
                throw new InvalidBookingTransitionException($currentStatus, BookingStatus::CHECKED_IN);
            }

            $currentBooking->status = BookingStatus::CHECKED_IN;
            $currentBooking->save();

            return $currentBooking;
        });
    }

    /**
     * Check-out a checked-in booking (CHECKED_IN -> COMPLETED).
     * Note: NO Invoice is created here.
     *
     * @throws InvalidBookingTransitionException
     */
    public function checkOut(Booking $booking): Booking
    {
        return DB::transaction(function () use ($booking) {
            $currentBooking = Booking::query()
                ->where('id', $booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            $currentStatus = $currentBooking->status instanceof BookingStatus
                ? $currentBooking->status
                : BookingStatus::from($currentBooking->status);

            if (! $currentStatus->canTransitionTo(BookingStatus::COMPLETED)) {
                throw new InvalidBookingTransitionException($currentStatus, BookingStatus::COMPLETED);
            }

            $currentBooking->status = BookingStatus::COMPLETED;
            $currentBooking->save();

            return $currentBooking;
        });
    }

    /**
     * Cancel a booking.
     *
     * @throws InvalidBookingTransitionException|BookingCancellationNotAllowedException
     */
    public function cancel(Booking $booking, User $user, ?BookingService $bookingService = null): Booking
    {
        return DB::transaction(function () use ($booking, $user, $bookingService) {
            $currentBooking = Booking::query()
                ->where('id', $booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            $currentStatus = $currentBooking->status instanceof BookingStatus
                ? $currentBooking->status
                : BookingStatus::from($currentBooking->status);

            if (! $currentStatus->canTransitionTo(BookingStatus::CANCELLED)) {
                throw new InvalidBookingTransitionException($currentStatus, BookingStatus::CANCELLED);
            }

            $service = $bookingService ?? app(BookingService::class);
            $check = $service->cancellationCheck($currentBooking);

            if (! $check['allowed']) {
                throw new BookingCancellationNotAllowedException($check['reason'] ?? 'Không thể hủy đặt phòng.');
            }

            $currentBooking->status = BookingStatus::CANCELLED;
            $currentBooking->save();

            return $currentBooking;
        });
    }
}
