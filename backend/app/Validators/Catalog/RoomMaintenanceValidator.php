<?php

declare(strict_types=1);

namespace App\Validators\Catalog;

use App\DTOs\Catalog\Requests\CheckRoomMaintenanceQuery;
use App\Enums\MaintenanceStatus;
use InvalidArgumentException;

/** UC51 rules; facts are scoped to one room by the Repository, without date casts. */
final class RoomMaintenanceValidator
{
    public function validateQuery(mixed $roomId, mixed $checkInDate, mixed $checkOutDate): CheckRoomMaintenanceQuery
    {
        if (!is_int($roomId) || $roomId < 1) {
            throw new InvalidArgumentException('Mã phòng phải là số nguyên dương.');
        }
        if (!$this->isValidDate($checkInDate)) {
            throw new InvalidArgumentException('Ngày nhận phòng phải có định dạng YYYY-MM-DD hợp lệ, từ năm 1000 đến 9999.');
        }
        if (!$this->isValidDate($checkOutDate)) {
            throw new InvalidArgumentException('Ngày trả phòng phải có định dạng YYYY-MM-DD hợp lệ, từ năm 1000 đến 9999.');
        }
        if ($checkInDate >= $checkOutDate) {
            throw new InvalidArgumentException('Ngày trả phòng phải sau ngày nhận phòng.');
        }

        return new CheckRoomMaintenanceQuery($roomId, $checkInDate, $checkOutDate);
    }

    /**
     * @param iterable<object{status: string, start_date: mixed, end_date: mixed}> $facts
     * @param CheckRoomMaintenanceQuery $query Input produced by validateQuery().
     */
    public function hasBlockingFacts(iterable $facts, CheckRoomMaintenanceQuery $query): bool
    {
        foreach ($facts as $fact) {
            // In-progress work needs an explicit completion/cancellation, even when overdue.
            if ($fact->status === MaintenanceStatus::IN_PROGRESS->value) {
                return true;
            }
            if ($fact->status !== MaintenanceStatus::SCHEDULED->value) {
                continue;
            }
            // Do not advertise an empty interval for an unresolved, malformed schedule.
            if (!$this->isValidDate($fact->start_date) || !$this->isValidDate($fact->end_date)
                || $fact->start_date >= $fact->end_date) {
                return true;
            }
            if ($fact->start_date < $query->checkOutDate && $fact->end_date > $query->checkInDate) {
                return true;
            }
        }

        return false;
    }

    private function isValidDate(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/\A[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}\z/', $value) === 1
            && checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4));
    }
}
