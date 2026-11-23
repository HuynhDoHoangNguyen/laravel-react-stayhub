<?php

namespace App\DTOs\Statistics;

use App\DTOs\Statistics\Requests\AdminStatisticsQueryRequest;
use DateTimeImmutable;
use DateTimeZone;
use OverflowException;

final readonly class AdminStatisticsPeriod
{
    public const TIMEZONE = 'Asia/Ho_Chi_Minh';
    public DateTimeImmutable $generatedAt;
    public string $fromMonth;
    public string $toMonth;
    /** @var list<array{month: string, start: int, end: int}> */
    public array $months;

    public function __construct(AdminStatisticsQueryRequest $query, DateTimeImmutable $generatedAt)
    {
        if (PHP_INT_SIZE !== 8) {
            throw new OverflowException('Thống kê yêu cầu runtime 64-bit.');
        }
        $this->generatedAt = $generatedAt->setTimezone(new DateTimeZone('UTC'));
        $zone = new DateTimeZone(self::TIMEZONE);
        $last = $query->toMonth ?? $this->generatedAt->setTimezone($zone)->format('Y-m');
        $endMonth = DateTimeImmutable::createFromFormat('!Y-m-d', $last.'-01', $zone);
        $first = $query->fromMonth ?? $endMonth->modify('-11 months')->format('Y-m');
        // Also validate clock-derived defaults; never silently serialize an unsupported label.
        new AdminStatisticsQueryRequest($first, $last);
        $this->fromMonth = $first;
        $this->toMonth = $last;
        $current = DateTimeImmutable::createFromFormat('!Y-m-d', $first.'-01', $zone);
        $months = [];
        $length = AdminStatisticsQueryRequest::monthIndex($last) - AdminStatisticsQueryRequest::monthIndex($first) + 1;
        for ($i = 0; $i < $length; $i++) {
            // Native arithmetic supports the internal 10000-01 boundary; UTC remains serializable.
            $next = $current->modify('+1 month');
            $months[] = ['month' => $current->format('Y-m'), 'start' => $current->getTimestamp(), 'end' => $next->getTimestamp()];
            $current = $next;
        }
        $this->months = $months;
    }

    public static function iso(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');
    }

    public function projection(): array
    {
        $instant = static fn (int $epoch): string => self::iso(new DateTimeImmutable('@'.$epoch));
        return ['from_month' => $this->fromMonth, 'to_month' => $this->toMonth,
            'start_at' => $instant($this->months[0]['start']),
            'end_at_exclusive' => $instant($this->months[array_key_last($this->months)]['end'])];
    }
}
