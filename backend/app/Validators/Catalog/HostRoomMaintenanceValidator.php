<?php

namespace App\Validators\Catalog;

use App\DTOs\Catalog\Requests\CheckRoomMaintenanceQuery;
use App\DTOs\Catalog\Requests\CreateHostRoomMaintenanceRequest;
use App\Enums\BookingStatus;
use Illuminate\Validation\ValidationException;

final class HostRoomMaintenanceValidator
{
    public function __construct(private readonly RoomMaintenanceValidator $maintenanceRules) {}

    public function validatePeriod(CreateHostRoomMaintenanceRequest $input): CheckRoomMaintenanceQuery
    {
        $errors = [];
        if (!$this->maintenanceRules->isValidDate($input->startDate)) {
            $errors['start_date'] = ['Ngày bắt đầu phải là ngày hợp lệ theo định dạng YYYY-MM-DD.'];
        }
        if (!$this->maintenanceRules->isValidDate($input->endDate)) {
            $errors['end_date'] = ['Ngày kết thúc phải là ngày hợp lệ theo định dạng YYYY-MM-DD.'];
        }
        if ($errors === [] && $input->startDate >= $input->endDate) {
            $errors['end_date'] = ['Ngày kết thúc phải sau ngày bắt đầu.'];
        }
        if ($input->reason !== null && strlen($input->reason) > 65535) {
            $errors['reason'] = ['Lý do vượt quá độ dài cho phép.'];
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return new CheckRoomMaintenanceQuery($input->roomId, $input->startDate, $input->endDate);
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
}
