<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Exceptions\BookingCancellationNotAllowedException;
use App\Exceptions\RoomNotAvailableException;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BookingService
{
    public function __construct(
        protected AvailabilityService $availabilityService,
        protected PricingService $pricingService
    ) {}

    /**
     * Create a new booking with transaction and lockForUpdate.
     *
     * @throws RoomNotAvailableException
     */
    public function create(User $customer, array $data): Booking
    {
        return DB::transaction(function () use ($customer, $data) {
            // Lock room row for update
            $room = Room::query()
                ->where('id', $data['room_id'])
                ->lockForUpdate()
                ->firstOrFail();

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
                if (! Booking::where('booking_code', $candidate)->exists()) {
                    $bookingCode = $candidate;
                    break;
                }
            }

            if (! $bookingCode) {
                $bookingCode = $prefix . strtoupper(Str::random(10));
            }

            return Booking::create([
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
        });
    }

    /**
     * List paginated booking history for a customer.
     */
    public function listForCustomer(User $customer, array $filters): LengthAwarePaginator
    {
        $query = Booking::query()
            ->forCustomer($customer->id)
            ->with(['room.property', 'bookingServices'])
            ->orderBy('created_at', 'desc');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $perPage = min((int) ($filters['per_page'] ?? 15), 50);
        if ($perPage < 1) {
            $perPage = 15;
        }

        return $query->paginate($perPage);
    }

    /**
     * Check if a booking is allowed to be cancelled.
     *
     * @return array{allowed: bool, reason: string|null}
     */
    public function cancellationCheck(Booking $booking): array
    {
        $status = $booking->status instanceof BookingStatus ? $booking->status : BookingStatus::from($booking->status);

        if ($status === BookingStatus::PENDING) {
            return ['allowed' => true, 'reason' => null];
        }

        if ($status === BookingStatus::CONFIRMED) {
            $cancelBeforeDays = (int) config('booking.cancel_before_days', 1);
            $checkInDate = Carbon::parse($booking->check_in_date)->startOfDay();
            $cancelDeadline = (clone $checkInDate)->subDays($cancelBeforeDays)->startOfDay();

            if (now()->startOfDay()->lessThanOrEqualTo($cancelDeadline)) {
                return ['allowed' => true, 'reason' => null];
            }

            return ['allowed' => false, 'reason' => 'Đã qua thời hạn hủy phòng cho phép.'];
        }

        return ['allowed' => false, 'reason' => 'Trạng thái đặt phòng không được phép hủy.'];
    }

    /**
     * Cancel a booking for a customer.
     *
     * @throws BookingCancellationNotAllowedException
     */
    public function cancel(Booking $booking, User $user): Booking
    {
        return app(BookingWorkflowService::class)->cancel($booking, $user, $this);
    }
}
