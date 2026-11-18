<?php

namespace App\Validators\Booking;

use App\Enums\BookingStatus;
use Carbon\CarbonImmutable;

/** Pure cancellation rule evaluated from already resolved booking facts. */
final class BookingCancellationValidator
{
    /** @return array{allowed: bool, reason: string|null} */
    public function check(
        BookingStatus|string $status,
        string $checkInDate,
        string $today,
        int $cancelBeforeDays,
    ): array {
        $status = $status instanceof BookingStatus ? $status : BookingStatus::from($status);

        if ($status === BookingStatus::PENDING) {
            return ['allowed' => true, 'reason' => null];
        }

        if ($status === BookingStatus::CONFIRMED) {
            $cancelDeadline = CarbonImmutable::parse($checkInDate)
                ->startOfDay()
                ->subDays($cancelBeforeDays);

            if (CarbonImmutable::parse($today)->startOfDay()->lessThanOrEqualTo($cancelDeadline)) {
                return ['allowed' => true, 'reason' => null];
            }

            return ['allowed' => false, 'reason' => 'Đã qua thời hạn hủy phòng cho phép.'];
        }

        return ['allowed' => false, 'reason' => 'Trạng thái đặt phòng không được phép hủy.'];
    }
}
