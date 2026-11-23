<?php

namespace Tests\Feature\Invoice;

use App\Exceptions\GlobalExceptionHandler;
use App\Models\User;
use App\Services\Invoice\InvoiceQueryService;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Mockery;
use RuntimeException;
use Tests\Support\Uc45InvoiceFixtures;
use Tests\Support\Uc45MySqlTestCase;

final class AdminInvoiceListApiMySqlTest extends Uc45MySqlTestCase
{
    private array $fixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixture = Uc45InvoiceFixtures::create($this->app, $this->uc45Manifest);
    }

    private function signIn(string $key = 'admin'): void
    {
        // Each synthetic request starts a new guard context, as separate HTTP requests do.
        $this->app['auth']->forgetGuards();
        $this->actingAs(User::findOrFail($this->fixture['actors'][$key]), 'web');
    }

    public function test_guest_is_unauthenticated_before_invalid_query(): void
    {
        $this->getJson('/api/admin/invoices?page=01')->assertUnauthorized();
    }

    public function test_active_non_admin_is_forbidden_before_invalid_query(): void
    {
        foreach (['host_a', 'customer'] as $key) {
            $this->signIn($key);
            $this->getJson('/api/admin/invoices?page=01')->assertForbidden()
                ->assertJsonPath('code', 'INVOICE_READ_FORBIDDEN')
                ->assertJsonPath('message', 'Bạn không có quyền truy cập chức năng này.');
        }
    }

    public function test_account_middleware_rejects_blocked_and_inactive_admin(): void
    {
        foreach (['blocked' => 'ACCOUNT_BLOCKED', 'inactive' => 'ACCOUNT_INACTIVE'] as $key => $code) {
            $this->signIn($key);
            $this->getJson('/api/admin/invoices?page=01')->assertForbidden()->assertJsonPath('code', $code);
        }
    }

    public function test_admin_receives_snapshot_projection_with_stable_order_and_no_writes(): void
    {
        $this->signIn();
        $snapshot = function (): array {
            $hashes = [];
            foreach (['users', 'properties', 'rooms', 'bookings', 'booking_services', 'invoices', 'invoice_items'] as $table) {
                $hashes[$table] = hash('sha256', json_encode($this->app['db']->table($table)->orderBy('id')->get(), JSON_THROW_ON_ERROR));
            }
            return $hashes;
        };
        $before = $snapshot();
        $body = $this->getJson('/api/admin/invoices')->assertOk()->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.total', 12)->assertJsonPath('meta.last_page', 2)->json();
        $this->assertSame(['data', 'links', 'meta'], array_keys($body));
        $this->assertSame(array_slice(array_reverse($this->fixture['invoices']), 0, 10), array_column($body['data'], 'id'));
        $bookings = $this->app['db']->table('bookings')->whereIn('id', $this->fixture['bookings'])->pluck('booking_code', 'id');
        foreach ($body['data'] as $row) {
            $this->assertSame(['id', 'invoice_code', 'booking_id', 'booking_code', 'customer_name', 'property_name', 'room_name',
                'total_amount', 'payment_status', 'paid_at', 'created_at'], array_keys($row));
            $this->assertIsInt($row['booking_id']);
            $this->assertSame($bookings[$row['booking_id']], $row['booking_code']);
            $this->assertSame('208.50', $row['total_amount']);
            $this->assertSame('2026-11-18T02:00:00.000000Z', $row['created_at']);
        }
        $this->getJson('/api/admin/invoices?page=2')->assertOk()->assertJsonCount(2, 'data');
        $this->assertSame($before, $snapshot());
        $route = $this->app['router']->getRoutes()->getByName('admin.invoices.index');
        $this->assertSame('api/admin/invoices', $route->uri());
        $this->assertContains('auth:sanctum', $route->gatherMiddleware());
        $this->assertContains('account.active', $route->gatherMiddleware());
    }

    public function test_raw_http_validation_rejects_noncanonical_types_and_limits(): void
    {
        $this->signIn();
        foreach (['page=01' => 'page', 'page=' => 'page', 'page[]=1' => 'page', 'page=2147483648' => 'page',
            'per_page=' => 'per_page', 'per_page=51' => 'per_page', 'host_id=1e2' => 'host_id',
            'host_id=9007199254740992' => 'host_id', 'property_id=0' => 'property_id',
            'status=ALL' => 'status', 'status[]=PAID' => 'status',
            'search='.rawurlencode(str_repeat('ệ', 256)) => 'search'] as $query => $field) {
            $this->getJson('/api/admin/invoices?'.$query)->assertUnprocessable()->assertJsonValidationErrors($field);
        }
    }

    public function test_filters_normalized_links_empty_and_out_of_range_are_http_contracts(): void
    {
        $this->signIn();
        $query = ['host_id' => $this->fixture['actors']['host_a'], 'status' => ' PAID ',
            'search' => ' snapshot %_ ', 'page' => 1, 'per_page' => 1, 'unexpected' => 'ignore'];
        $body = $this->getJson('/api/admin/invoices?'.http_build_query($query))->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 3)->json();
        foreach (['first', 'last', 'next'] as $key) {
            parse_str(parse_url($body['links'][$key], PHP_URL_QUERY), $params);
            $this->assertSame('PAID', $params['status']);
            $this->assertSame('snapshot %_', $params['search']);
            $this->assertSame((string) $query['host_id'], $params['host_id']);
            $this->assertArrayNotHasKey('unexpected', $params);
        }
        $this->getJson('/api/admin/invoices?host_id=&property_id=&status=&search=')->assertOk()->assertJsonPath('meta.total', 12);
        foreach ($this->fixture['invoices'] as $index => $id) {
            $this->app['db']->table('invoices')->where('id', $id)->update(['invoice_code' => 'CODE-'.chr(65 + $index)]);
        }
        $this->getJson('/api/admin/invoices?search=0')->assertOk()->assertJsonPath('meta.total', 5);
        $this->getJson('/api/admin/invoices?property_id='.$this->fixture['properties'][3])->assertOk()
            ->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 0)->assertJsonPath('meta.last_page', 1)
            ->assertJsonPath('links.prev', null)->assertJsonPath('links.next', null);
        $this->getJson('/api/admin/invoices?page=99')->assertOk()->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.current_page', 99)->assertJsonPath('meta.total', 12);
    }

    public function test_unexpected_failure_uses_safe_response_even_with_debug_enabled(): void
    {
        $this->signIn();
        config(['app.debug' => true]);
        $service = Mockery::mock(InvoiceQueryService::class);
        $service->shouldReceive('getAdminInvoices')->once()->andThrow(new RuntimeException('PRIVATE-EXCEPTION-SENTINEL'));
        $this->app->instance(InvoiceQueryService::class, $service);
        $response = $this->getJson('/api/admin/invoices')->assertStatus(500)->assertJsonPath('code', 'INTERNAL_SERVER_ERROR');
        $this->assertStringNotContainsString('PRIVATE-EXCEPTION-SENTINEL', $response->getContent());
        $this->assertArrayNotHasKey('trace', $response->json());
        $request = Request::create('/unrelated');
        $request->setRouteResolver(fn () => (new Route('GET', '/unrelated', fn () => null))->name('unrelated'));
        $this->assertNull((new GlobalExceptionHandler())->render(new RuntimeException('unrelated'), $request));
    }
}
