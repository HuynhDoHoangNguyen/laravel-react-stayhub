<?php

namespace App\Enums;

enum HostReportReason: string
{
    case WRONG_ADDRESS = 'WRONG_ADDRESS';
    case PROPERTY_NOT_FOUND = 'PROPERTY_NOT_FOUND';
    case OFF_PLATFORM_PAYMENT = 'OFF_PLATFORM_PAYMENT';
    case CHECK_IN_REFUSED = 'CHECK_IN_REFUSED';

    public function label(): string
    {
        return match ($this) {
            self::WRONG_ADDRESS => 'Sai địa chỉ',
            self::PROPERTY_NOT_FOUND => 'Cơ sở không tồn tại',
            self::OFF_PLATFORM_PAYMENT => 'Yêu cầu thanh toán ngoài hệ thống',
            self::CHECK_IN_REFUSED => 'Từ chối cho nhận phòng',
        };
    }
}
