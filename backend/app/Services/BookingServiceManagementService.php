<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Exceptions\ServiceNotAllowedException;
use App\Models\Booking;
use App\Models\BookingService as BookingServiceModel;
use App\Repositories\Booking\BookingRepository;
use App\Repositories\Booking\BookingServiceRepository;
use App\Repositories\Booking\ServiceRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class BookingServiceManagementService
{
    public function __construct(
        protected PricingService $pricingService,
        protected BookingRepository $bookingRepository,
        protected BookingServiceRepository $bookingServiceRepository,
        protected ServiceRepository $serviceRepository
    ) {}

    /** @return Collection<int, BookingServiceModel> */
    public function list(Booking $booking): Collection
    {
        return $this->bookingServiceRepository->listForBooking($booking->id);
    }

    public function add(Booking $booking, int $serviceId, float $quantity): BookingServiceModel
    {
        return DB::transaction(function () use ($booking, $serviceId, $quantity) {
            $currentBooking = $this->bookingRepository->lockForUpdate($booking->id);
            $this->assertServiceChangeAllowed($currentBooking, 'Chỉ được thêm dịch vụ khi đặt phòng ở trạng thái đang lưu trú (CHECKED_IN).');

            $service = $this->serviceRepository->lockForUpdate($serviceId);
            $room = $this->bookingRepository->findRoomOrFail($currentBooking->room_id);
            if ($service->property_id !== $room->property_id) {
                throw new ServiceNotAllowedException('Dịch vụ không thuộc cơ sở lưu trú của phòng này.');
            }
            if (! $service->status) {
                throw new ServiceNotAllowedException('Dịch vụ này hiện đang tạm ngưng cung cấp.');
            }

            $qty = round(max(0.01, $quantity), 2);
            $existing = $this->bookingServiceRepository->findForBookingAndService($currentBooking->id, $service->id);
            if ($existing) {
                $newQty = round($existing->quantity + $qty, 2);
                $newAmount = $this->pricingService->serviceAmount($newQty, $existing->unit_price);
                return $this->bookingServiceRepository->update($existing, ['quantity' => $newQty, 'amount' => $newAmount]);
            }

            $unitPriceStr = number_format((float) $service->price, 2, '.', '');
            $amountStr = $this->pricingService->serviceAmount($qty, $unitPriceStr);
            return $this->bookingServiceRepository->create([
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

    public function updateQuantity(Booking $booking, BookingServiceModel $bookingService, float $quantity): BookingServiceModel
    {
        return DB::transaction(function () use ($booking, $bookingService, $quantity) {
            $currentBooking = $this->bookingRepository->lockForUpdate($booking->id);
            $currentLine = $this->bookingServiceRepository->findForBookingOrFail($bookingService->id, $currentBooking->id);
            $this->assertServiceChangeAllowed($currentBooking, 'Chỉ được thay đổi dịch vụ khi đặt phòng ở trạng thái đang lưu trú (CHECKED_IN).');

            $qty = round(max(0.01, $quantity), 2);
            $newAmount = $this->pricingService->serviceAmount($qty, $currentLine->unit_price);
            return $this->bookingServiceRepository->update($currentLine, ['quantity' => $qty, 'amount' => $newAmount]);
        });
    }

    public function remove(Booking $booking, BookingServiceModel $bookingService): void
    {
        DB::transaction(function () use ($booking, $bookingService): void {
            $currentBooking = $this->bookingRepository->lockForUpdate($booking->id);
            $currentLine = $this->bookingServiceRepository->findForBookingOrFail($bookingService->id, $currentBooking->id);
            $this->assertServiceChangeAllowed($currentBooking, 'Chỉ được xóa dịch vụ khi đặt phòng ở trạng thái đang lưu trú (CHECKED_IN).');
            $this->bookingServiceRepository->delete($currentLine);
        });
    }

    public function total(Booking $booking): string
    {
        return number_format((float) $this->bookingServiceRepository->totalForBooking($booking->id), 2, '.', '');
    }

    private function assertServiceChangeAllowed(Booking $booking, string $message): void
    {
        $status = $booking->status instanceof BookingStatus ? $booking->status->value : (string) $booking->status;
        $allowedStatuses = (array) config('booking.service_allowed_statuses', ['CHECKED_IN']);
        if (! in_array($status, $allowedStatuses, true)) {
            throw new ServiceNotAllowedException($message);
        }
    }
}
