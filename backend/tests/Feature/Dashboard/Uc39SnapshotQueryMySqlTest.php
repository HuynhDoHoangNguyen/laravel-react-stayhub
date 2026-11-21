<?php

namespace Tests\Feature\Dashboard;

use App\Models\User;
use App\Services\Dashboard\HostDashboardQueryService;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Tests\Support\Uc39MySqlTestCase;

final class Uc39SnapshotQueryMySqlTest extends Uc39MySqlTestCase
{
    private const CUTOFF = '2026-11-21T05:00:00Z';

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(self::CUTOFF);
        $db = $this->app['db']->connection();
        foreach ([[1, 'HOST', 'ACTIVE'], [2, 'HOST', 'ACTIVE'], [3, 'HOST', 'ACTIVE'], [4, 'CUSTOMER', 'INACTIVE']] as [$id, $role, $status]) {
            $db->table('users')->insert(['id' => $id, 'name' => 'QA '.$id, 'email' => 'qa'.$id.'@example.test',
                'password' => 'synthetic-not-a-login', 'role' => $role, 'status' => $status, ...$this->stamp()]);
        }
        foreach ([[101, 1, 'ACTIVE'], [102, 1, 'BLOCKED'], [103, 1, 'INACTIVE'], [104, 1, 'PENDING'], [105, 2, 'ACTIVE']] as [$id, $host, $status]) {
            $this->property($id, $host, $status);
        }
        foreach ([[201, 101, 'ACTIVE'], [202, 102, 'MAINTENANCE'], [203, 103, 'INACTIVE'], [204, 104, 'ACTIVE'], [205, 105, 'ACTIVE']] as [$id, $property, $status]) {
            $this->room($id, $property, $status);
        }
        foreach ([[301, 'PENDING', '2026-09-01T00:00:00Z'], [302, 'PENDING', '2026-11-20T00:00:00Z'],
            [303, 'CONFIRMED', '2026-11-20T00:00:00Z'], [304, 'COMPLETED', self::CUTOFF],
            [305, 'CANCELLED', '2026-11-19T00:00:00Z'], [306, 'REJECTED', '2026-11-18T00:00:00Z'],
            [307, 'CHECKED_IN', '2026-11-17T00:00:00Z'], [308, 'PENDING', '2026-11-21T05:00:01Z']] as [$id, $status, $time]) {
            $this->booking((string) $id, 201, $status, $time);
        }
        $this->booking('309', 205, 'PENDING', '2026-11-21T04:00:00Z');
        foreach ([[401, 'IN_PROGRESS'], [402, 'IN_PROGRESS'], [403, 'SCHEDULED'], [404, 'COMPLETED'], [405, 'CANCELLED']] as [$id, $status]) {
            $db->table('maintenances')->insert(['id' => $id, 'room_id' => 201,
                'start_date' => $id === 401 ? '2025-01-01' : '2027-01-01',
                'end_date' => $id === 401 ? '2025-01-02' : '2027-01-02', 'status' => $status, ...$this->stamp()]);
        }
        $db->table('maintenances')->insert(['id' => 407, 'room_id' => 205, 'start_date' => '2026-11-21',
            'end_date' => '2026-11-22', 'status' => 'IN_PROGRESS', ...$this->stamp()]);
    }

    protected function tearDown(): void
    {
        try { parent::tearDown(); } finally { CarbonImmutable::setTestNow(); }
    }

    private function stamp(string $time = '2026-01-01T00:00:00Z'): array
    {
        // Native DateTime oracle; not the production repository or connection's wall clock.
        $epoch = (new DateTimeImmutable($time))->getTimestamp();
        $expression = $this->app['db']->connection()->raw('FROM_UNIXTIME('.$epoch.')');
        return ['created_at' => $expression, 'updated_at' => $expression];
    }

    private function property(int $id, int $host, string $status, string $time = '2026-01-01T00:00:00Z'): void
    {
        $this->app['db']->connection()->table('properties')->insert(['id' => $id, 'host_id' => $host,
            'name' => 'Cơ sở '.$id, 'type' => 'HOTEL', 'address' => 'QA', 'status' => $status, ...$this->stamp($time)]);
    }

    private function room(int $id, int $property, string $status, string $time = '2026-01-01T00:00:00Z'): void
    {
        $db = $this->app['db']->connection();
        $db->table('room_types')->insert(['id' => $id, 'property_id' => $property, 'name' => 'QA', ...$this->stamp()]);
        $db->table('rooms')->insert(['id' => $id, 'property_id' => $property, 'room_type_id' => $id,
            'room_number' => (string) $id, 'name' => 'Phòng '.$id, 'capacity' => 2,
            'price_per_night' => '100.00', 'status' => $status, ...$this->stamp($time)]);
    }

    private function booking(string $id, int $room, string $status, string $time): void
    {
        $this->app['db']->connection()->table('bookings')->insert(['id' => $id, 'booking_code' => 'QA-'.$id,
            'customer_id' => 4, 'room_id' => $room, 'check_in_date' => '2026-12-01', 'check_out_date' => '2026-12-02',
            'guest_count' => 1, 'number_of_nights' => 1, 'price_per_night' => '100.00', 'room_total' => '100.00',
            'status' => $status, 'note' => 'Private fixture', ...$this->stamp($time)]);
    }

    private function snapshot(int $actor = 1): array
    {
        return $this->app->make(HostDashboardQueryService::class)->getSnapshot(User::findOrFail($actor))->jsonSerialize();
    }

    private function fingerprint(): string
    {
        $tables = ['users', 'properties', 'room_types', 'rooms', 'maintenances', 'bookings', 'booking_services', 'invoices', 'invoice_items'];
        $rows = [];
        foreach ($tables as $table) { $rows[$table] = $this->app['db']->connection()->table($table)->orderBy('id')->get()->all(); }
        return hash('sha256', serialize($rows));
    }

    public function test_snapshot_oracle_single_statement_read_only_and_minimal_projection(): void
    {
        foreach (['properties', 'rooms', 'bookings', 'maintenances'] as $table) {
            $engine = $this->app['db']->connection()->selectOne('SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table]);
            $this->assertSame('InnoDB', $engine->engine);
        }
        $before = $this->fingerprint();
        $actor = User::findOrFail(1);
        $db = $this->app['db']->connection();
        $db->enableQueryLog(); $db->flushQueryLog();
        try {
            $data = $this->app->make(HostDashboardQueryService::class)->getSnapshot($actor)->jsonSerialize();
            $queries = $db->getQueryLog();
        } finally { $db->disableQueryLog(); }
        $this->assertCount(1, $queries);
        $this->assertStringStartsWith('select ', strtolower($queries[0]['query']));
        $this->assertStringNotContainsString('invoices', $queries[0]['query']);
        $this->assertSame(['properties_count' => 4], $data['scope']);
        $expected = ['rooms_count' => 4, 'pending_bookings_count' => 2, 'in_progress_maintenances_count' => 2];
        $this->assertSame($expected, $data['snapshot']);
        $this->assertSame(['304', '303', '302', '305', '306'], array_column($data['recent_bookings'], 'id'));
        $this->assertSame('2026-11-21T05:00:00.000000Z', $data['recent_bookings'][0]['created_at']);
        $this->assertSame('2026-12-01', $data['recent_bookings'][0]['check_in_date']);
        $this->assertStringNotContainsString('Private', json_encode($data, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('customer', json_encode($data, JSON_THROW_ON_ERROR));
        $this->assertSame($before, $this->fingerprint());
        file_put_contents(dirname(getenv('STAYHUB_UC39_MANIFEST')).'/snapshot-oracle.json', json_encode([
            'expected_snapshot' => $expected, 'actual_snapshot' => $data['snapshot'],
            'expected_recent_ids' => ['304', '303', '302', '305', '306'],
            'actual_recent_ids' => array_column($data['recent_bookings'], 'id'),
            'reporting_statements' => count($queries), 'business_fingerprint_before' => $before,
            'business_fingerprint_after' => $this->fingerprint(),
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
    }

    public function test_cross_host_empty_and_property_without_rooms(): void
    {
        $a = $this->snapshot();
        for ($i = 500; $i < 520; $i++) { $this->booking((string) $i, 205, 'PENDING', self::CUTOFF); }
        $this->assertSame($a, $this->snapshot());
        $b = $this->snapshot(2);
        $this->assertSame(21, $b['snapshot']['pending_bookings_count']);
        $this->assertSame(['519', '518', '517', '516', '515'], array_column($b['recent_bookings'], 'id'));
        $empty = $this->snapshot(3);
        $this->assertSame(['properties_count' => 0], $empty['scope']);
        $this->assertSame(['rooms_count' => 0, 'pending_bookings_count' => 0, 'in_progress_maintenances_count' => 0], $empty['snapshot']);
        $this->assertSame([], $empty['recent_bookings']);
        $this->property(108, 3, 'BLOCKED');
        $this->assertSame(['properties_count' => 1], $this->snapshot(3)['scope']);
        $this->assertSame($empty['snapshot'], $this->snapshot(3)['snapshot']);
    }

    public function test_future_ancestor_and_maintenance_cutoffs_are_applied_everywhere(): void
    {
        $before = $this->snapshot();
        $this->property(106, 1, 'ACTIVE', '2026-11-21T05:00:01Z');
        $this->room(206, 106, 'ACTIVE');
        $this->room(207, 101, 'ACTIVE', '2026-11-21T05:00:01Z');
        foreach ([206, 207] as $room) {
            $this->booking((string) (600 + $room), $room, 'PENDING', self::CUTOFF);
            $this->app['db']->connection()->table('maintenances')->insert(['room_id' => $room, 'start_date' => '2026-11-21',
                'end_date' => '2026-11-22', 'status' => 'IN_PROGRESS', ...$this->stamp()]);
        }
        $this->app['db']->connection()->table('maintenances')->insert(['room_id' => 201, 'start_date' => '2026-11-21',
            'end_date' => '2026-11-22', 'status' => 'IN_PROGRESS', ...$this->stamp('2026-11-21T05:00:01Z')]);
        $this->assertSame($before, $this->snapshot());
        $this->property(107, 1, 'INACTIVE', self::CUTOFF);
        $this->room(208, 107, 'INACTIVE', self::CUTOFF);
        $this->booking('900', 208, 'PENDING', self::CUTOFF);
        $this->assertSame(5, $this->snapshot()['scope']['properties_count']);
        $this->assertSame(5, $this->snapshot()['snapshot']['rooms_count']);
        $this->assertSame(3, $this->snapshot()['snapshot']['pending_bookings_count']);
        $this->assertSame('900', $this->snapshot()['recent_bookings'][0]['id']);
        $this->app['db']->connection()->table('maintenances')->insert(['room_id' => 208, 'start_date' => '2027-01-01',
            'end_date' => '2027-01-02', 'status' => 'IN_PROGRESS', ...$this->stamp(self::CUTOFF)]);
        $this->assertSame(3, $this->snapshot()['snapshot']['in_progress_maintenances_count']);
    }

    public function test_current_ownership_and_connection_timezone(): void
    {
        $before = $this->snapshot();
        $db = $this->app['db']->connection();
        try {
            $db->statement("SET SESSION time_zone = '+07:00'");
            $this->assertSame($before, $this->snapshot());
        } finally { $db->statement("SET SESSION time_zone = '+00:00'"); }
        $db->table('properties')->where('id', 101)->update(['host_id' => 2]);
        $a = $this->snapshot(); $b = $this->snapshot(2);
        $this->assertSame(3, $a['scope']['properties_count']);
        $this->assertSame(0, $a['snapshot']['pending_bookings_count']);
        $this->assertSame([], $a['recent_bookings']);
        $this->assertSame(2, $b['scope']['properties_count']);
        $this->assertSame(3, $b['snapshot']['pending_bookings_count']);
        $this->assertSame(3, $b['snapshot']['in_progress_maintenances_count']);
    }

    public function test_large_unsigned_ids_and_all_six_statuses(): void
    {
        // Stay above JS's safe integer range without exhausting MySQL AUTO_INCREMENT
        // or forcing later Eloquent fixtures beyond PHP_INT_MAX after rollback.
        $this->booking('9007199254740993', 201, 'CONFIRMED', self::CUTOFF);
        $this->booking('9007199254740994', 201, 'CHECKED_IN', self::CUTOFF);
        $this->assertSame(['9007199254740994', '9007199254740993', '304', '303', '302'], array_column($this->snapshot()['recent_bookings'], 'id'));
        $db = $this->app['db']->connection();
        $db->table('bookings')->whereNotIn('id', ['301', '302', '308', '309'])->delete();
        $this->assertSame('PENDING', $this->snapshot()['recent_bookings'][0]['status']);
        foreach (['CONFIRMED', 'CHECKED_IN', 'COMPLETED', 'CANCELLED', 'REJECTED'] as $status) {
            $db->table('bookings')->where('id', 302)->update(['status' => $status]);
            $this->assertSame($status, $this->snapshot()['recent_bookings'][0]['status']);
        }
    }
}
