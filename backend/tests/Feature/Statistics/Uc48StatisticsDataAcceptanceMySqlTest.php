<?php

namespace Tests\Feature\Statistics;

use App\Models\User;
use Carbon\CarbonImmutable;
use Tests\Support\Uc48MySqlTestCase;
use Tests\Support\Uc48StatisticsFixtures as Fixtures;

/** Synthetic snapshots exercise reporting, not the TV2 booking/payment workflow. */
final class Uc48StatisticsDataAcceptanceMySqlTest extends Uc48MySqlTestCase
{
    private const STATUSES = ['PENDING', 'CONFIRMED', 'CHECKED_IN', 'COMPLETED', 'CANCELLED', 'REJECTED'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['db']->connection()->statement('SET SESSION time_zone = ?', ['+00:00']);
        CarbonImmutable::setTestNow('2026-11-19T05:00:00Z');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function fingerprint(): string
    {
        $rows = [];
        foreach (['users', 'properties', 'room_types', 'rooms', 'bookings', 'invoices', 'invoice_items', 'services', 'booking_services'] as $table) {
            $rows[$table] = $this->app['db']->table($table)->orderBy('id')->get()->all();
        }
        return hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
    }

    private function read(array $base, string $from = '2026-10', string $to = '2026-11'): array
    {
        $before = $this->fingerprint();
        $response = $this->actingAs(User::findOrFail($base['actors']['admin']))
            ->getJson('/api/admin/statistics?'.http_build_query(['from_month' => $from, 'to_month' => $to]))
            ->assertOk();
        $this->assertSame($before, $this->fingerprint(), 'Reporting must not write business rows.');
        $this->assertSame(['data'], array_keys($response->json()));
        $data = $response->json('data');
        $this->assertSame(['period', 'report_timezone', 'generated_at', 'summary', 'booking_status_counts', 'monthly', 'warnings'], array_keys($data));
        $this->assertSame('Asia/Ho_Chi_Minh', $data['report_timezone']);
        $this->assertSame('2026-11-19T05:00:00.000000Z', $data['generated_at']);
        $this->assertSame(self::STATUSES, array_column($data['booking_status_counts'], 'status'));
        $counts = array_fill(0, 6, 0);
        $bookingSum = 0;
        $revenueSum = '0.00';
        foreach ($data['monthly'] as $month) {
            $this->assertSame(self::STATUSES, array_column($month['booking_status_counts'], 'status'));
            $this->assertIsInt($month['bookings_count']);
            $this->assertMatchesRegularExpression('/\A(?:0|[1-9][0-9]*)\.[0-9]{2}\z/', $month['revenue_total']);
            $this->assertSame($month['bookings_count'], array_sum(array_column($month['booking_status_counts'], 'count')));
            foreach ($month['booking_status_counts'] as $i => $status) {
                $this->assertIsInt($status['count']);
                $this->assertGreaterThanOrEqual(0, $status['count']);
                $counts[$i] += $status['count'];
            }
            $bookingSum += $month['bookings_count'];
            $revenueSum = bcadd($revenueSum, $month['revenue_total'], 2);
        }
        $this->assertSame($bookingSum, $data['summary']['bookings_count']);
        $this->assertSame($revenueSum, $data['summary']['revenue_total']);
        $this->assertSame($counts, array_column($data['booking_status_counts'], 'count'));
        // The exact projection above excludes snapshots/PII, not merely selected example values.
        $this->assertSame(['paid_invoices_missing_paid_at_global_count'], array_keys($data['warnings']));
        return $data;
    }

    private function multiProperty(): array
    {
        $base = Fixtures::base($this->app, $this->uc48Manifest);
        $db = $this->app['db']->connection();
        $host = (array) $db->table('users')->where('id', $base['actors']['host'])->first();
        unset($host['id']);
        $host['email'] = 'second-host@uc48.example.test';
        $host['status'] = 'ACTIVE';
        $hostId = $db->table('users')->insertGetId($host);
        $property = (array) $db->table('properties')->where('id', $base['property'])->first();
        unset($property['id']);
        $property['host_id'] = $hostId;
        $property['status'] = 'ACTIVE';
        $propertyId = $db->table('properties')->insertGetId($property);
        $typeId = $db->table('room_types')->insertGetId(['property_id' => $propertyId, 'name' => 'Second type']);
        $room = (array) $db->table('rooms')->where('id', $base['room'])->first();
        unset($room['id']);
        $room['property_id'] = $propertyId;
        $room['room_type_id'] = $typeId;
        $room['status'] = 'ACTIVE';
        $second = array_replace($base, ['property' => $propertyId, 'room' => $db->table('rooms')->insertGetId($room)]);

        $bookings = [];
        foreach (self::STATUSES as $i => $status) {
            $bookings[] = Fixtures::booking($this->app, $i % 2 === 0 ? $base : $second,
                $i < 3 ? '2026-10-05 00:00:00' : '2026-11-05 00:00:00', $status);
        }
        $paid = Fixtures::invoice($this->app, $bookings[0], '2026-10-10 00:00:00', '10.01');
        Fixtures::invoice($this->app, $bookings[1], '2026-10-10 00:00:00', '20.02', 'UNPAID');
        Fixtures::invoice($this->app, $bookings[2], '2026-10-10 00:00:00', '30.03', 'REFUNDED');
        $refundable = Fixtures::invoice($this->app, $bookings[3], '2026-11-10 00:00:00', '40.04');
        Fixtures::invoice($this->app, $bookings[4], null, '50.05');
        Fixtures::invoice($this->app, $bookings[5], '2026-11-19 05:00:01', '60.06');
        // A non-COMPLETED booking created outside the period still contributes its paid invoice.
        $outside = Fixtures::booking($this->app, $second, '2026-09-01 00:00:00', 'PENDING');
        Fixtures::invoice($this->app, $outside, '2026-11-10 00:00:00', '70.07');
        return $base + compact('bookings', 'paid', 'refundable');
    }

    public function test_global_http_totals_include_both_properties_and_independent_invoice_cohorts(): void
    {
        $base = $this->multiProperty();
        $data = $this->read($base);
        // Hand-calculated ledger: 10.01 + 40.04 + 70.07 = 120.12.
        $this->assertSame(['bookings_count' => 6, 'revenue_total' => '120.12'], $data['summary']);
        $this->assertSame(['2026-10', '2026-11'], array_column($data['monthly'], 'month'));
        $this->assertSame([3, 3], array_column($data['monthly'], 'bookings_count'));
        $this->assertSame(['10.01', '110.11'], array_column($data['monthly'], 'revenue_total'));
        $this->assertSame([1, 1, 1, 1, 1, 1], array_column($data['booking_status_counts'], 'count'));
        $this->assertSame(1, $data['warnings']['paid_invoices_missing_paid_at_global_count']);
    }

    public function test_current_states_change_their_metrics_but_catalog_and_item_edits_do_not(): void
    {
        $base = $this->multiProperty();
        $before = $this->read($base);
        $db = $this->app['db']->connection();
        $db->table('rooms')->update(['price_per_night' => '999999.99']);
        $db->table('invoice_items')->update(['unit_price' => '0.01', 'amount' => '0.01']);
        $this->assertSame($before, $this->read($base), 'Only stored invoice headers determine revenue.');
        $db->table('invoices')->where('id', $base['refundable'])->update(['payment_status' => 'REFUNDED']);
        $db->table('bookings')->where('id', $base['bookings'][0])->update(['status' => 'REJECTED']);
        $after = $this->read($base);
        $this->assertSame(['bookings_count' => 6, 'revenue_total' => '80.08'], $after['summary']);
        $this->assertSame(['10.01', '70.07'], array_column($after['monthly'], 'revenue_total'));
        $this->assertSame([0, 1, 1, 1, 1, 2], array_column($after['booking_status_counts'], 'count'));
        $this->assertSame(1, $after['warnings']['paid_invoices_missing_paid_at_global_count']);
    }

    public function test_revenue_only_http_report_preserves_large_decimal_headers_with_many_items_and_services(): void
    {
        $base = Fixtures::base($this->app, $this->uc48Manifest);
        $db = $this->app['db']->connection();
        $service = $db->table('services')->insertGetId(['property_id' => $base['property'], 'name' => 'Synthetic', 'unit' => 'Lần', 'price' => '1.00']);
        for ($i = 0; $i < 2; $i++) {
            $booking = Fixtures::booking($this->app, $base, '2026-09-01 00:00:00');
            $invoice = Fixtures::invoice($this->app, $booking, '2026-10-01 00:00:00', '9999999999.99');
            for ($j = 0; $j < 3; $j++) {
                $db->table('invoice_items')->insert(['invoice_id' => $invoice, 'item_type' => 'SERVICE', 'item_name' => 'Synthetic', 'quantity' => '1.00', 'unit_price' => '1.00', 'amount' => '1.00']);
                $db->table('booking_services')->insert(['booking_id' => $booking, 'service_id' => $service, 'service_name' => 'Synthetic', 'unit' => 'Lần', 'quantity' => '1.00', 'unit_price' => '1.00', 'amount' => '1.00']);
            }
        }
        $data = $this->read($base);
        $this->assertSame(['bookings_count' => 0, 'revenue_total' => '19999999999.98'], $data['summary']);
        $this->assertSame(['19999999999.98', '0.00'], array_column($data['monthly'], 'revenue_total'));
        $this->assertSame([0, 0, 0, 0, 0, 0], array_column($data['booking_status_counts'], 'count'));
    }

    public function test_booking_only_sparse_year_and_global_warning_are_independent(): void
    {
        $base = Fixtures::base($this->app, $this->uc48Manifest);
        Fixtures::booking($this->app, $base, '2025-12-01 00:00:00', 'CHECKED_IN');
        Fixtures::booking($this->app, $base, '2026-11-01 00:00:00', 'CANCELLED');
        $outside = Fixtures::booking($this->app, $base, '2025-01-01 00:00:00');
        Fixtures::invoice($this->app, $outside, null, '9.99');
        $data = $this->read($base, '2025-12', '2026-11');
        $this->assertSame(['bookings_count' => 2, 'revenue_total' => '0.00'], $data['summary']);
        $this->assertSame(['2025-12', '2026-01', '2026-02', '2026-03', '2026-04', '2026-05', '2026-06', '2026-07', '2026-08', '2026-09', '2026-10', '2026-11'], array_column($data['monthly'], 'month'));
        $this->assertSame([1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1], array_column($data['monthly'], 'bookings_count'));
        $this->assertSame(1, $data['warnings']['paid_invoices_missing_paid_at_global_count']);
        $future = $this->read($base, '2026-12', '2026-12');
        $this->assertSame(['bookings_count' => 0, 'revenue_total' => '0.00'], $future['summary']);
        $this->assertSame(1, $future['warnings']['paid_invoices_missing_paid_at_global_count']);
    }
}
