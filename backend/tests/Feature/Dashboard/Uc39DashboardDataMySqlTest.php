<?php

namespace Tests\Feature\Dashboard;

use App\Models\User;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Uc39DashboardDataFixtures as Fixtures;
use Tests\Support\Uc39MySqlTestCase;

final class Uc39DashboardDataMySqlTest extends Uc39MySqlTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app['db']->connection()->statement("SET time_zone = '+00:00'");
        CarbonImmutable::setTestNow(Fixtures::CUTOFF);
        Fixtures::create($this->app['db']->connection());
    }

    protected function tearDown(): void
    {
        try { parent::tearDown(); } finally { CarbonImmutable::setTestNow(); }
    }

    private function read(int $actor, string $query = 'from_month=2026-10&to_month=2026-11'): array
    {
        $this->app['auth']->forgetGuards();
        $this->actingAs(User::findOrFail($actor), 'web');
        $r = $this->getJson('/api/host/dashboard'.($query === '' ? '' : '?'.$query));
        $r->assertOk();
        $this->assertSame(['data'], array_keys($r->json()));
        $this->assertStringNotContainsString('PRIVATE_DATA_SENTINEL', $r->getContent());
        $this->assertStringNotContainsString('customer_', $r->getContent());
        return $r->json('data');
    }

    public static function scenarios(): array
    {
        $cases = [];
        foreach (Fixtures::scenarios() as $name => $oracle) { $cases[$name] = [$name, ...$oracle]; }
        return $cases;
    }

    #[DataProvider('scenarios')]
    public function test_scenario_http_oracles_single_read_view_and_no_writes(string $name, int $actor, string $total, array $months, int $warning, int $pending): void
    {
        $db = $this->app['db']->connection();
        $before = Fixtures::fingerprint($db);
        $db->enableQueryLog(); $db->flushQueryLog();
        try { $d = $this->read($actor); $queries = $db->getQueryLog(); }
        finally { $db->disableQueryLog(); }
        $this->assertSame($total, $d['summary']['revenue_total']);
        $this->assertSame($months, array_column($d['revenue_by_month'], 'revenue_total'));
        $this->assertSame(['2026-10', '2026-11'], array_column($d['revenue_by_month'], 'month'));
        $this->assertSame($warning, $d['warnings']['paid_invoices_missing_paid_at_scope_count']);
        $this->assertSame(2, $d['scope']['properties_count']);
        $this->assertSame(['rooms_count' => 2, 'pending_bookings_count' => $pending,
            'in_progress_maintenances_count' => $actor === 1 ? 2 : 0], $d['snapshot']);
        $expectedIds = match ($actor) {
            1 => ['1007', '1006', '1005', '1004', '1003'], 2 => [], 3 => ['3002', '3001'],
            4 => ['4002', '4001'], 5 => ['5001'], 6 => ['6002', '6001'], 7 => ['7001'],
        };
        $this->assertSame($expectedIds, array_column($d['recent_bookings'], 'id'));
        $this->assertSame('2026-11-21T05:00:00.000000Z', $d['generated_at']);
        $reporting = array_filter($queries, static fn ($q) => str_contains($q['query'], 'invoices'));
        $this->assertCount(1, $reporting);
        foreach ($queries as $q) { $this->assertStringStartsWith('select ', strtolower($q['query'])); }
        $this->assertSame($before, Fixtures::fingerprint($db));
        $path = dirname(getenv('STAYHUB_UC39_MANIFEST'));
        file_put_contents($path.'/data-response-'.$name.'.json', json_encode(['data' => $d], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        file_put_contents($path.'/data-oracle-'.$name.'.json', json_encode(['expected_total' => $total,
            'actual_total' => $d['summary']['revenue_total'], 'expected_months' => $months,
            'actual_months' => array_column($d['revenue_by_month'], 'revenue_total'),
            'fingerprint_before' => $before, 'fingerprint_after' => Fixtures::fingerprint($db),
            'reporting_selects' => count($reporting)], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
    }

    public function test_empty_host_and_properties_without_rooms(): void
    {
        foreach ([9 => 0, 10 => 2] as $actor => $properties) {
            $d = $this->read($actor);
            $this->assertSame($properties, $d['scope']['properties_count']);
            $this->assertSame(['rooms_count' => 0, 'pending_bookings_count' => 0, 'in_progress_maintenances_count' => 0], $d['snapshot']);
            $this->assertSame([], $d['recent_bookings']);
            $this->assertSame('0.00', $d['summary']['revenue_total']);
        }
    }

    public function test_foreign_changes_refund_and_current_owner_scope(): void
    {
        $db = $this->app['db']->connection(); $original = $this->read(1);
        $db->table('invoices')->where('id', 8001)->update(['total_amount' => '1.00']);
        $this->assertSame($original, $this->read(1));
        $db->table('invoices')->where('id', 1001)->update(['payment_status' => 'REFUNDED']);
        $this->assertSame('200.51', $this->read(1)['summary']['revenue_total']);
        $db->table('properties')->where('id', 101)->update(['host_id' => 8]);
        $a = $this->read(1); $b = $this->read(8);
        $this->assertSame('0.00', $a['summary']['revenue_total']);
        $this->assertSame(0, $a['warnings']['paid_invoices_missing_paid_at_scope_count']);
        $this->assertSame([], $a['recent_bookings']);
        $this->assertSame('201.51', $b['summary']['revenue_total']);
        $this->assertSame(1, $b['warnings']['paid_invoices_missing_paid_at_scope_count']);
    }

    public function test_period_change_keeps_snapshot_recent_warning_and_timezone_independence(): void
    {
        $a = $this->read(1);
        $future = $this->read(1, 'from_month=2026-12&to_month=2026-12');
        $this->assertSame('0.00', $future['summary']['revenue_total']);
        foreach (['scope', 'snapshot', 'recent_bookings', 'warnings'] as $field) { $this->assertSame($a[$field], $future[$field]); }
        $this->app['db']->connection()->statement("SET time_zone = '+07:00'");
        $this->assertSame($a, $this->read(1));
        $default = $this->read(1, '');
        $this->assertSame('2025-12', $default['period']['from_month']);
        $this->assertCount(12, $default['revenue_by_month']);
    }

    public function test_future_ancestors_remove_all_descendant_facts(): void
    {
        $db = $this->app['db']->connection();
        $db->table('rooms')->where('id', 101)->update(['created_at' => '2026-11-21 05:00:01']);
        $d = $this->read(1);
        $this->assertSame(1, $d['snapshot']['rooms_count']);
        $this->assertSame(0, $d['snapshot']['pending_bookings_count']);
        $this->assertSame(0, $d['snapshot']['in_progress_maintenances_count']);
        $this->assertSame([], $d['recent_bookings']);
        $this->assertSame('0.00', $d['summary']['revenue_total']);
        $this->assertSame(0, $d['warnings']['paid_invoices_missing_paid_at_scope_count']);
    }
}
