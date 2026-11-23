<?php

namespace Tests\Feature\Statistics;

use App\DTOs\Statistics\Requests\AdminStatisticsQueryInputRequest;
use App\Models\User;
use App\Services\Statistics\AdminStatisticsQueryService;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Uc48MySqlTestCase;
use Tests\Support\Uc48StatisticsFixtures as Fixtures;

final class Uc48StatisticsQueryMySqlTest extends Uc48MySqlTestCase
{
    private ?string $originalTimezone = null;

    protected function setUp(): void
    {
        parent::setUp();
        $db = $this->app['db']->connection();
        $this->originalTimezone = $db->selectOne('SELECT @@session.time_zone AS zone')->zone;
        $db->statement('SET SESSION time_zone = ?', ['+00:00']);
        CarbonImmutable::setTestNow('2026-11-19T05:00:00Z');
    }

    protected function tearDown(): void
    {
        try {
            CarbonImmutable::setTestNow();
            if (isset($this->app) && $this->originalTimezone !== null) {
                $this->app['db']->connection()->statement('SET SESSION time_zone = ?', [$this->originalTimezone]);
            }
        } finally { parent::tearDown(); }
    }

    private function report(array $base, string $from = '2026-10', string $to = '2026-11'): array
    {
        $actor = User::findOrFail($base['actors']['admin']);
        return $this->app->make(AdminStatisticsQueryService::class)->getStatistics($actor,
            new AdminStatisticsQueryInputRequest(['from_month' => $from, 'to_month' => $to]))->jsonSerialize();
    }

    private function businessFingerprint(): string
    {
        $snapshot = [];
        foreach (['users', 'properties', 'room_types', 'rooms', 'bookings', 'invoices', 'invoice_items', 'services', 'booking_services', 'booking_commissions'] as $table) {
            $snapshot[$table] = $this->app['db']->connection()->table($table)->orderBy('id')->get()->all();
        }
        return hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
    }

    public function test_contract_oracle_and_single_readonly_statement(): void
    {
        $base = Fixtures::contract($this->app, $this->uc48Manifest);
        $before = $this->businessFingerprint();
        $actor = User::findOrFail($base['actors']['admin']);
        $service = $this->app->make(AdminStatisticsQueryService::class);
        $db = $this->app['db']->connection();
        $db->flushQueryLog(); $db->enableQueryLog();
        try {
            $data = $service->getStatistics($actor, new AdminStatisticsQueryInputRequest(['from_month' => '2026-10', 'to_month' => '2026-11']))->jsonSerialize();
            $queries = $db->getQueryLog();
        } finally { $db->disableQueryLog(); $db->flushQueryLog(); }
        $this->assertCount(1, $queries);
        $this->assertStringStartsWith('select ', strtolower($queries[0]['query']));
        $this->assertSame($before, $this->businessFingerprint());
        $this->assertSame(['from_month' => '2026-10', 'to_month' => '2026-11', 'start_at' => '2026-09-30T17:00:00.000000Z',
            'end_at_exclusive' => '2026-11-30T17:00:00.000000Z'], $data['period']);
        $this->assertSame('2026-11-19T05:00:00.000000Z', $data['generated_at']);
        $this->assertSame(['bookings_count' => 4, 'revenue_total' => '300.76', 'commission_revenue_total' => '0.00'], $data['summary']);
        $this->assertSame([1, 1, 0, 1, 1, 0], array_column($data['booking_status_counts'], 'count'));
        $this->assertSame(['2026-10', '2026-11'], array_column($data['monthly'], 'month'));
        $this->assertSame([2, 2], array_column($data['monthly'], 'bookings_count'));
        $this->assertSame(['100.25', '200.51'], array_column($data['monthly'], 'revenue_total'));
        $this->assertSame([0, 0, 0, 1, 1, 0], array_column($data['monthly'][0]['booking_status_counts'], 'count'));
        $this->assertSame([1, 1, 0, 0, 0, 0], array_column($data['monthly'][1]['booking_status_counts'], 'count'));
        $this->assertSame(['paid_invoices_missing_paid_at_global_count' => 1, 'settled_commissions_missing_settled_at_global_count' => 0], $data['warnings']);
    }

    public function test_current_refund_and_booking_status_change_affect_only_their_metrics(): void
    {
        $base = Fixtures::contract($this->app, $this->uc48Manifest);
        $db = $this->app['db']->connection();
        $db->table('invoices')->where('id', $base['invoices'][1])->update(['payment_status' => 'REFUNDED']);
        $data = $this->report($base);
        $this->assertSame(['bookings_count' => 4, 'revenue_total' => '100.26', 'commission_revenue_total' => '0.00'], $data['summary']);
        $this->assertSame(['100.25', '0.01'], array_column($data['monthly'], 'revenue_total'));
        $db->table('bookings')->where('id', $base['bookings'][2])->update(['status' => 'REJECTED']);
        $changed = $this->report($base);
        $this->assertSame($data['summary'], $changed['summary']);
        $this->assertSame([0, 1, 0, 1, 1, 1], array_column($changed['booking_status_counts'], 'count'));
    }

    public function test_large_decimal_headers_are_not_multiplied_by_items_or_services(): void
    {
        $base = Fixtures::base($this->app, $this->uc48Manifest);
        $db = $this->app['db']->connection();
        $service = $db->table('services')->insertGetId(['property_id' => $base['property'], 'name' => 'UC48 extra', 'unit' => 'Lần', 'price' => '0.00']);
        for ($i = 0; $i < 2; $i++) {
            $booking = Fixtures::booking($this->app, $base, '2026-09-01 00:00:00', 'COMPLETED', '9999999999.99');
            $invoice = Fixtures::invoice($this->app, $booking, '2026-10-01 00:00:00', '9999999999.99');
            for ($j = 0; $j < 3; $j++) {
                $db->table('invoice_items')->insert(['invoice_id' => $invoice, 'item_type' => 'SERVICE', 'item_name' => 'Synthetic extra', 'quantity' => '1.00', 'unit_price' => '0.00', 'amount' => '0.00']);
                $db->table('booking_services')->insert(['booking_id' => $booking, 'service_id' => $service, 'service_name' => 'Synthetic extra', 'unit' => 'Lần', 'quantity' => '1.00', 'unit_price' => '0.00', 'amount' => '0.00']);
            }
        }
        $this->assertSame(['bookings_count' => 0, 'revenue_total' => '19999999999.98', 'commission_revenue_total' => '0.00'], $this->report($base)['summary']);
    }

    public function test_zero_and_missing_date_count_are_independent(): void
    {
        $base = Fixtures::base($this->app, $this->uc48Manifest);
        $zero = $this->report($base);
        $this->assertSame(['bookings_count' => 0, 'revenue_total' => '0.00', 'commission_revenue_total' => '0.00'], $zero['summary']);
        foreach ($zero['monthly'] as $month) { $this->assertSame([0, 0, 0, 0, 0, 0], array_column($month['booking_status_counts'], 'count')); }
        $booking = Fixtures::booking($this->app, $base, '2026-09-01 00:00:00');
        Fixtures::invoice($this->app, $booking, null, '1.00');
        $warning = $this->report($base);
        $this->assertSame($zero['summary'], $warning['summary']);
        $this->assertSame(1, $warning['warnings']['paid_invoices_missing_paid_at_global_count']);
    }

    public static function outsidePeriods(): array
    {
        return [['0001-01', '0001-12'], ['9999-01', '9999-12'], ['1969-01', '1969-12'],
            ['2038-02', '2038-12'], ['2026-12', '2026-12']];
    }

    #[DataProvider('outsidePeriods')]
    public function test_extreme_and_future_periods_zero_fill_but_keep_global_count(string $from, string $to): void
    {
        $base = Fixtures::contract($this->app, $this->uc48Manifest);
        $data = $this->report($base, $from, $to);
        $this->assertSame(['bookings_count' => 0, 'revenue_total' => '0.00', 'commission_revenue_total' => '0.00'], $data['summary']);
        $this->assertSame($from, $data['monthly'][0]['month']);
        $this->assertSame($to, $data['monthly'][array_key_last($data['monthly'])]['month']);
        $this->assertSame(1, $data['warnings']['paid_invoices_missing_paid_at_global_count']);
        foreach ($data['monthly'] as $month) {
            $this->assertSame(0, $month['bookings_count']); $this->assertSame('0.00', $month['revenue_total']);
            $this->assertSame([0, 0, 0, 0, 0, 0], array_column($month['booking_status_counts'], 'count'));
        }
    }

    public function test_settled_commissions_follow_vietnam_month_boundaries_and_cutoff_in_any_session_timezone(): void
    {
        $base = Fixtures::base($this->app, $this->uc48Manifest);
        $acceptance = Fixtures::commissionAcceptance($this->app, $base['actors']['host']);
        foreach ([
            ['2026-09-30 16:59:59', '999.99', 'SETTLED'],
            ['2026-09-30 17:00:00', '0.10', 'SETTLED'],
            ['2026-10-31 16:59:59', '10.15', 'SETTLED'],
            ['2026-10-31 17:00:00', '5.46', 'SETTLED'],
            ['2026-11-19 05:00:00', '0.04', 'SETTLED'],
            ['2026-11-19 05:00:01', '999.99', 'SETTLED'],
            ['2026-11-10 00:00:00', '999.99', 'DUE'],
            [null, '999.99', 'SETTLED'],
            [null, '999.99', 'AWAITING_CUSTOMER_PAYMENT'],
        ] as [$time, $amount, $status]) {
            Fixtures::commission($this->app, $base, $acceptance, $time, $amount, $status);
        }
        $before = $this->businessFingerprint();
        foreach (['+00:00', '+07:00', '-05:00'] as $zone) {
            $this->app['db']->connection()->statement('SET SESSION time_zone = ?', [$zone]);
            $data = $this->report($base, '2026-10', '2026-12');
            $this->assertSame(['bookings_count' => 0, 'revenue_total' => '0.00', 'commission_revenue_total' => '15.75'], $data['summary']);
            $this->assertSame(['10.25', '5.50', '0.00'], array_column($data['monthly'], 'commission_revenue_total'));
            $this->assertSame(1, $data['warnings']['settled_commissions_missing_settled_at_global_count']);
        }
        $this->app['db']->connection()->statement('SET SESSION time_zone = ?', ['+00:00']);
        $this->assertSame($before, $this->businessFingerprint());
    }

    public function test_connection_timezone_does_not_change_the_same_stored_instants(): void
    {
        $base = Fixtures::contract($this->app, $this->uc48Manifest);
        $expected = $this->report($base);
        foreach (['+07:00', '-05:00', '+00:00'] as $zone) {
            $this->app['db']->connection()->statement('SET SESSION time_zone = ?', [$zone]);
            $this->assertSame($expected, $this->report($base));
        }
    }

    public function test_end_boundary_is_excluded_even_after_cutoff_has_passed_it(): void
    {
        $base = Fixtures::base($this->app, $this->uc48Manifest);
        foreach (['2026-10-31 16:59:59', '2026-10-31 17:00:00'] as $time) {
            $booking = Fixtures::booking($this->app, $base, $time);
            Fixtures::invoice($this->app, $booking, $time, '0.01');
        }
        $this->assertSame(['bookings_count' => 1, 'revenue_total' => '0.01', 'commission_revenue_total' => '0.00'], $this->report($base, '2026-10', '2026-10')['summary']);
    }

    public function test_leap_day_and_all_six_statuses_use_created_not_stay_dates(): void
    {
        $base = Fixtures::base($this->app, $this->uc48Manifest);
        foreach (['PENDING', 'CONFIRMED', 'CHECKED_IN', 'COMPLETED', 'CANCELLED', 'REJECTED'] as $status) {
            Fixtures::booking($this->app, $base, '2024-02-29 16:59:59', $status);
        }
        Fixtures::booking($this->app, $base, '2024-02-29 17:00:00');
        $data = $this->report($base, '2024-02', '2024-02');
        $this->assertSame(['bookings_count' => 6, 'revenue_total' => '0.00', 'commission_revenue_total' => '0.00'], $data['summary']);
        $this->assertSame([1, 1, 1, 1, 1, 1], array_column($data['booking_status_counts'], 'count'));
    }

    public function test_cutoff_microseconds_and_pre_epoch_periods_do_not_coerce_boundaries(): void
    {
        $base = Fixtures::base($this->app, $this->uc48Manifest);
        foreach (['2026-11-19 05:00:00', '2026-11-19 05:00:01'] as $time) {
            $booking = Fixtures::booking($this->app, $base, $time);
            Fixtures::invoice($this->app, $booking, $time, '0.01');
        }
        CarbonImmutable::setTestNow('2026-11-19T05:00:00.999999Z');
        $this->assertSame(['bookings_count' => 1, 'revenue_total' => '0.01', 'commission_revenue_total' => '0.00'], $this->report($base)['summary']);
        $this->assertSame(['bookings_count' => 0, 'revenue_total' => '0.00', 'commission_revenue_total' => '0.00'], $this->report($base, '1970-01', '1970-01')['summary']);
    }

    public function test_timestamp_storage_edges_are_compared_against_numeric_period_boundaries(): void
    {
        $base = Fixtures::base($this->app, $this->uc48Manifest);
        foreach ([['1970-01-01 00:00:01', '0.01'], ['2038-01-19 03:14:07', '0.02']] as [$time, $amount]) {
            $booking = Fixtures::booking($this->app, $base, $time, 'PENDING', $amount);
            Fixtures::invoice($this->app, $booking, $time, $amount);
        }
        $this->assertSame(['bookings_count' => 1, 'revenue_total' => '0.01', 'commission_revenue_total' => '0.00'], $this->report($base, '1969-12', '1970-01')['summary']);
        CarbonImmutable::setTestNow('2039-01-20T00:00:00Z');
        $this->assertSame(['bookings_count' => 1, 'revenue_total' => '0.02', 'commission_revenue_total' => '0.00'], $this->report($base, '2038-01', '2038-01')['summary']);
    }

    public function test_default_period_uses_the_same_database_facts_and_clock(): void
    {
        $base = Fixtures::contract($this->app, $this->uc48Manifest);
        $actor = User::findOrFail($base['actors']['admin']);
        $data = $this->app->make(AdminStatisticsQueryService::class)->getStatistics($actor, new AdminStatisticsQueryInputRequest())->jsonSerialize();
        $this->assertSame('2025-12', $data['period']['from_month']);
        $this->assertSame('2026-11', $data['period']['to_month']);
        $this->assertCount(12, $data['monthly']);
        $this->assertSame(['bookings_count' => 6, 'revenue_total' => '300.76', 'commission_revenue_total' => '0.00'], $data['summary']);
        $this->assertSame([1, 1, 0, 3, 1, 0], array_column($data['booking_status_counts'], 'count'));
    }
}
