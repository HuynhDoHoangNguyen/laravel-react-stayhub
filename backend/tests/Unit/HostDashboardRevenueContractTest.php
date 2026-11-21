<?php

namespace Tests\Unit;

use App\DTOs\Dashboard\HostDashboardPeriod;
use App\DTOs\Dashboard\Responses\HostDashboardResponse;
use App\Exceptions\AppException;
use App\Models\User;
use App\Repositories\Dashboard\HostDashboardRepository;
use App\Services\Dashboard\HostDashboardQueryService;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HostDashboardRevenueContractTest extends TestCase
{
    private function period(?string $from = '2026-10', ?string $to = '2026-11'): HostDashboardPeriod
    {
        return new HostDashboardPeriod(new DateTimeImmutable('2026-11-21T05:00:00.123456Z'), $from, $to);
    }

    private function facts(): object
    {
        return (object) ['properties_count' => '1', 'rooms_count' => '1', 'pending_bookings_count' => '0',
            'in_progress_maintenances_count' => '0', 'recent_json' => '[]', 'missing_paid_at' => '1',
            'summary_revenue' => '300.76', 'month_0_revenue' => '100.25', 'month_1_revenue' => '200.51'];
    }

    public static function invalidPeriods(): array
    {
        return [[null, '2026-11'], ['2026-10', null], ['', '2026-11'], ['2026-1', '2026-11'],
            [' 2026-10', '2026-11'], ['2026-10', '2026-11 '], ['2026-12', '2026-11'],
            ['2025-11', '2026-11'], ['0000-01', '0000-02'], ['10000-01', '10000-02'],
            ['2026-00', '2026-11'], ['2026-10', '2026-13']];
    }

    #[DataProvider('invalidPeriods')]
    public function test_invalid_internal_period_is_rejected(?string $from, ?string $to): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->period($from, $to);
    }

    public function test_default_boundaries_leap_extreme_years_and_future(): void
    {
        $default = $this->period(null, null);
        $this->assertSame(['2025-12', '2026-11'], [$default->fromMonth, $default->toMonth]);
        $this->assertCount(12, $default->months);
        $p = $this->period()->projection();
        $this->assertSame('2026-09-30T17:00:00.000000Z', $p['start_at']);
        $this->assertSame('2026-11-30T17:00:00.000000Z', $p['end_at_exclusive']);
        $leap = $this->period('2024-02', '2024-02')->projection();
        $this->assertSame('2024-01-31T17:00:00.000000Z', $leap['start_at']);
        $this->assertSame('2024-02-29T17:00:00.000000Z', $leap['end_at_exclusive']);
        $this->assertSame('9999-12-31T17:00:00.000000Z', $this->period('9999-12', '9999-12')->projection()['end_at_exclusive']);
        $this->assertStringStartsWith('0000-12-31T', $this->period('0001-01', '0001-01')->projection()['start_at']);
        $this->assertCount(1, $this->period('2026-12', '2026-12')->months);
        $edge = new HostDashboardPeriod(new DateTimeImmutable('2026-11-30T17:00:00Z'));
        $this->assertSame('2026-12', $edge->toMonth);
    }

    public static function denied(): array
    {
        return [['ADMIN', 'ACTIVE'], ['CUSTOMER', 'ACTIVE'], ['HOST', 'BLOCKED'], ['HOST', 'INACTIVE']];
    }

    #[DataProvider('denied')]
    public function test_actor_denied_before_invalid_period_or_repository(string $role, string $status): void
    {
        $repo = $this->createMock(HostDashboardRepository::class);
        $repo->expects($this->never())->method('overview');
        $repo->expects($this->never())->method('snapshot');
        $actor = new User(['role' => $role, 'status' => $status]); $actor->id = 1;
        try {
            (new HostDashboardQueryService($repo))->getOverview($actor, 'bad', 'worse');
            $this->fail('Actor accepted.');
        } catch (AppException $e) {
            $this->assertSame(403, $e->status);
            $this->assertSame('HOST_DASHBOARD_FORBIDDEN', $e->errorCode);
        }
    }

    public function test_service_passes_single_server_cutoff_and_does_not_read_snapshot_separately(): void
    {
        CarbonImmutable::setTestNow('2026-11-21T05:00:00.123456Z');
        try {
            $repo = $this->createMock(HostDashboardRepository::class);
            $repo->expects($this->never())->method('snapshot');
            $repo->expects($this->once())->method('overview')->with('42', $this->callback(fn ($p) =>
                $p instanceof HostDashboardPeriod && $p->generatedAt->format('U.u') === '1795237200.123456'
                && $p->fromMonth === '2026-10' && $p->toMonth === '2026-11'))->willReturn($this->facts());
            $actor = new User(['role' => 'HOST', 'status' => 'ACTIVE']); $actor->id = 42;
            $data = (new HostDashboardQueryService($repo))->getOverview($actor, '2026-10', '2026-11')->jsonSerialize();
            $this->assertSame('2026-11-21T05:00:00.123456Z', $data['generated_at']);
        } finally { CarbonImmutable::setTestNow(); }
    }

    public function test_full_projection_exact_decimal_and_warning(): void
    {
        $data = (new HostDashboardResponse($this->period(), $this->facts()))->jsonSerialize();
        $this->assertSame(['scope', 'period', 'report_timezone', 'generated_at', 'snapshot', 'summary', 'revenue_by_month', 'recent_bookings', 'warnings'], array_keys($data));
        $this->assertSame(['revenue_total' => '300.76'], $data['summary']);
        $this->assertSame([['month' => '2026-10', 'revenue_total' => '100.25'], ['month' => '2026-11', 'revenue_total' => '200.51']], $data['revenue_by_month']);
        $this->assertSame(['paid_invoices_missing_paid_at_scope_count' => 1], $data['warnings']);
        $facts = $this->facts(); $facts->summary_revenue = '19999999999.98';
        $facts->month_0_revenue = $facts->month_1_revenue = '9999999999.99';
        $this->assertSame('19999999999.98', (new HostDashboardResponse($this->period(), $facts))->jsonSerialize()['summary']['revenue_total']);
        $facts->summary_revenue = '0.49'; $facts->month_0_revenue = '0.00'; $facts->month_1_revenue = '0.49';
        $this->assertSame('0.49', (new HostDashboardResponse($this->period(), $facts))->jsonSerialize()['summary']['revenue_total']);
    }

    public static function invalidFacts(): array
    {
        return [['summary_revenue', 300.76], ['summary_revenue', '-1.00'], ['summary_revenue', '0300.76'],
            ['summary_revenue', '300.760'], ['summary_revenue', null], ['summary_revenue', '300.77'],
            ['month_0_revenue', null], ['missing_paid_at', -1], ['missing_paid_at', 1.0],
            ['missing_paid_at', '01'], ['missing_paid_at', '9007199254740992'], ['recent_json', '{}']];
    }

    #[DataProvider('invalidFacts')]
    public function test_malformed_financial_facts_fail_closed(string $field, mixed $value): void
    {
        $facts = $this->facts(); $facts->$field = $value;
        $this->expectException(\UnexpectedValueException::class);
        new HostDashboardResponse($this->period(), $facts);
    }
}
