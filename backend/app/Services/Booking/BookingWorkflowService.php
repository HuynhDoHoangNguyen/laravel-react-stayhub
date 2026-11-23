<?php

namespace App\Services\Booking;

use App\Enums\BookingStatus;
use App\Exceptions\BookingCancellationNotAllowedException;
use App\Exceptions\BookingDomainException;
use App\Exceptions\InvalidBookingTransitionException;
use App\Exceptions\RoomNotAvailableException;
use App\Models\Booking;
use App\Models\User;
use App\Repositories\Booking\BookingRepository;
use App\Validators\Booking\BookingCancellationValidator;
use App\Validators\Catalog\RoomMaintenanceValidator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;

class BookingWorkflowService
{
    public function __construct(
        protected AvailabilityService $availabilityService,
        protected BookingRepository $bookingRepository,
        protected BookingCancellationValidator $bookingCancellationValidator,
        private readonly RoomMaintenanceValidator $dateRules,
    ) {}

    /**
     * Confirm a pending booking (PENDING -> CONFIRMED).
     *
     * @throws InvalidBookingTransitionException|RoomNotAvailableException
     */
    public function confirm(Booking $booking, User $actor): Booking
    {
        return DB::transaction(function () use ($booking, $actor) {
            // Lock Room first to establish hierarchical lock order (Room -> Booking) and prevent deadlocks with maintenance workflows
            $roomId = (int) ($booking->room_id ?: Booking::query()->whereKey($booking->id)->value('room_id'));
            $room = $this->bookingRepository->lockRoomForUpdate($roomId);
            $currentBooking = $this->bookingRepository->lockForUpdate($booking->id);

            if ($currentBooking->room_id !== $room->id) {
                $room = $this->bookingRepository->lockRoomForUpdate($currentBooking->room_id);
            }

            Gate::forUser($actor)->authorize('manageHost', $currentBooking);

            $currentStatus = $currentBooking->status instanceof BookingStatus
                ? $currentBooking->status
                : BookingStatus::from($currentBooking->status);

            if (! $currentStatus->canTransitionTo(BookingStatus::CONFIRMED)) {
                throw new InvalidBookingTransitionException($currentStatus, BookingStatus::CONFIRMED);
            }

            // Re-check room availability (excluding current booking)
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
            $roomId = (int) ($booking->room_id ?: Booking::query()->whereKey($booking->id)->value('room_id'));
            $room = $this->bookingRepository->lockRoomForUpdate($roomId);
            $currentBooking = $this->bookingRepository->lockForUpdate($booking->id);
            Gate::forUser($actor)->authorize('manageHost', $currentBooking);
            if ((int) $currentBooking->room_id !== (int) $room->getKey()) {
                throw new BookingDomainException('Phòng của đặt phòng đã thay đổi. Vui lòng tải lại.', ['reasons' => ['BOOKING_ROOM_CHANGED']], 409);
            }

            $currentStatus = $currentBooking->status instanceof BookingStatus
                ? $currentBooking->status
                : BookingStatus::from($currentBooking->status);

            if (! $currentStatus->canTransitionTo(BookingStatus::CHECKED_IN)) {
                throw new InvalidBookingTransitionException($currentStatus, BookingStatus::CHECKED_IN);
            }

            $today = now(config('app.timezone'))->toDateString();
            $checkIn = substr((string) $currentBooking->getRawOriginal('check_in_date'), 0, 10);
            $checkOut = substr((string) $currentBooking->getRawOriginal('check_out_date'), 0, 10);
            if (!$this->dateRules->isValidDate($checkIn) || !$this->dateRules->isValidDate($checkOut)
                || $today < $checkIn || $today >= $checkOut) {
                throw new BookingDomainException(
                    'Chỉ có thể nhận phòng từ ngày nhận phòng đến trước ngày trả phòng.',
                    ['reasons' => ['CHECK_IN_OUTSIDE_STAY']],
                );
            }
            if ($this->availabilityService->hasMaintenanceBlock($room, $today, $checkOut)) {
                throw new RoomNotAvailableException('Phòng đang có lịch bảo trì ảnh hưởng đến thời gian lưu trú.', ['MAINTENANCE_OVERLAP']);
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
