<?php

namespace Tests\Feature\Dashboard;

use App\Models\User;
use App\Services\Dashboard\HostDashboardQueryService;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Tests\Support\Uc39MySqlTestCase;

final class Uc39RevenueQueryMySqlTest extends Uc39MySqlTestCase
{
    private const CUTOFF = '2026-11-21T05:00:00Z';

    private function stamp(string $time = '2026-01-01T00:00:00Z'): array
    {
        $epoch = (new DateTimeImmutable($time))->getTimestamp();
        $value = $this->app['db']->connection()->raw('FROM_UNIXTIME('.$epoch.')');
        return ['created_at' => $value, 'updated_at' => $value];
    }

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(self::CUTOFF);
        $db = $this->app['db']->connection();
        foreach ([[1, 'HOST'], [2, 'HOST'], [3, 'HOST'], [4, 'CUSTOMER']] as [$id, $role]) {
            $db->table('users')->insert(['id' => $id, 'name' => 'QA '.$id, 'email' => 'revenue'.$id.'@example.test',
                'password' => 'synthetic-not-a-login', 'role' => $role, 'status' => $id === 4 ? 'INACTIVE' : 'ACTIVE', ...$this->stamp()]);
        }
        foreach ([[101, 1, 'ACTIVE'], [102, 1, 'BLOCKED'], [103, 2, 'ACTIVE'], [104, 1, 'INACTIVE']] as [$id, $host, $status]) {
            $db->table('properties')->insert(['id' => $id, 'host_id' => $host, 'name' => 'Cơ sở '.$id,
                'type' => 'HOTEL', 'address' => 'QA', 'status' => $status,
                ...$this->stamp($id === 104 ? '2026-11-21T05:00:01Z' : '2026-01-01T00:00:00Z')]);
        }
        foreach ([[201, 101, 'ACTIVE'], [202, 102, 'INACTIVE'], [203, 103, 'MAINTENANCE'], [204, 104, 'ACTIVE'], [205, 101, 'ACTIVE']] as [$id, $property, $status]) {
            $db->table('room_types')->insert(['id' => $id, 'property_id' => $property, 'name' => 'QA', ...$this->stamp()]);
            $db->table('rooms')->insert(['id' => $id, 'property_id' => $property, 'room_type_id' => $id,
                'room_number' => (string) $id, 'name' => 'Phòng '.$id, 'capacity' => 2, 'price_per_night' => '100.00',
                'status' => $status, ...$this->stamp($id === 205 ? '2026-11-21T05:00:01Z' : '2026-01-01T00:00:00Z')]);
        }
        // Explicit negative/cohort fixtures: Invoice reporting must not recheck Booking eligibility.
        $specs = [
            [1, 201, 'PAID', '2026-09-30T17:00:00Z', '100.25'],
            [2, 202, 'PAID', '2026-10-31T17:00:00Z', '200.50'],
            [3, 201, 'PAID', self::CUTOFF, '0.01'],
            [4, 201, 'PAID', '2026-11-21T05:00:01Z', '999.99'],
            [5, 201, 'REFUNDED', '2026-10-15T00:00:00Z', '500.00'],
            [6, 201, 'PAID', null, '300.00'], [7, 201, 'UNPAID', '2026-10-10T00:00:00Z', '400.00'],
            [8, 203, 'PAID', '2026-10-15T00:00:00Z', '700.00'], [9, 203, 'PAID', null, '800.00'],
            [10, 204, 'PAID', '2026-10-15T00:00:00Z', '900.00'], [11, 204, 'PAID', null, '900.00'],
            [12, 205, 'PAID', '2026-10-15T00:00:00Z', '900.00'], [13, 205, 'PAID', null, '900.00'],
        ];
        foreach ($specs as [$id, $room, $status, $paid, $amount]) {
            $this->booking($id, $room, $id === 2 ? 'PENDING' : ($id === 3 ? 'CANCELLED' : 'COMPLETED'),
                $id === 2 ? '2026-11-21T05:00:01Z' : '2026-01-01T00:00:00Z');
            $db->table('invoices')->insert(['id' => $id, 'invoice_code' => 'QA-INV-'.$id, 'booking_id' => $id,
                'customer_name' => 'Private', 'customer_email' => 'private@example.test', 'customer_phone' => '000',
                'property_name' => 'Snapshot', 'room_name' => 'Snapshot', 'subtotal' => $amount, 'total_amount' => $amount,
                'payment_status' => $status, 'paid_at' => $paid === null ? null : $this->stamp($paid)['created_at'],
                ...$this->stamp($id === 1 || $id === 6 ? '2026-12-01T00:00:00Z' : '2026-01-01T00:00:00Z')]);
        }
        $this->booking(91, 201, 'PENDING', '2025-01-01T00:00:00Z'); // No Invoice, never revenue.
    }

    protected function tearDown(): void
    {
        try { parent::tearDown(); } finally { CarbonImmutable::setTestNow(); }
    }

    private function booking(int $id, int $room, string $status, string $time): void
    {
        $this->app['db']->connection()->table('bookings')->insert(['id' => $id, 'booking_code' => 'QA-REV-'.$id,
            'customer_id' => 4, 'room_id' => $room, 'check_in_date' => '2026-12-01', 'check_out_date' => '2026-12-02',
            'guest_count' => 1, 'number_of_nights' => 1, 'price_per_night' => '100.00', 'room_total' => '100.00',
            'status' => $status, ...$this->stamp($time)]);
    }

    private function overview(?string $from = '2026-10', ?string $to = '2026-11', int $actor = 1): array
    {
        return $this->app->make(HostDashboardQueryService::class)->getOverview(User::findOrFail($actor), $from, $to)->jsonSerialize();
    }

    private function fingerprint(): string
    {
        $rows = [];
        foreach (['users', 'properties', 'room_types', 'rooms', 'maintenances', 'bookings', 'booking_services', 'invoices', 'invoice_items'] as $table) {
            $rows[$table] = $this->app['db']->connection()->table($table)->orderBy('id')->get()->all();
        }
        return hash('sha256', serialize($rows));
    }

    public function test_full_oracle_one_select_same_snapshot_and_no_writes(): void
    {
        $db = $this->app['db']->connection();
        foreach (['invoices', 'bookings', 'rooms', 'properties', 'maintenances'] as $table) {
            $this->assertSame('InnoDB', $db->selectOne('SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table])->engine);
        }
        $before = $this->fingerprint(); $actor = User::findOrFail(1);
        $snapshot = $this->app->make(HostDashboardQueryService::class)->getSnapshot($actor)->jsonSerialize();
        $db->enableQueryLog(); $db->flushQueryLog();
        try {
            $data = $this->app->make(HostDashboardQueryService::class)->getOverview($actor, '2026-10', '2026-11')->jsonSerialize();
            $queries = $db->getQueryLog();
        } finally { $db->disableQueryLog(); }
        $this->assertCount(1, $queries);
        $this->assertStringStartsWith('select ', strtolower($queries[0]['query']));
        $this->assertSame('300.76', $data['summary']['revenue_total']);
        $this->assertSame(['100.25', '200.51'], array_column($data['revenue_by_month'], 'revenue_total'));
        $this->assertSame(1, $data['warnings']['paid_invoices_missing_paid_at_scope_count']);
        foreach (['scope', 'snapshot', 'recent_bookings', 'generated_at', 'report_timezone'] as $key) {
            $this->assertSame($snapshot[$key], $data[$key]);
        }
        $this->assertStringNotContainsString('private', json_encode($data, JSON_THROW_ON_ERROR));
        $this->assertSame($before, $this->fingerprint());
        file_put_contents(dirname(getenv('STAYHUB_UC39_MANIFEST')).'/revenue-oracle.json', json_encode([
            'expected_summary' => '300.76', 'actual_summary' => $data['summary']['revenue_total'],
            'expected_months' => ['100.25', '200.51'], 'actual_months' => array_column($data['revenue_by_month'], 'revenue_total'),
            'expected_warning' => 1, 'actual_warning' => $data['warnings']['paid_invoices_missing_paid_at_scope_count'],
            'reporting_statements' => count($queries), 'business_fingerprint_before' => $before, 'business_fingerprint_after' => $this->fingerprint(),
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
    }

    public function test_scope_empty_and_period_independent_warning(): void
    {
        $a = $this->overview();
        $this->app['db']->connection()->table('invoices')->where('id', 8)->update(['total_amount' => '9999999999.99']);
        $this->assertSame($a, $this->overview());
        $b = $this->overview(actor: 2);
        $this->assertSame('9999999999.99', $b['summary']['revenue_total']);
        $this->assertSame(1, $b['warnings']['paid_invoices_missing_paid_at_scope_count']);
        $future = $this->overview('2026-12', '2026-12');
        $this->assertSame('0.00', $future['summary']['revenue_total']);
        $this->assertSame(1, $future['warnings']['paid_invoices_missing_paid_at_scope_count']);
        $this->assertSame($a['snapshot'], $future['snapshot']);
        $this->assertSame($a['recent_bookings'], $future['recent_bookings']);
        $empty = $this->overview(actor: 3);
        $this->assertSame(['properties_count' => 0], $empty['scope']);
        $this->assertSame('0.00', $empty['summary']['revenue_total']);
        $this->assertSame(['0.00', '0.00'], array_column($empty['revenue_by_month'], 'revenue_total'));
        $this->assertSame(0, $empty['warnings']['paid_invoices_missing_paid_at_scope_count']);
        $db = $this->app['db']->connection();
        $db->table('properties')->insert(['id' => 109, 'host_id' => 3, 'name' => 'Empty', 'type' => 'HOTEL', 'address' => 'QA', ...$this->stamp()]);
        $this->assertSame(1, $this->overview(actor: 3)['scope']['properties_count']);
        $this->assertSame('0.00', $this->overview(actor: 3)['summary']['revenue_total']);
        $db->table('invoices')->where('id', 6)->update(['payment_status' => 'REFUNDED']);
        $this->assertSame(0, $this->overview()['warnings']['paid_invoices_missing_paid_at_scope_count']);
        $db->table('invoices')->where('id', 6)->update(['payment_status' => 'PAID']);
        $db->table('invoices')->where('id', '<>', 6)->delete();
        $onlyWarning = $this->overview();
        $this->assertSame('0.00', $onlyWarning['summary']['revenue_total']);
        $this->assertSame(1, $onlyWarning['warnings']['paid_invoices_missing_paid_at_scope_count']);
        $db->table('invoices')->delete();
        $withoutInvoices = $this->overview();
        $this->assertSame('0.00', $withoutInvoices['summary']['revenue_total']);
        $this->assertSame(0, $withoutInvoices['warnings']['paid_invoices_missing_paid_at_scope_count']);
        $this->assertSame($a['snapshot'], $withoutInvoices['snapshot']);
    }

    public function test_large_totals_items_services_do_not_multiply_and_refund_is_current_state(): void
    {
        $db = $this->app['db']->connection();
        $db->table('invoices')->where('id', 2)->update(['payment_status' => 'REFUNDED']);
        $this->assertSame('100.26', $this->overview()['summary']['revenue_total']);
        $db->table('invoices')->where('id', 3)->update(['payment_status' => 'UNPAID']);
        $db->table('invoices')->whereIn('id', [1, 2])->update(['payment_status' => 'PAID', 'total_amount' => '9999999999.99']);
        $db->table('services')->insert(['id' => 1, 'property_id' => 101, 'name' => 'QA', 'unit' => 'lần', 'price' => '1.00', 'status' => true, ...$this->stamp()]);
        foreach (range(1, 4) as $id) {
            $db->table('invoice_items')->insert(['id' => $id, 'invoice_id' => 1, 'item_type' => 'SERVICE', 'item_name' => 'QA',
                'quantity' => '1.00', 'unit_price' => '1.00', 'amount' => '1.00', ...$this->stamp()]);
            $db->table('booking_services')->insert(['id' => $id, 'booking_id' => 1, 'service_id' => 1, 'service_name' => 'QA', 'unit' => 'lần',
                'quantity' => '1.00', 'unit_price' => '1.00', 'amount' => '1.00', ...$this->stamp()]);
        }
        $data = $this->overview();
        $this->assertSame('19999999999.98', $data['summary']['revenue_total']);
        $this->assertSame(['9999999999.99', '9999999999.99'], array_column($data['revenue_by_month'], 'revenue_total'));
        $this->assertSame(1, $data['warnings']['paid_invoices_missing_paid_at_scope_count']);
    }

    public function test_period_boundaries_timezone_cutoff_and_default_zero_fill(): void
    {
        $db = $this->app['db']->connection();
        $october = $this->overview('2026-10', '2026-10');
        $this->assertSame('100.25', $october['summary']['revenue_total']);
        $db->table('invoices')->where('id', 1)->update(['paid_at' => $this->stamp('2026-09-30T16:59:59Z')['created_at']]);
        $this->assertSame('0.00', $this->overview('2026-10', '2026-10')['summary']['revenue_total']);
        $db->table('invoices')->where('id', 1)->update(['paid_at' => $this->stamp('2026-10-31T16:59:59Z')['created_at']]);
        $this->assertSame('100.25', $this->overview('2026-10', '2026-10')['summary']['revenue_total']);
        $before = $this->overview();
        try {
            $db->statement("SET SESSION time_zone = '+07:00'");
            $this->assertSame($before, $this->overview());
        } finally { $db->statement("SET SESSION time_zone = '+00:00'"); }
        $default = $this->overview(null, null);
        $this->assertCount(12, $default['revenue_by_month']);
        $this->assertSame('2025-12', $default['revenue_by_month'][0]['month']);
        $this->assertSame('300.76', $default['summary']['revenue_total']);
        foreach (array_slice($default['revenue_by_month'], 0, 10) as $row) { $this->assertSame('0.00', $row['revenue_total']); }
        $db->table('invoices')->where('id', 1)->update(['paid_at' => $this->stamp('2024-02-29T16:59:59Z')['created_at']]);
        $this->assertSame('100.25', $this->overview('2024-02', '2024-02')['summary']['revenue_total']);
        foreach (['0001-01', '9999-12'] as $month) { $this->assertSame('0.00', $this->overview($month, $month)['summary']['revenue_total']); }
    }

    public function test_current_ownership_and_ancestors_at_exact_cutoff(): void
    {
        $db = $this->app['db']->connection();
        $before = $this->overview();
        $db->table('properties')->where('id', 104)->update(['created_at' => $this->stamp(self::CUTOFF)['created_at']]);
        $this->assertSame('1200.76', $this->overview()['summary']['revenue_total']);
        $this->assertSame(2, $this->overview()['warnings']['paid_invoices_missing_paid_at_scope_count']);
        $db->table('rooms')->where('id', 205)->update(['created_at' => $this->stamp(self::CUTOFF)['created_at']]);
        $this->assertSame('2100.76', $this->overview()['summary']['revenue_total']);
        $this->assertSame(3, $this->overview()['warnings']['paid_invoices_missing_paid_at_scope_count']);
        $db->table('properties')->whereIn('id', [101, 102, 104])->update(['host_id' => 2]);
        $this->assertSame('0.00', $this->overview()['summary']['revenue_total']);
        $this->assertSame(0, $this->overview()['warnings']['paid_invoices_missing_paid_at_scope_count']);
        $this->assertSame('2800.76', $this->overview(actor: 2)['summary']['revenue_total']);
        $this->assertSame(4, $this->overview(actor: 2)['warnings']['paid_invoices_missing_paid_at_scope_count']);
        $this->assertSame('300.76', $before['summary']['revenue_total']);
    }
}
