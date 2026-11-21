<?php

namespace Tests\Feature\Dashboard;

use App\Models\User;
use App\Repositories\Dashboard\AdminDashboardRepository;
use App\Services\Dashboard\AdminDashboardQueryService;
use Mockery;
use RuntimeException;
use Tests\Support\Uc47DashboardFixtures;
use Tests\Support\Uc47MySqlTestCase;

final class Uc47AdminDashboardMySqlTest extends Uc47MySqlTestCase
{
    private function signIn(int $id): void
    {
        $this->app['auth']->forgetGuards();
        $this->actingAs(User::findOrFail($id), 'web');
    }

    public function test_guest_and_role_account_boundaries(): void
    {
        $this->getJson('/api/admin/dashboard')->assertUnauthorized();
        $fixture = Uc47DashboardFixtures::create($this->app, $this->uc47Manifest);
        foreach (['host_a', 'customer'] as $key) {
            $this->signIn($fixture['actors'][$key]);
            $this->getJson('/api/admin/dashboard')->assertForbidden()->assertJsonPath('code', 'ADMIN_DASHBOARD_FORBIDDEN');
        }
        foreach (['blocked' => 'ACCOUNT_BLOCKED', 'inactive' => 'ACCOUNT_INACTIVE'] as $key => $code) {
            $this->signIn($fixture['actors'][$key]);
            $this->getJson('/api/admin/dashboard')->assertForbidden()->assertJsonPath('code', $code);
        }
    }

    public function test_exact_global_summary_projection_query_scope_and_no_writes(): void
    {
        $fixture = Uc47DashboardFixtures::create($this->app, $this->uc47Manifest);
        $this->signIn($fixture['actors']['admin']);
        $snapshot = function (): array {
            $hashes = [];
            foreach (['users', 'properties', 'rooms', 'bookings', 'booking_services', 'invoices', 'invoice_items'] as $table) {
                $hashes[$table] = hash('sha256', json_encode($this->app['db']->table($table)->orderBy('id')->get(), JSON_THROW_ON_ERROR));
            }
            return $hashes;
        };
        $before = $snapshot();
        $this->travelTo(\Carbon\Carbon::parse('2026-11-18 07:00:00', 'UTC'));
        $response = $this->getJson('/api/admin/dashboard?host_id=999&status=UNPAID&revenue_total=1')->assertOk();
        $months = [];
        foreach (['2025-12', '2026-01', '2026-02', '2026-03', '2026-04', '2026-05', '2026-06', '2026-07', '2026-08', '2026-09', '2026-10', '2026-11'] as $month) {
            $months[] = ['month' => $month, 'total_amount' => $month === '2026-11' ? '19999999999.98' : '0.00'];
        }
        $response->assertExactJson(['data' => [
            'summary' => ['users_count' => 7, 'hosts_count' => 3, 'properties_count' => 4,
                'rooms_count' => 4, 'bookings_count' => 6, 'revenue_total' => '19999999999.98'],
            'excluded_paid_invoices_count' => 2, 'generated_at' => '2026-11-18T07:00:00.000000Z',
            'booking_status_counts' => array_map(fn ($status) => ['status' => $status, 'count' => 1], ['PENDING', 'CONFIRMED', 'CHECKED_IN', 'COMPLETED', 'CANCELLED', 'REJECTED']),
            'revenue_by_month' => $months, 'report_timezone' => 'Asia/Ho_Chi_Minh',
        ]]);
        $this->assertSame($before, $snapshot());
        $db = $this->app['db']->connection();
        $db->enableQueryLog();
        $db->flushQueryLog();
        $facts = $this->app->make(AdminDashboardRepository::class)->aggregate([], '1794985200.000000');
        $this->assertCount(1, $db->getQueryLog());
        $db->disableQueryLog();
        $this->assertSame('19999999999.98', $facts->revenue_total);
        // No Booking-status gate; refund removes a formerly valid invoice from the total.
        $db->table('invoices')->where('id', $fixture['invoices'][0])->update(['payment_status' => 'REFUNDED']);
        $this->getJson('/api/admin/dashboard')->assertOk()->assertJsonPath('data.summary.revenue_total', '9999999999.99')
            ->assertJsonPath('data.revenue_by_month.11.total_amount', '9999999999.99');
        $this->travelBack();
    }

    public function test_no_bookings_or_invoices_returns_zero_decimal_and_counts(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'empty@uc47.example.test', 'password' => 'synthetic', 'role' => 'ADMIN', 'status' => 'ACTIVE']);
        $this->signIn($admin->id);
        $this->getJson('/api/admin/dashboard')->assertOk()->assertJsonPath('data.summary', [
            'users_count' => 1, 'hosts_count' => 0, 'properties_count' => 0,
            'rooms_count' => 0, 'bookings_count' => 0, 'revenue_total' => '0.00',
        ])->assertJsonPath('data.excluded_paid_invoices_count', 0);
        $empty = $this->getJson('/api/admin/dashboard')->assertOk()->json('data');
        $this->assertSame(array_fill(0, 6, 0), array_column($empty['booking_status_counts'], 'count'));
        $this->assertSame(array_fill(0, 12, '0.00'), array_column($empty['revenue_by_month'], 'total_amount'));
        $facts = $this->app->make(AdminDashboardRepository::class)->aggregate([], '1794985200.000000');
        $this->assertSame('0.00', $facts->revenue_total);
    }

    public function test_unexpected_errors_are_safe_and_route_has_expected_middleware(): void
    {
        $fixture = Uc47DashboardFixtures::create($this->app, $this->uc47Manifest);
        $this->signIn($fixture['actors']['admin']);
        $service = Mockery::mock(AdminDashboardQueryService::class);
        $service->shouldReceive('getOverview')->once()->andThrow(new RuntimeException('PRIVATE_DASHBOARD_FAILURE'));
        $this->app->instance(AdminDashboardQueryService::class, $service);
        $this->getJson('/api/admin/dashboard')->assertStatus(500)->assertExactJson([
            'message' => 'Đã xảy ra lỗi hệ thống. Vui lòng thử lại sau.', 'code' => 'INTERNAL_SERVER_ERROR',
        ]);
        $route = $this->app['router']->getRoutes()->getByName('admin.dashboard.show');
        $this->assertSame(['GET', 'HEAD'], $route->methods());
        $this->assertSame('api/admin/dashboard', $route->uri());
        $middleware = $route->gatherMiddleware();
        $this->assertContains('account.active', $middleware);
        $this->assertLessThan(array_search('account.active', $middleware, true), array_search('auth:sanctum', $middleware, true));
    }

    public function test_month_boundaries_cutoff_statuses_and_utc_session(): void
    {
        $fixture = Uc47DashboardFixtures::create($this->app, $this->uc47Manifest);
        $db = $this->app['db']->connection();
        $this->assertSame('00:00:00', $db->selectOne('SELECT TIMEDIFF(NOW(), UTC_TIMESTAMP()) AS utc_offset')->utc_offset);
        // Remove the original fixture revenue, then use independently calculated edge amounts.
        $db->table('invoices')->update(['payment_status' => 'UNPAID']);
        foreach ([
            ['2025-11-30 16:59:59', '1.01', 'PAID'], // Before first local month.
            ['2025-11-30 17:00:00', '2.02', 'PAID'], // First local month start.
            ['2025-12-31 16:59:59', '3.03', 'PAID'], // Last second of December VN.
            ['2025-12-31 17:00:00', '4.04', 'PAID'], // First second of January VN.
            ['2026-10-31 16:59:59', '5.05', 'PAID'], // October VN.
            ['2026-10-31 17:00:00', '6.06', 'PAID'], // November VN.
            ['2026-11-18 07:00:00', '7.07', 'PAID'], // Exact inclusive cutoff.
            ['2026-11-18 07:00:01', '8.08', 'PAID'], // Future in current month.
            ['2026-11-30 17:00:00', '9.09', 'PAID'], // Next month.
            ['2026-11-01 00:00:00', '10.10', 'UNPAID'],
            ['2026-11-01 00:00:00', '11.11', 'REFUNDED'],
            [null, '12.12', 'PAID'],
        ] as [$date, $amount, $status]) Uc47DashboardFixtures::addInvoice($this->app, $this->uc47Manifest, $date, $amount, $status);
        $this->signIn($fixture['actors']['admin']);
        $this->travelTo(\Carbon\Carbon::parse('2026-11-18T07:00:00Z'));
        try {
            $data = $this->getJson('/api/admin/dashboard')->assertOk()->json('data');
            $this->assertSame(['5.05', '4.04', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '5.05', '13.13'], array_column($data['revenue_by_month'], 'total_amount'));
            $this->assertSame('45.45', $data['summary']['revenue_total']); // All-time, including future dates.
            $this->assertSame(1, $data['excluded_paid_invoices_count']);
            $this->assertSame($data['summary']['bookings_count'], array_sum(array_column($data['booking_status_counts'], 'count')));
        } finally { $this->travelBack(); }
    }

    public function test_leap_day_and_local_new_month_with_zero_fill(): void
    {
        $fixture = Uc47DashboardFixtures::create($this->app, $this->uc47Manifest);
        $this->app['db']->table('invoices')->update(['payment_status' => 'UNPAID']);
        Uc47DashboardFixtures::addInvoice($this->app, $this->uc47Manifest, '2024-02-28 17:00:00', '1.23');
        Uc47DashboardFixtures::addInvoice($this->app, $this->uc47Manifest, '2024-02-29 16:59:59', '2.34');
        Uc47DashboardFixtures::addInvoice($this->app, $this->uc47Manifest, '2024-02-29 17:00:00', '3.45');
        $this->signIn($fixture['actors']['admin']);
        $this->travelTo(\Carbon\Carbon::parse('2024-02-29T17:00:00Z'));
        try {
            $data = $this->getJson('/api/admin/dashboard')->assertOk()->json('data');
            $this->assertCount(12, $data['revenue_by_month']);
            $this->assertSame(['month' => '2023-04', 'total_amount' => '0.00'], $data['revenue_by_month'][0]);
            $this->assertSame(['month' => '2024-02', 'total_amount' => '3.57'], $data['revenue_by_month'][10]);
            $this->assertSame(['month' => '2024-03', 'total_amount' => '3.45'], $data['revenue_by_month'][11]);
        } finally { $this->travelBack(); }
    }

}
