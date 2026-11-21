<?php

namespace App\DTOs\Statistics\Requests;

use InvalidArgumentException;

final readonly class AdminStatisticsQueryRequest
{
    public const MONTH_PATTERN = '/\A(?!0000)[0-9]{4}-(?:0[1-9]|1[0-2])\z/';

    public function __construct(public ?string $fromMonth = null, public ?string $toMonth = null)
    {
        if ($fromMonth === null && $toMonth === null) {
            return;
        }
        if ($fromMonth === null || $toMonth === null
            || preg_match(self::MONTH_PATTERN, $fromMonth) !== 1
            || preg_match(self::MONTH_PATTERN, $toMonth) !== 1
            || $fromMonth > $toMonth || self::monthIndex($toMonth) - self::monthIndex($fromMonth) > 11) {
            throw new InvalidArgumentException('Kỳ thống kê không hợp lệ.');
        }
    }

    public static function monthIndex(string $month): int
    {
        return (int) substr($month, 0, 4) * 12 + (int) substr($month, 5, 2) - 1;
    }
}
