<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Exceptions\RoomNotAvailableException;
use App\Models\Booking;
use App\Models\User;
use App\Repositories\Booking\BookingRepository;
use App\Repositories\Booking\BookingServiceRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BookingService
{
    public function __construct(
        protected AvailabilityService $availabilityService,
        protected PricingService $pricingService,
        protected BookingRepository $bookingRepository,
        protected BookingServiceRepository $bookingServiceRepository,
        protected BookingWorkflowService $bookingWorkflowService
    ) {}

    /**
     * Build a public preview using the same availability and pricing rules as create.
     *
     * @return array<string, mixed>
     */
    public function preview(array $data): array
    {
        $room = $this->bookingRepository->findRoomOrFail((int) $data['room_id']);
        $quote = $this->pricingService->quote(
            $room,
            $data['check_in_date'],
            $data['check_out_date']
        );
        $availability = $this->availabilityService->check(
            $room,
            $data['check_in_date'],
            $data['check_out_date'],
            (int) $data['guest_count']
        );

        return [
            'room_id' => $room->id,
            'check_in_date' => $data['check_in_date'],
            'check_out_date' => $data['check_out_date'],
            'guest_count' => (int) $data['guest_count'],
            'number_of_nights' => $quote['number_of_nights'],
            'price_per_night' => $quote['price_per_night'],
            'room_total' => $quote['room_total'],
            'availability' => $availability,
        ];
    }

    /**
     * Create a new booking with transaction and lockForUpdate.
     *
     * @throws RoomNotAvailableException
     */
    public function create(User $customer, array $data): Booking
    {
        return DB::transaction(function () use ($customer, $data) {
            // Lock room row for update
            $room = $this->bookingRepository->lockRoomForUpdate((int) $data['room_id']);

            // Re-check availability under lock
            $checkResult = $this->availabilityService->check(
                $room,
                $data['check_in_date'],
                $data['check_out_date'],
                (int) $data['guest_count']
            );

            if (! $checkResult['available']) {
                throw new RoomNotAvailableException(
                    'Phòng không khả dụng trong thời gian yêu cầu.',
                    $checkResult['reasons']
                );
            }

            // Snapshot price from DB
            $quote = $this->pricingService->quote(
                $room,
                $data['check_in_date'],
                $data['check_out_date']
            );

            // Generate unique booking code
            $prefix = config('booking.code_prefix', 'BK');
            $bookingCode = null;
            for ($i = 0; $i < 5; $i++) {
                $candidate = $prefix . strtoupper(Str::random(8));
                if (! $this->bookingRepository->bookingCodeExists($candidate)) {
                    $bookingCode = $candidate;
                    break;
                }
            }

            if (! $bookingCode) {
                $bookingCode = $prefix . strtoupper(Str::random(10));
            }

            $created = $this->bookingRepository->create([
                'booking_code' => $bookingCode,
                'customer_id' => $customer->id,
                'room_id' => $room->id,
                'check_in_date' => $data['check_in_date'],
                'check_out_date' => $data['check_out_date'],
                'guest_count' => (int) $data['guest_count'],
                'number_of_nights' => $quote['number_of_nights'],
                'price_per_night' => $quote['price_per_night'],
                'room_total' => $quote['room_total'],
                'status' => BookingStatus::PENDING,
                'note' => $data['note'] ?? null,
            ]);

            $this->bookingRepository->loadForCustomerCreation($created);

            return $created;
        });
    }

    /**
     * Cancel a booking for a customer.
     *
     * @throws BookingCancellationNotAllowedException
     */
    public function cancel(Booking $booking, User $user): Booking
    {
        return $this->bookingWorkflowService->cancel($booking, $user);
    }
}
