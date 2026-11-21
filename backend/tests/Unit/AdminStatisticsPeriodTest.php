<?php

namespace Tests\Unit;

use App\DTOs\Statistics\AdminStatisticsPeriod;
use App\DTOs\Statistics\Requests\AdminStatisticsQueryRequest;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AdminStatisticsPeriodTest extends TestCase
{
    public static function boundaries(): array
    {
        return [
            ['2026-10', '2026-11', '2026-09-30T17:00:00.000000Z', '2026-11-30T17:00:00.000000Z', 2],
            ['2024-02', '2024-02', '2024-01-31T17:00:00.000000Z', '2024-02-29T17:00:00.000000Z', 1],
            ['9999-12', '9999-12', '9999-11-30T17:00:00.000000Z', '9999-12-31T17:00:00.000000Z', 1],
            // IANA Asia/Ho_Chi_Minh historical LMT is +07:06:30; UTC year 0000 is ISO-serializable.
            ['0001-01', '0001-01', '0000-12-31T16:53:30.000000Z', '0001-01-31T16:53:30.000000Z', 1],
            ['2037-12', '2038-01', '2037-11-30T17:00:00.000000Z', '2038-01-31T17:00:00.000000Z', 2],
        ];
    }

    #[DataProvider('boundaries')]
    public function test_local_boundaries_are_exact_and_serializable(string $from, string $to, string $start, string $end, int $length): void
    {
        $now = new DateTimeImmutable('2026-11-19T05:00:00.123456Z');
        $period = new AdminStatisticsPeriod(new AdminStatisticsQueryRequest($from, $to), $now);
        $this->assertSame(['from_month' => $from, 'to_month' => $to, 'start_at' => $start, 'end_at_exclusive' => $end], $period->projection());
        $this->assertCount($length, $period->months);
        $this->assertSame('2026-11-19T05:00:00.123456Z', AdminStatisticsPeriod::iso($period->generatedAt));
        foreach ($period->months as $i => $month) {
            $this->assertLessThan($month['end'], $month['start']);
            if ($i > 0) { $this->assertSame($period->months[$i - 1]['end'], $month['start']); }
        }
        $this->assertSame('2026-11-19T05:00:00.123456Z', AdminStatisticsPeriod::iso($now));
    }

    public static function defaultClocks(): array
    {
        return [['2026-11-19T05:00:00Z', '2025-12', '2026-11'],
            ['2026-11-30T16:59:59.999999Z', '2025-12', '2026-11'],
            ['2026-11-30T17:00:00Z', '2026-01', '2026-12'],
            ['2024-02-29T17:00:00Z', '2023-04', '2024-03']];
    }

    #[DataProvider('defaultClocks')]
    public function test_defaults_use_local_month_and_twelve_contiguous_buckets(string $clock, string $first, string $last): void
    {
        $period = new AdminStatisticsPeriod(new AdminStatisticsQueryRequest(), new DateTimeImmutable($clock));
        $this->assertSame($first, $period->fromMonth);
        $this->assertSame($last, $period->toMonth);
        $this->assertCount(12, $period->months);
        for ($i = 1; $i < 12; $i++) { $this->assertSame($period->months[$i - 1]['end'], $period->months[$i]['start']); }
    }
}
