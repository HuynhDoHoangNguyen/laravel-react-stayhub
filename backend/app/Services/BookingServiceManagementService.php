<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Exceptions\ServiceNotAllowedException;
use App\Models\Booking;
use App\Models\BookingService as BookingServiceModel;
use App\Models\Service;
use Illuminate\Support\Facades\DB;

class BookingServiceManagementService
{
    public function __construct(
        protected PricingService $pricingService
    ) {}

    /**
     * Add a service to a booking during stay (CHECKED_IN).
     *
     * @throws ServiceNotAllowedException
     */
    public function add(Booking $booking, int $serviceId, float $quantity): BookingServiceModel
    {
        return DB::transaction(function () use ($booking, $serviceId, $quantity) {
            $currentBooking = Booking::query()
                ->where('id', $booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            $currentStatus = $currentBooking->status instanceof BookingStatus
                ? $currentBooking->status->value
                : (string) $currentBooking->status;

            $allowedStatuses = (array) config('booking.service_allowed_statuses', ['CHECKED_IN']);
            if (! in_array($currentStatus, $allowedStatuses, true)) {
                throw new ServiceNotAllowedException('Chỉ được thêm dịch vụ khi đặt phòng ở trạng thái đang lưu trú (CHECKED_IN).');
            }

            $service = Service::query()->where('id', $serviceId)->firstOrFail();

            // Check if service belongs to the property of the room
            if ($service->property_id !== $currentBooking->room->property_id) {
                throw new ServiceNotAllowedException('Dịch vụ không thuộc cơ sở lưu trú của phòng này.');
            }

            // Check if service is active
            if (! $service->status) {
                throw new ServiceNotAllowedException('Dịch vụ này hiện đang tạm ngưng cung cấp.');
            }

            $qty = round(max(0.01, $quantity), 2);

            // Check if service is already added to booking
            $existing = BookingServiceModel::query()
                ->where('booking_id', $currentBooking->id)
                ->where('service_id', $service->id)
                ->first();

            if ($existing) {
                $newQty = round($existing->quantity + $qty, 2);
                $newAmount = $this->pricingService->serviceAmount($newQty, $existing->unit_price);

                $existing->update([
                    'quantity' => $newQty,
                    'amount' => $newAmount,
                ]);

                return $existing;
            }

            // Create new booking service record with snapshot
            $unitPriceStr = number_format((float) $service->price, 2, '.', '');
            $amountStr = $this->pricingService->serviceAmount($qty, $unitPriceStr);

            return BookingServiceModel::create([
                'booking_id' => $currentBooking->id,
                'service_id' => $service->id,
                'service_name' => $service->name,
                'unit' => $service->unit,
                'quantity' => $qty,
                'unit_price' => $unitPriceStr,
                'amount' => $amountStr,
            ]);
        });
    }

    /**
     * Update quantity of a booking service record.
     *
     * @throws ServiceNotAllowedException
     */
    public function updateQuantity(Booking $booking, BookingServiceModel $bookingService, float $quantity): BookingServiceModel
    {
        if ($bookingService->booking_id !== $booking->id) {
            abort(404, 'Dịch vụ trong booking không tồn tại.');
        }

        $currentStatus = $booking->status instanceof BookingStatus
            ? $booking->status->value
            : (string) $booking->status;

        $allowedStatuses = (array) config('booking.service_allowed_statuses', ['CHECKED_IN']);
        if (! in_array($currentStatus, $allowedStatuses, true)) {
            throw new ServiceNotAllowedException('Chỉ được thay đổi dịch vụ khi đặt phòng ở trạng thái đang lưu trú (CHECKED_IN).');
        }

        $qty = round(max(0.01, $quantity), 2);

        // Keep existing snapshot unit_price
        $newAmount = $this->pricingService->serviceAmount($qty, $bookingService->unit_price);

        $bookingService->update([
            'quantity' => $qty,
            'amount' => $newAmount,
        ]);

        return $bookingService;
    }

    /**
     * Remove a booking service record.
     *
     * @throws ServiceNotAllowedException
     */
    public function remove(Booking $booking, BookingServiceModel $bookingService): void
    {
        if ($bookingService->booking_id !== $booking->id) {
            abort(404, 'Dịch vụ trong booking không tồn tại.');
        }

        $currentStatus = $booking->status instanceof BookingStatus
            ? $booking->status->value
            : (string) $booking->status;

        $allowedStatuses = (array) config('booking.service_allowed_statuses', ['CHECKED_IN']);
        if (! in_array($currentStatus, $allowedStatuses, true)) {
            throw new ServiceNotAllowedException('Chỉ được xóa dịch vụ khi đặt phòng ở trạng thái đang lưu trú (CHECKED_IN).');
        }

        $bookingService->delete();
    }

    /**
     * Calculate total services amount for a booking.
     */
    public function total(Booking $booking): string
    {
        $total = BookingServiceModel::query()
            ->where('booking_id', $booking->id)
            ->sum('amount');

        return number_format((float) $total, 2, '.', '');
    }
}
