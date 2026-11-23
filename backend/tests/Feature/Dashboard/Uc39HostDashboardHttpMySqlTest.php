<?php

namespace Tests\Feature\Dashboard;

use App\Models\User;
use App\Services\Dashboard\HostDashboardQueryService;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\Uc39MySqlTestCase;

final class Uc39HostDashboardHttpMySqlTest extends Uc39MySqlTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-11-21T05:00:00Z');
        $db = $this->app['db']->connection();
        foreach ([[1, 'HOST', 'ACTIVE'], [2, 'HOST', 'ACTIVE'], [3, 'HOST', 'ACTIVE'],
            [4, 'CUSTOMER', 'ACTIVE'], [5, 'ADMIN', 'ACTIVE'], [6, 'HOST', 'BLOCKED'], [7, 'HOST', 'INACTIVE']] as [$id, $role, $status]) {
            $db->table('users')->insert(['id' => $id, 'name' => 'CORE QA '.$id, 'email' => 'core'.$id.'@example.test',
                'password' => 'synthetic-not-a-login', 'role' => $role, 'status' => $status, ...$this->stamp()]);
        }
        foreach ([[101, 1, 'ACTIVE'], [102, 1, 'BLOCKED'], [103, 2, 'ACTIVE']] as [$id, $host, $status]) {
            $db->table('properties')->insert(['id' => $id, 'host_id' => $host, 'name' => 'Cơ sở '.$id,
                'type' => 'HOTEL', 'address' => 'QA', 'status' => $status, ...$this->stamp()]);
            $db->table('room_types')->insert(['id' => $id, 'property_id' => $id, 'name' => 'QA', ...$this->stamp()]);
            $db->table('rooms')->insert(['id' => $id + 100, 'property_id' => $id, 'room_type_id' => $id,
                'room_number' => (string) $id, 'name' => 'Phòng '.$id, 'capacity' => 2,
                'price_per_night' => '100.00', 'status' => $id === 102 ? 'INACTIVE' : 'ACTIVE', ...$this->stamp()]);
        }
        $specs = [
            [301, 201, 'PENDING', '2026-11-20T00:00:00Z', 'PAID', '2026-09-30T17:00:00Z', '100.25'],
            [302, 202, 'CONFIRMED', '2026-11-20T00:00:00Z', 'PAID', '2026-10-31T17:00:00Z', '200.50'],
            [303, 201, 'COMPLETED', '2026-11-21T05:00:00Z', 'PAID', '2026-11-21T05:00:00Z', '0.01'],
            [304, 201, 'CANCELLED', '2026-11-19T00:00:00Z', 'PAID', null, '300.00'],
            [305, 201, 'REJECTED', '2026-11-18T00:00:00Z', 'REFUNDED', '2026-10-15T00:00:00Z', '500.00'],
            [306, 201, 'CHECKED_IN', '2026-11-17T00:00:00Z', 'UNPAID', null, '400.00'],
            [307, 201, 'PENDING', '2026-11-16T00:00:00Z', 'PAID', '2026-11-21T05:00:01Z', '999.99'],
            [308, 201, 'PENDING', '2026-11-21T05:00:01Z', null, null, '0.00'],
            [309, 203, 'PENDING', '2026-11-21T04:00:00Z', 'PAID', '2026-10-15T00:00:00Z', '9999.99'],
            [310, 203, 'COMPLETED', '2026-11-20T00:00:00Z', 'PAID', null, '888.00'],
        ];
        foreach ($specs as [$id, $room, $status, $created, $payment, $paid, $amount]) {
            $db->table('bookings')->insert(['id' => $id, 'booking_code' => 'CORE-'.$id, 'customer_id' => 4, 'room_id' => $room,
                'check_in_date' => '2026-12-01', 'check_out_date' => '2026-12-02', 'guest_count' => 1,
                'number_of_nights' => 1, 'price_per_night' => '100.00', 'room_total' => '100.00',
                'status' => $status, 'note' => 'PRIVATE_CORE_SENTINEL', ...$this->stamp($created)]);
            if ($payment !== null) {
                $db->table('invoices')->insert(['id' => $id, 'invoice_code' => 'CORE-INV-'.$id, 'booking_id' => $id,
                    'customer_name' => 'PRIVATE_CORE_SENTINEL', 'customer_email' => 'private@example.test', 'customer_phone' => '000',
                    'property_name' => 'Snapshot', 'room_name' => 'Snapshot', 'subtotal' => $amount, 'total_amount' => $amount,
                    'payment_status' => $payment, 'paid_at' => $paid === null ? null : $this->stamp($paid)['created_at'], ...$this->stamp()]);
            }
        }
        foreach ([401, 402] as $id) {
            $db->table('maintenances')->insert(['id' => $id, 'room_id' => 201, 'start_date' => '2025-01-01',
                'end_date' => '2025-01-02', 'status' => 'IN_PROGRESS', ...$this->stamp()]);
        }
    }

    protected function tearDown(): void
    {
        try { parent::tearDown(); } finally { CarbonImmutable::setTestNow(); }
    }

    private function stamp(string $value = '2026-01-01T00:00:00Z'): array
    {
        $time = $this->app['db']->connection()->raw('FROM_UNIXTIME('.(new DateTimeImmutable($value))->getTimestamp().')');
        return ['created_at' => $time, 'updated_at' => $time];
    }

    private function requestAs(?int $id, string $query = '', array $body = []): TestResponse
    {
        if ($id !== null) {
            // Each simulated HTTP request gets a fresh Sanctum guard, as a real request does.
            $this->app['auth']->forgetGuards();
            $this->actingAs(User::findOrFail($id), 'web');
        }
        return $this->call('GET', '/api/host/dashboard'.($query === '' ? '' : '?'.$query),
            server: ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'],
            content: json_encode($body, JSON_THROW_ON_ERROR));
    }

    private function fingerprint(): string
    {
        $rows = [];
        foreach (['users', 'properties', 'room_types', 'rooms', 'maintenances', 'bookings', 'booking_services', 'invoices', 'invoice_items'] as $table) {
            $rows[$table] = $this->app['db']->connection()->table($table)->orderBy('id')->get()->all();
        }
        return hash('sha256', serialize($rows));
    }

    public function test_route_http_oracle_no_writes_and_one_reporting_select(): void
    {
        $route = $this->app['router']->getRoutes()->getByName('host.dashboard.show');
        $this->assertSame('api/host/dashboard', $route->uri());
        $this->assertSame(['GET', 'HEAD'], $route->methods());
        $this->assertContains('auth:sanctum', $route->gatherMiddleware());
        $this->assertContains('account.active', $route->gatherMiddleware());
        $before = $this->fingerprint();
        $this->actingAs(User::findOrFail(1), 'web');
        $db = $this->app['db']->connection(); $db->enableQueryLog(); $db->flushQueryLog();
        try {
            $response = $this->requestAs(null, 'from_month=2026-10&to_month=2026-11');
            $queries = $db->getQueryLog();
        } finally { $db->disableQueryLog(); }
        $response->assertOk()->assertJsonPath('data.summary.revenue_total', '300.76')
            ->assertJsonPath('data.scope.properties_count', 2)->assertJsonPath('data.snapshot.rooms_count', 2)
            ->assertJsonPath('data.snapshot.pending_bookings_count', 2)
            ->assertJsonPath('data.snapshot.in_progress_maintenances_count', 2)
            ->assertJsonPath('data.warnings.paid_invoices_missing_paid_at_scope_count', 1);
        $this->assertSame(['data'], array_keys($response->json()));
        $data = $response->json('data');
        $this->assertSame(['100.25', '200.51'], array_column($data['revenue_by_month'], 'revenue_total'));
        $this->assertSame(['303', '302', '301', '304', '305'], array_column($data['recent_bookings'], 'id'));
        $this->assertSame(['id', 'booking_code', 'status', 'created_at', 'check_in_date', 'check_out_date', 'property', 'room'], array_keys($data['recent_bookings'][0]));
        $this->assertSame('2026-09-30T17:00:00.000000Z', $data['period']['start_at']);
        $this->assertSame('2026-11-30T17:00:00.000000Z', $data['period']['end_at_exclusive']);
        $this->assertSame('2026-11-21T05:00:00.000000Z', $data['generated_at']);
        $reporting = array_filter($queries, static fn ($q) => str_contains($q['query'], 'invoices'));
        $this->assertCount(1, $reporting);
        foreach ($queries as $q) { $this->assertMatchesRegularExpression('/\Aselect /i', $q['query']); }
        $this->assertStringNotContainsString('PRIVATE_CORE_SENTINEL', $response->getContent());
        $this->assertStringNotContainsString('customer_', $response->getContent());
        $this->assertSame($before, $this->fingerprint());
        // Synthetic public projection only, for checking the real frontend mapper against this HTTP response.
        file_put_contents(dirname(getenv('STAYHUB_UC39_MANIFEST')).'/http-response.json', $response->getContent());
        file_put_contents(dirname(getenv('STAYHUB_UC39_MANIFEST')).'/http-oracle.json', json_encode([
            'expected_summary' => '300.76', 'actual_summary' => $data['summary']['revenue_total'],
            'expected_months' => ['100.25', '200.51'], 'actual_months' => array_column($data['revenue_by_month'], 'revenue_total'),
            'reporting_selects' => count($reporting), 'fingerprint_before' => $before, 'fingerprint_after' => $this->fingerprint(),
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
    }

    public static function deniedActors(): array
    {
        return [[null, 401, null], [4, 403, 'HOST_DASHBOARD_FORBIDDEN'], [5, 403, 'HOST_DASHBOARD_FORBIDDEN'],
            [6, 403, 'ACCOUNT_BLOCKED'], [7, 403, 'ACCOUNT_INACTIVE']];
    }

    #[DataProvider('deniedActors')]
    public function test_permission_precedes_invalid_raw_query(?int $actor, int $status, ?string $code): void
    {
        $db = $this->app['db']->connection(); $db->enableQueryLog(); $db->flushQueryLog();
        try {
            $r = $this->requestAs($actor, 'host_id=secret&from_month[]=bad&from_month=bad&from_month=again');
            $queries = $db->getQueryLog();
        } finally { $db->disableQueryLog(); }
        $r->assertStatus($status);
        if ($code !== null) { $r->assertJsonPath('code', $code); }
        $this->assertArrayNotHasKey('errors', $r->json());
        foreach ($queries as $q) { $this->assertStringNotContainsString('invoices', $q['query']); }
    }

    public static function invalidQueries(): array
    {
        return [
            ['from_month=2026-10', 'to_month'], ['to_month=2026-10', 'from_month'],
            ['from_month=&to_month=2026-11', 'from_month'], ['from_month&to_month=2026-11', 'from_month'],
            ['from_month=null&to_month=2026-11', 'from_month'], ['from_month=2026-1&to_month=2026-11', 'from_month'],
            ['from_month=0000-01&to_month=2026-11', 'from_month'], ['from_month=2026-13&to_month=2026-11', 'from_month'],
            ['from_month=2026-11&to_month=2026-10', 'to_month'], ['from_month=2025-11&to_month=2026-11', 'to_month'],
            ['from_month=+2026-10&to_month=2026-11', 'from_month'], ['from_month=2026-10%0A&to_month=2026-11', 'from_month'],
            ['from_month=2026-10&from_month=2026-10&to_month=2026-11', 'from_month'],
            ['from_month=2026-10&%66rom_month=2026-11&to_month=2026-11', 'from_month'],
            ['to_month=2026-11&to%5Fmonth=2026-11&from_month=2026-10', 'to_month'],
            ['from_month[]=2026-10&to_month=2026-11', 'from_month'],
            ['from_month%5Bx%5D=2026-10&to_month=2026-11', 'from_month'],
            ['from_month=2026-10&to_month[a][b]=2026-11', 'to_month'],
            ['host_id=PRIVATE_VALUE', 'host_id'], ['property_id=99', 'property_id'], ['timezone=UTC', 'timezone'],
            ['from.month=2026-10&to_month=2026-11', 'from.month'],
            ['from+month=2026-10&to_month=2026-11', 'from month'],
            ['from_month%00=2026-10&to_month=2026-11', "from_month\0"],
            ['0=PRIVATE_VALUE', '0'], ['=PRIVATE_VALUE', ''], ['unknown[]=PRIVATE_VALUE', 'unknown[]'],
            ['from_month=%ZZ&to_month=2026-11', 'from_month'],
        ];
    }

    #[DataProvider('invalidQueries')]
    public function test_raw_url_validation_field_errors_are_objects(string $query, string $field): void
    {
        $r = $this->requestAs(1, $query);
        $r->assertStatus(422)->assertJsonPath('code', 'HOST_DASHBOARD_INVALID_QUERY');
        $body = json_decode($r->getContent(), flags: JSON_THROW_ON_ERROR);
        $this->assertInstanceOf(\stdClass::class, $body->errors);
        $this->assertTrue(property_exists($body->errors, $field));
        $this->assertStringNotContainsString('PRIVATE_VALUE', $r->getContent());
    }

    public function test_default_ignores_get_body_and_encoded_canonical_names_work(): void
    {
        $r = $this->requestAs(1, '', ['from_month' => 'bad', 'host_id' => '2']);
        $r->assertOk()->assertJsonPath('data.period.from_month', '2025-12')->assertJsonPath('data.period.to_month', '2026-11');
        $this->assertCount(12, $r->json('data.revenue_by_month'));
        $this->requestAs(1, '%66rom_month=2026-10&to%5Fmonth=2026-11')
            ->assertOk()->assertJsonPath('data.summary.revenue_total', '300.76');
    }

    public static function validBoundaries(): array
    {
        return [['0001-01', '0001-01'], ['9999-12', '9999-12'], ['2024-02', '2024-02'], ['2026-12', '2026-12']];
    }

    #[DataProvider('validBoundaries')]
    public function test_valid_period_boundaries_keep_snapshot_and_warning(string $from, string $to): void
    {
        $this->requestAs(1, 'from_month='.$from.'&to_month='.$to)->assertOk()
            ->assertJsonPath('data.period.from_month', $from)->assertJsonPath('data.period.to_month', $to)
            ->assertJsonPath('data.summary.revenue_total', '0.00')->assertJsonPath('data.snapshot.pending_bookings_count', 2)
            ->assertJsonPath('data.warnings.paid_invoices_missing_paid_at_scope_count', 1);
    }

    public function test_host_isolation_empty_and_current_state_changes(): void
    {
        $query = 'from_month=2026-10&to_month=2026-11';
        $a = $this->requestAs(1, $query)->assertOk()->json('data');
        $this->requestAs(2, $query)->assertOk()->assertJsonPath('data.summary.revenue_total', '9999.99')
            ->assertJsonPath('data.scope.properties_count', 1)->assertJsonPath('data.recent_bookings.0.id', '309');
        $empty = $this->requestAs(3, $query)->assertOk()->assertJsonPath('data.scope.properties_count', 0)
            ->assertJsonPath('data.summary.revenue_total', '0.00')->json('data');
        $this->assertSame([], $empty['recent_bookings']);
        $db = $this->app['db']->connection();
        $db->table('invoices')->where('id', 309)->update(['total_amount' => '1.00']);
        $this->assertSame($a, $this->requestAs(1, $query)->json('data'));
        $db->table('invoices')->where('id', 302)->update(['payment_status' => 'REFUNDED']);
        $this->requestAs(1, $query)->assertOk()->assertJsonPath('data.summary.revenue_total', '100.26');
    }

    public function test_unexpected_exception_is_safe_even_with_debug_enabled(): void
    {
        config(['app.debug' => true]);
        $mock = \Mockery::mock(HostDashboardQueryService::class);
        $mock->shouldReceive('assertCanView')->once();
        $mock->shouldReceive('getOverview')->once()->andThrow(new RuntimeException('PRIVATE_SQL_SECRET'));
        $this->app->instance(HostDashboardQueryService::class, $mock);
        $r = $this->requestAs(1);
        $r->assertStatus(500)->assertJsonPath('code', 'INTERNAL_SERVER_ERROR');
        $this->assertSame(['message', 'code'], array_keys($r->json()));
        $this->assertStringNotContainsString('PRIVATE_SQL_SECRET', $r->getContent());
    }
}
