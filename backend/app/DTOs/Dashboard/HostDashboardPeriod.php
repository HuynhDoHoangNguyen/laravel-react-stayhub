<?php

namespace App\DTOs\Dashboard;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use OverflowException;

/** Internal period values; raw HTTP query presence/types are validated at the HTTP boundary. */
final readonly class HostDashboardPeriod
{
    public const TIMEZONE = 'Asia/Ho_Chi_Minh';
    public DateTimeImmutable $generatedAt;
    public string $fromMonth;
    public string $toMonth;
    /** @var list<array{month: string, start: int, end: int}> */
    public array $months;

    public function __construct(DateTimeImmutable $generatedAt, ?string $fromMonth = null, ?string $toMonth = null)
    {
        if (PHP_INT_SIZE !== 8) { throw new OverflowException('Tổng quan Host yêu cầu runtime 64-bit.'); }
        $this->generatedAt = $generatedAt->setTimezone(new DateTimeZone('UTC'));
        $zone = new DateTimeZone(self::TIMEZONE);
        if ($fromMonth === null && $toMonth === null) {
            $toMonth = $this->generatedAt->setTimezone($zone)->format('Y-m');
            $firstOfLastMonth = $this->generatedAt->setTimezone($zone)->modify('first day of this month')->setTime(0, 0);
            $fromMonth = $firstOfLastMonth->modify('-11 months')->format('Y-m');
        }
        $pattern = '/\A(?!0000)[0-9]{4}-(?:0[1-9]|1[0-2])\z/';
        if ($fromMonth === null || $toMonth === null || preg_match($pattern, $fromMonth) !== 1
            || preg_match($pattern, $toMonth) !== 1 || $fromMonth > $toMonth) {
            throw new InvalidArgumentException('Kỳ tổng quan Host không hợp lệ.');
        }
        $index = static fn (string $month): int => (int) substr($month, 0, 4) * 12 + (int) substr($month, 5, 2) - 1;
        $length = $index($toMonth) - $index($fromMonth) + 1;
        if ($length > 12) { throw new InvalidArgumentException('Kỳ tổng quan Host không được vượt quá 12 tháng.'); }
        $this->fromMonth = $fromMonth;
        $this->toMonth = $toMonth;
        $current = DateTimeImmutable::createFromFormat('!Y-m-d', $fromMonth.'-01', $zone);
        $months = [];
        for ($i = 0; $i < $length; $i++) {
            // Native DateTime handles the internal 10000-01 boundary without overflowing Carbon's format parser.
            $next = $current->modify('+1 month');
            $months[] = ['month' => $current->format('Y-m'), 'start' => $current->getTimestamp(), 'end' => $next->getTimestamp()];
            $current = $next;
        }
        $this->months = $months;
    }

    public function projection(): array
    {
        $iso = static fn (int $epoch): string => (new DateTimeImmutable('@'.$epoch))->format('Y-m-d\TH:i:s.u\Z');
        return ['from_month' => $this->fromMonth, 'to_month' => $this->toMonth,
            'start_at' => $iso($this->months[0]['start']),
            'end_at_exclusive' => $iso($this->months[array_key_last($this->months)]['end'])];
    }
}
