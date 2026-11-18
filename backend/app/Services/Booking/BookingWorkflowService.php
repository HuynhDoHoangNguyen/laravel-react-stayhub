<?php

namespace App\Services\Booking;

use App\Enums\BookingStatus;
use App\Exceptions\BookingCancellationNotAllowedException;
use App\Exceptions\InvalidBookingTransitionException;
use App\Exceptions\RoomNotAvailableException;
use App\Models\Booking;
use App\Models\User;
use App\Repositories\Booking\BookingRepository;
use App\Validators\Booking\BookingCancellationValidator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;

class BookingWorkflowService
{
    public function __construct(
        protected AvailabilityService $availabilityService,
        protected BookingRepository $bookingRepository,
        protected BookingCancellationValidator $bookingCancellationValidator
    ) {}

    /**
     * Confirm a pending booking (PENDING -> CONFIRMED).
     *
     * @throws InvalidBookingTransitionException|RoomNotAvailableException
     */
    public function confirm(Booking $booking, User $actor): Booking
    {
        return DB::transaction(function () use ($booking, $actor) {
            $currentBooking = $this->bookingRepository->lockForUpdate($booking->id);
            Gate::forUser($actor)->authorize('manageHost', $currentBooking);

            $currentStatus = $currentBooking->status instanceof BookingStatus
                ? $currentBooking->status
                : BookingStatus::from($currentBooking->status);

            if (! $currentStatus->canTransitionTo(BookingStatus::CONFIRMED)) {
                throw new InvalidBookingTransitionException($currentStatus, BookingStatus::CONFIRMED);
            }

            // Re-check room availability (excluding current booking)
            $room = $this->bookingRepository->lockRoomForUpdate($currentBooking->room_id);
            $checkResult = $this->availabilityService->check(
                $room,
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
            $this->bookingRepository->save($currentBooking);

            $this->bookingRepository->loadForHost($currentBooking);
            $this->bookingRepository->attachServicesTotal($currentBooking);

            return $currentBooking;
        });
    }

    /**
     * Reject a pending booking (PENDING -> REJECTED).
     *
     * @throws InvalidBookingTransitionException
     */
    public function reject(Booking $booking, User $actor, ?string $reason = null): Booking
    {
        return DB::transaction(function () use ($booking, $actor, $reason) {
            $currentBooking = $this->bookingRepository->lockForUpdate($booking->id);
            Gate::forUser($actor)->authorize('manageHost', $currentBooking);

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
            $this->bookingRepository->save($currentBooking);

            $this->bookingRepository->loadForHost($currentBooking);
            $this->bookingRepository->attachServicesTotal($currentBooking);

            return $currentBooking;
        });
    }

    /**
     * Check-in a confirmed booking (CONFIRMED -> CHECKED_IN).
     *
     * @throws InvalidBookingTransitionException
     */
    public function checkIn(Booking $booking, User $actor): Booking
    {
        return DB::transaction(function () use ($booking, $actor) {
            $currentBooking = $this->bookingRepository->lockForUpdate($booking->id);
            Gate::forUser($actor)->authorize('manageHost', $currentBooking);

            $currentStatus = $currentBooking->status instanceof BookingStatus
                ? $currentBooking->status
                : BookingStatus::from($currentBooking->status);

            if (! $currentStatus->canTransitionTo(BookingStatus::CHECKED_IN)) {
                throw new InvalidBookingTransitionException($currentStatus, BookingStatus::CHECKED_IN);
            }

            $currentBooking->status = BookingStatus::CHECKED_IN;
            $this->bookingRepository->save($currentBooking);

            $this->bookingRepository->loadForHost($currentBooking);
            $this->bookingRepository->attachServicesTotal($currentBooking);

            return $currentBooking;
        });
    }

    /**
     * Check-out a checked-in booking (CHECKED_IN -> COMPLETED).
     * Note: NO Invoice is created here.
     *
     * @throws InvalidBookingTransitionException
     */
    public function checkOut(Booking $booking, User $actor): Booking
    {
        return DB::transaction(function () use ($booking, $actor) {
            $currentBooking = $this->bookingRepository->lockForUpdate($booking->id);
            Gate::forUser($actor)->authorize('manageHost', $currentBooking);

            $currentStatus = $currentBooking->status instanceof BookingStatus
                ? $currentBooking->status
                : BookingStatus::from($currentBooking->status);

            if (! $currentStatus->canTransitionTo(BookingStatus::COMPLETED)) {
                throw new InvalidBookingTransitionException($currentStatus, BookingStatus::COMPLETED);
            }

            $currentBooking->status = BookingStatus::COMPLETED;
            $this->bookingRepository->save($currentBooking);

            $this->bookingRepository->loadForHost($currentBooking);
            $this->bookingRepository->attachServicesTotal($currentBooking);

            return $currentBooking;
        });
    }

    /**
     * Cancel a booking.
     *
     * @throws InvalidBookingTransitionException|BookingCancellationNotAllowedException
     */
    public function cancel(Booking $booking, User $user): Booking
    {
        return DB::transaction(function () use ($booking, $user) {
            $currentBooking = $this->bookingRepository->lockForUpdate($booking->id);

            Gate::forUser($user)->authorize('cancel', $currentBooking);

            $currentStatus = $currentBooking->status instanceof BookingStatus
                ? $currentBooking->status
                : BookingStatus::from($currentBooking->status);

            if (! $currentStatus->canTransitionTo(BookingStatus::CANCELLED)) {
                throw new InvalidBookingTransitionException($currentStatus, BookingStatus::CANCELLED);
            }

            $check = $this->bookingCancellationValidator->check(
                $currentStatus,
                (string) $currentBooking->check_in_date,
                now()->toDateString(),
                (int) config('booking.cancel_before_days', 1),
            );

            if (! $check['allowed']) {
                throw new BookingCancellationNotAllowedException($check['reason'] ?? 'Không thể hủy đặt phòng.');
            }

            $currentBooking->status = BookingStatus::CANCELLED;
            $this->bookingRepository->save($currentBooking);

            $this->bookingRepository->loadForCustomer($currentBooking);

            return $currentBooking;
        });
    }
}
