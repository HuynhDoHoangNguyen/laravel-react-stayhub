<?php

namespace Tests\Feature\Statistics;

use App\Models\User;
use App\Repositories\Statistics\AdminStatisticsRepository;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Uc48MySqlTestCase;
use Tests\Support\Uc48StatisticsFixtures as Fixtures;

final class Uc48AdminStatisticsHttpMySqlTest extends Uc48MySqlTestCase
{
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

    private function actor(string $role = 'ADMIN', string $status = 'ACTIVE'): User
    {
        $base = Fixtures::base($this->app, $this->uc48Manifest);
        $id = $base['actors']['admin'];
        $this->app['db']->table('users')->where('id', $id)->update(['role' => $role, 'status' => $status]);
        return User::findOrFail($id);
    }

    public function test_guest_is_unauthorized_before_invalid_query(): void
    {
        $this->mock(AdminStatisticsRepository::class)->shouldNotReceive('aggregate');
        $this->getJson('/api/admin/statistics?from_month=bad')->assertUnauthorized();
    }

    public static function deniedActors(): array
    {
        return [
            'host' => ['HOST', 'ACTIVE', 'ADMIN_STATISTICS_FORBIDDEN'],
            'customer' => ['CUSTOMER', 'ACTIVE', 'ADMIN_STATISTICS_FORBIDDEN'],
            'blocked admin' => ['ADMIN', 'BLOCKED', 'ACCOUNT_BLOCKED'],
            'inactive admin' => ['ADMIN', 'INACTIVE', 'ACCOUNT_INACTIVE'],
        ];
    }

    #[DataProvider('deniedActors')]
    public function test_permission_precedes_validation(string $role, string $status, string $code): void
    {
        $actor = $this->actor($role, $status);
        $this->mock(AdminStatisticsRepository::class)->shouldNotReceive('aggregate');
        $this->actingAs($actor)->getJson('/api/admin/statistics?from_month=%20bad&extra=x')
            ->assertForbidden()->assertJsonPath('code', $code)->assertJsonMissingPath('errors');
    }

    public static function invalidQueries(): array
    {
        return [
            'missing to' => ['from_month=2026-10', 'to_month'],
            'missing from' => ['to_month=2026-11', 'from_month'],
            'empty' => ['from_month=&to_month=2026-11', 'from_month'],
            'spaces preserved' => ['from_month=%202026-10%20&to_month=2026-11', 'from_month'],
            'plus preserved' => ['from_month=+2026-10&to_month=2026-11', 'from_month'],
            'newline preserved' => ['from_month=2026-10%0A&to_month=2026-11', 'from_month'],
            'array' => ['from_month[]=2026-10&to_month=2026-11', 'from_month'],
            'nested array' => ['from_month[a]=2026-10&to_month=2026-11', 'from_month'],
            'duplicate' => ['from_month=bad&from_month=2026-10&to_month=2026-11', 'from_month'],
            'unknown' => ['from_month=2026-10&to_month=2026-11&as_of=2026-11', 'as_of'],
            'dot key not normalized' => ['from.month=2026-10&to_month=2026-11', 'from.month'],
            'space key not normalized' => ['from+month=2026-10&to_month=2026-11', 'from month'],
            'bad month' => ['from_month=2026-13&to_month=2026-11', 'from_month'],
            'zero year' => ['from_month=0000-01&to_month=0000-02', 'from_month'],
            'short month' => ['from_month=2026-1&to_month=2026-11', 'from_month'],
            'reverse' => ['from_month=2026-11&to_month=2026-10', 'to_month'],
            '13 months' => ['from_month=2025-11&to_month=2026-11', 'to_month'],
        ];
    }

    #[DataProvider('invalidQueries')]
    public function test_raw_query_validation(string $query, string $field): void
    {
        $actor = $this->actor();
        $this->mock(AdminStatisticsRepository::class)->shouldNotReceive('aggregate');
        $response = $this->actingAs($actor)->getJson('/api/admin/statistics?'.$query)
            ->assertUnprocessable()->assertJsonPath('code', 'ADMIN_STATISTICS_INVALID_QUERY');
        $this->assertArrayHasKey($field, $response->json('errors'));
    }

    public function test_http_oracle_projection_and_readonly_business_data(): void
    {
        $base = Fixtures::contract($this->app, $this->uc48Manifest);
        $acceptance = Fixtures::commissionAcceptance($this->app, $base['actors']['host']);
        Fixtures::commission($this->app, $base, $acceptance, '2026-11-01 00:00:00', '70.25');
        $db = $this->app['db']->connection();
        $snapshot = function () use ($db): string {
            $rows = [];
            foreach (['users', 'properties', 'rooms', 'bookings', 'invoices', 'invoice_items', 'booking_services', 'booking_commissions'] as $table) {
                $rows[$table] = $db->table($table)->orderBy('id')->get()->all();
            }
            return hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
        };
        $before = $snapshot();
        $response = $this->actingAs(User::findOrFail($base['actors']['admin']))
            ->getJson('/api/admin/statistics?from_month=2026-10&to_month=2026-11')->assertOk();
        $data = $response->json('data');
        $this->assertSame(['data'], array_keys($response->json()));
        $this->assertSame(['period', 'report_timezone', 'generated_at', 'summary', 'booking_status_counts', 'monthly', 'warnings'], array_keys($data));
        $this->assertSame(['bookings_count' => 4, 'revenue_total' => '300.76', 'commission_revenue_total' => '70.25'], $data['summary']);
        $this->assertSame(['100.25', '200.51'], array_column($data['monthly'], 'revenue_total'));
        $this->assertSame(['0.00', '70.25'], array_column($data['monthly'], 'commission_revenue_total'));
        $this->assertSame([1, 1, 0, 1, 1, 0], array_column($data['booking_status_counts'], 'count'));
        $this->assertSame(['paid_invoices_missing_paid_at_global_count' => 1, 'settled_commissions_missing_settled_at_global_count' => 0], $data['warnings']);
        $this->assertSame('2026-11-19T05:00:00.000000Z', $data['generated_at']);
        $this->assertStringNotContainsString('example.test', $response->getContent());
        $this->assertSame($before, $snapshot());
    }

    public static function validPeriods(): array
    {
        return [
            'default' => ['', '2025-12', '2026-11', 12],
            'one future month' => ['?from_month=2026-12&to_month=2026-12', '2026-12', '2026-12', 1],
            'earliest' => ['?from_month=0001-01&to_month=0001-01', '0001-01', '0001-01', 1],
            'latest' => ['?from_month=9999-12&to_month=9999-12', '9999-12', '9999-12', 1],
        ];
    }

    #[DataProvider('validPeriods')]
    public function test_defaults_zero_fill_and_extreme_periods(string $query, string $from, string $to, int $months): void
    {
        $response = $this->actingAs($this->actor())->getJson('/api/admin/statistics'.$query)->assertOk()
            ->assertJsonPath('data.period.from_month', $from)->assertJsonPath('data.period.to_month', $to)
            ->assertJsonPath('data.summary.revenue_total', '0.00')->assertJsonPath('data.summary.bookings_count', 0);
        $this->assertCount($months, $response->json('data.monthly'));
        $this->assertCount(6, $response->json('data.booking_status_counts'));
    }

    public function test_get_body_is_ignored(): void
    {
        $this->actingAs($this->actor())->json('GET', '/api/admin/statistics', ['from_month' => 'bad', 'as_of' => 'bad'])
            ->assertOk()->assertJsonPath('data.period.from_month', '2025-12');
    }

    public function test_unexpected_exception_is_safe_even_with_debug_enabled(): void
    {
        $actor = $this->actor();
        $this->app['config']->set('app.debug', true);
        $this->mock(AdminStatisticsRepository::class)->shouldReceive('aggregate')->once()
            ->andThrow(new \RuntimeException('SQL secret must not reach response'));
        $this->actingAs($actor)->getJson('/api/admin/statistics')->assertStatus(500)->assertExactJson([
            'message' => 'Đã xảy ra lỗi hệ thống. Vui lòng thử lại sau.', 'code' => 'INTERNAL_SERVER_ERROR',
        ]);
    }
}
