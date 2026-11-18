<?php

namespace Tests\Feature\Invoice;

use App\DTOs\Invoice\Requests\AdminInvoiceFilterOptionsRequest;
use App\Exceptions\AppException;
use App\Models\User;
use App\Services\Invoice\InvoiceQueryService;
use Mockery;
use Tests\Support\Uc45InvoiceFixtures;
use Tests\Support\Uc45MySqlTestCase;

final class AdminInvoicePropertyOptionsMySqlTest extends Uc45MySqlTestCase
{
    private array $fixture;
    private const URL = '/api/admin/invoices/filter-options?type=properties';

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixture = Uc45InvoiceFixtures::create($this->app, $this->uc45Manifest);
    }

    private function signIn(string $key = 'admin'): void
    {
        $this->app['auth']->forgetGuards();
        $this->actingAs(User::findOrFail($this->fixture['actors'][$key]), 'web');
    }

    public function test_http_auth_role_account_and_raw_validation(): void
    {
        $this->getJson(self::URL.'&host_id=01')->assertUnauthorized();
        foreach (['host_a' => 'INVOICE_READ_FORBIDDEN', 'customer' => 'INVOICE_READ_FORBIDDEN',
            'blocked' => 'ACCOUNT_BLOCKED', 'inactive' => 'ACCOUNT_INACTIVE'] as $key => $code) {
            $this->signIn($key);
            $this->getJson(self::URL.'&host_id=01')->assertForbidden()->assertJsonPath('code', $code);
        }
        $this->signIn();
        foreach (['host_id=01' => 'host_id', 'host_id[]=1' => 'host_id', 'host_id=9007199254740992' => 'host_id',
            'page=' => 'page', 'per_page=' => 'per_page', 'per_page=51' => 'per_page', 'search[]=x' => 'search'] as $query => $field) {
            $this->getJson(self::URL.'&'.$query)->assertUnprocessable()->assertJsonValidationErrors($field);
        }
    }

    public function test_unique_properties_host_scope_and_historical_data_with_current_ownership(): void
    {
        $this->signIn(); $db = $this->app['db'];
        [$a, $b, $c, $empty] = $this->fixture['properties'];
        $hostA = $this->fixture['actors']['host_a']; $hostB = $this->fixture['actors']['host_b'];
        $body = $this->getJson(self::URL.'&status=UNPAID&property_id=999')->assertOk()->assertJsonCount(3, 'data')->json();
        $this->assertSame([$a, $b, $c], array_column($body['data'], 'id'));
        $this->assertSame(['id', 'name', 'host_id'], array_keys($body['data'][0]));
        $this->assertNotContains($empty, array_column($body['data'], 'id'));
        $db->table('users')->where('id', $hostA)->update(['role' => 'CUSTOMER', 'status' => 'BLOCKED']);
        $db->table('properties')->whereIn('id', [$a, $b])->update(['status' => 'INACTIVE']);
        $this->getJson(self::URL.'&host_id='.$hostA)->assertOk()->assertJsonCount(2, 'data');
        $db->table('properties')->where('id', $a)->update(['host_id' => $hostB, 'name' => 'Tên mới']);
        $this->getJson(self::URL.'&host_id='.$hostA)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $b);
        $this->getJson(self::URL.'&host_id='.$hostB)->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/admin/invoices?host_id='.$hostA.'&property_id='.$a)->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/admin/invoices?host_id='.$hostB.'&property_id='.$a.'&status=UNPAID&search=snapshot')
            ->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('data.0.property_name', 'Cơ sở snapshot 0');
        $this->getJson(self::URL.'&host_id=999999999')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson(self::URL.'&host_id=')->assertOk()->assertJsonPath('meta.total', 3);
    }

    public function test_literal_search_order_links_and_empty_pages(): void
    {
        $this->signIn(); $db = $this->app['db'];
        [$a, $b, $c] = $this->fixture['properties'];
        $hostA = $this->fixture['actors']['host_a'];
        $db->table('properties')->whereIn('id', [$a, $b, $c])->update(['name' => 'Cùng tên']);
        $body = $this->getJson(self::URL.'&host_id='.$hostA.'&search=%20Cùng%20&per_page=1&ignored=x')->assertOk()
            ->assertJsonPath('data.0.id', $a)->assertJsonPath('meta.total', 2)->json();
        parse_str(parse_url($body['links']['next'], PHP_URL_QUERY), $params);
        $this->assertSame('properties', $params['type']); $this->assertSame((string) $hostA, $params['host_id']);
        $this->assertSame('Cùng', $params['search']); $this->assertArrayNotHasKey('ignored', $params);
        $this->getJson($body['links']['next'])->assertOk()->assertJsonPath('data.0.id', $b);
        foreach (['%', '_', '!', '\\', '0', 'Tiếng Việt', "O'Reilly"] as $needle) {
            $db->table('properties')->where('id', $a)->update(['name' => 'Riêng '.$needle]);
            $this->getJson(self::URL.'&search='.rawurlencode($needle))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $a);
        }
        $this->getJson(self::URL.'&search=absent')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.last_page', 1);
        $this->getJson(self::URL.'&page=99')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.current_page', 99)->assertJsonPath('meta.total', 3);
    }

    public function test_service_guards_query_count_and_http_read_only(): void
    {
        $db = $this->app['db']->connection(); $service = $this->app->make(InvoiceQueryService::class);
        $customer = User::findOrFail($this->fixture['actors']['customer']);
        $admin = User::findOrFail($this->fixture['actors']['admin']);
        $filter = new AdminInvoiceFilterOptionsRequest(type: 'properties');
        $db->enableQueryLog(); $db->flushQueryLog();
        try {
            foreach (['getAdminInvoiceFilterOptions', 'getAdminInvoicePropertyOptions'] as $method) {
                try { $service->$method($customer, $filter); $this->fail('Non-admin accepted'); }
                catch (AppException $error) { $this->assertSame(403, $error->status); $this->assertSame([], $db->getQueryLog()); }
            }
            \App\Common\PageResponse::toResponse($service->getAdminInvoicePropertyOptions($admin, $filter));
            $this->assertCount(2, $db->getQueryLog());
            foreach ($db->getQueryLog() as $query) $this->assertStringStartsWith('select', strtolower($query['query']));
        } finally { $db->disableQueryLog(); $db->flushQueryLog(); }
        $snapshot = function () use ($db): array {
            $hashes = [];
            foreach (['users', 'properties', 'rooms', 'bookings', 'booking_services', 'invoices', 'invoice_items'] as $table)
                $hashes[$table] = hash('sha256', json_encode($db->table($table)->orderBy('id')->get(), JSON_THROW_ON_ERROR));
            return $hashes;
        };
        $this->signIn(); $before = $snapshot(); $this->getJson(self::URL)->assertOk(); $this->assertSame($before, $snapshot());
    }

    public function test_safe_500_uses_existing_named_route_handler(): void
    {
        $this->signIn(); config(['app.debug' => true]);
        $service = Mockery::mock(InvoiceQueryService::class);
        $service->shouldReceive('getAdminInvoiceFilterOptions')->once()->andThrow(new \RuntimeException('PRIVATE-PROPERTY-SENTINEL'));
        $this->app->instance(InvoiceQueryService::class, $service);
        $response = $this->getJson(self::URL)->assertStatus(500)->assertJsonPath('code', 'INTERNAL_SERVER_ERROR');
        $this->assertStringNotContainsString('PRIVATE-PROPERTY-SENTINEL', $response->getContent());
        $this->assertArrayNotHasKey('trace', $response->json());
    }
}
