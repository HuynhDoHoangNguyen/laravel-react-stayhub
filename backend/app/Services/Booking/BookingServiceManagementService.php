<?php

namespace App\Services\Booking;

use App\DTOs\Booking\Requests\AddBookingServiceRequest;
use App\DTOs\Booking\Requests\UpdateBookingServiceRequest;
use App\Enums\BookingStatus;
use App\Exceptions\ServiceNotAllowedException;
use App\Models\Booking;
use App\Models\BookingService as BookingServiceModel;
use App\Repositories\Booking\BookingRepository;
use App\Repositories\Booking\BookingServiceRepository;
use App\Repositories\Booking\ServiceRepository;
use App\Support\DecimalMoney;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

    public function add(Booking $booking, AddBookingServiceRequest $input): BookingServiceModel
    {
        return DB::transaction(function () use ($booking, $input) {
            $currentBooking = $this->bookingRepository->lockForUpdate($booking->id);
            $this->assertServiceChangeAllowed($currentBooking, 'Chỉ được thêm dịch vụ khi đặt phòng ở trạng thái đang lưu trú (CHECKED_IN).');

            $service = $this->serviceRepository->findForUpdate($input->serviceId);
            if (! $service) {
                throw ValidationException::withMessages([
                    'service_id' => [__('validation.exists', ['attribute' => 'service id'])],
                ]);
            }
            $room = $this->bookingRepository->findRoomOrFail($currentBooking->room_id);
            if ($service->property_id !== $room->property_id) {
                throw new ServiceNotAllowedException('Dịch vụ không thuộc cơ sở lưu trú của phòng này.');
            }
            if (! $service->status) {
                throw new ServiceNotAllowedException('Dịch vụ này hiện đang tạm ngưng cung cấp.');
            }

            $qty = $input->quantity;
            $existing = $this->bookingServiceRepository->findForBookingAndService($currentBooking->id, $service->id);
            if ($existing) {
                $newQty = DecimalMoney::addQuantity((string) $existing->quantity, $qty);
                $newAmount = $this->pricingService->serviceAmount($newQty, $existing->unit_price);
                return $this->bookingServiceRepository->update($existing, ['quantity' => $newQty, 'amount' => $newAmount]);
            }

            $unitPriceStr = DecimalMoney::format($service->price);
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

    public function updateQuantity(Booking $booking, BookingServiceModel $bookingService, UpdateBookingServiceRequest $input): BookingServiceModel
    {
        return DB::transaction(function () use ($booking, $bookingService, $input) {
            $currentBooking = $this->bookingRepository->lockForUpdate($booking->id);
            $currentLine = $this->bookingServiceRepository->findForBookingOrFail($bookingService->id, $currentBooking->id);
            $this->assertServiceChangeAllowed($currentBooking, 'Chỉ được thay đổi dịch vụ khi đặt phòng ở trạng thái đang lưu trú (CHECKED_IN).');

            $qty = $input->quantity;
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
        return $this->bookingServiceRepository->totalForBooking($booking->id);
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
