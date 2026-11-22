<?php

namespace App\Validators\Catalog;

use App\DTOs\Catalog\Requests\CheckRoomMaintenanceQuery;
use App\Enums\BookingStatus;
use App\Exceptions\AppException;
use Illuminate\Validation\ValidationException;

final class HostRoomMaintenanceValidator
{
    public function __construct(private readonly RoomMaintenanceValidator $maintenanceRules) {}

    public function validatePeriod(int $roomId, string $startDate, string $endDate, ?string $reason): CheckRoomMaintenanceQuery
    {
        $errors = [];
        if (!$this->maintenanceRules->isValidDate($startDate)) {
            $errors['start_date'] = ['Ngày bắt đầu phải là ngày hợp lệ theo định dạng YYYY-MM-DD.'];
        }
        if (!$this->maintenanceRules->isValidDate($endDate)) {
            $errors['end_date'] = ['Ngày kết thúc phải là ngày hợp lệ theo định dạng YYYY-MM-DD.'];
        }
        if ($errors === [] && $startDate >= $endDate) {
            $errors['end_date'] = ['Ngày kết thúc phải sau ngày bắt đầu.'];
        }
        if ($reason !== null && strlen($reason) > 65535) {
            $errors['reason'] = ['Lý do vượt quá độ dài cho phép.'];
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return new CheckRoomMaintenanceQuery($roomId, $startDate, $endDate);
    }

    public function validateStartDate(string $startDate, string $today): void
    {
        if ($startDate < $today) {
            throw ValidationException::withMessages(['start_date' => ['Ngày bắt đầu phải từ hôm nay trở đi.']]);
        }
    }

    /** @param iterable<object{status: string, check_in_date: mixed, check_out_date: mixed}> $facts */
    public function hasBlockingBookingFacts(iterable $facts, CheckRoomMaintenanceQuery $period, string $today): bool
    {
        foreach ($facts as $fact) {
            $status = BookingStatus::tryFrom($fact->status);
            if ($status === null || !$status->holdsRoom()) {
                continue;
            }

            $checkIn = $fact->check_in_date;
            $checkOut = $fact->check_out_date;
            if (!$this->maintenanceRules->isValidDate($checkIn)
                || !$this->maintenanceRules->isValidDate($checkOut) || $checkIn >= $checkOut) {
                return true;
            }
            if ($status === BookingStatus::CHECKED_IN && $checkOut <= $today) {
                return true;
            }
            if ($checkIn < $period->checkOutDate && $checkOut > $period->checkInDate) {
                return true;
            }
        }

        return false;
    }

    public function validateStartWindow(int $roomId, mixed $startDate, mixed $endDate, string $today): CheckRoomMaintenanceQuery
    {
        if (!$this->maintenanceRules->isValidDate($startDate) || !$this->maintenanceRules->isValidDate($endDate)
            || $today < $startDate || $today >= $endDate) {
            throw new AppException(
                'CATALOG_ROOM_MAINTENANCE_STATUS_CONFLICT',
                'Chỉ có thể bắt đầu bảo trì trong khoảng từ ngày bắt đầu đến trước ngày kết thúc.',
                409,
            );
        }

        return new CheckRoomMaintenanceQuery($roomId, $startDate, $endDate);
    }

    /** @param iterable<object{status: string, check_in_date: mixed, check_out_date: mixed}> $facts */
    public function hasBookingBlockingMaintenanceStart(iterable $facts, string $today): bool
    {
        foreach ($facts as $fact) {
            $status = BookingStatus::tryFrom($fact->status);
            if ($status === null || !$status->holdsRoom()) {
                continue;
            }

            if ($status === BookingStatus::CHECKED_IN
                || !$this->maintenanceRules->isValidDate($fact->check_in_date)
                || !$this->maintenanceRules->isValidDate($fact->check_out_date)
                || $fact->check_in_date >= $fact->check_out_date
                || $fact->check_out_date > $today) {
                return true;
            }
        }

        return false;
    }
}
