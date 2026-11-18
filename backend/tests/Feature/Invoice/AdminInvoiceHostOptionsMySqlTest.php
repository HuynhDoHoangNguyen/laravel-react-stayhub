<?php

namespace Tests\Feature\Invoice;

use App\DTOs\Invoice\Requests\AdminInvoiceFilterOptionsRequest;
use App\Exceptions\AppException;
use App\Models\User;
use App\Services\Invoice\InvoiceQueryService;
use Mockery;
use Tests\Support\Uc45InvoiceFixtures;
use Tests\Support\Uc45MySqlTestCase;

final class AdminInvoiceHostOptionsMySqlTest extends Uc45MySqlTestCase
{
    private array $fixture;
    private const URL = '/api/admin/invoices/filter-options';

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

    public function test_auth_account_and_role_precede_query_validation(): void
    {
        $this->getJson(self::URL)->assertUnauthorized();
        foreach (['host_a' => 'INVOICE_READ_FORBIDDEN', 'customer' => 'INVOICE_READ_FORBIDDEN',
            'blocked' => 'ACCOUNT_BLOCKED', 'inactive' => 'ACCOUNT_INACTIVE'] as $key => $code) {
            $this->signIn($key);
            $this->getJson(self::URL.'?page=01')->assertForbidden()->assertJsonPath('code', $code);
        }
    }

    public function test_http_validation_and_normalized_links(): void
    {
        $this->signIn();
        foreach (['' => 'type', 'type=unsupported' => 'type', 'type[]=hosts' => 'type', 'type=hosts&host_id=' => 'host_id',
            'type=hosts&page=' => 'page', 'type=hosts&page=01' => 'page', 'type=hosts&per_page=51' => 'per_page',
            'type=hosts&search[]=x' => 'search'] as $query => $field) {
            $this->getJson(self::URL.'?'.$query)->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $body = $this->getJson(self::URL.'?type=hosts&search=%20UC45%20&per_page=1&ignored=x&status=UNPAID')
            ->assertOk()->assertJsonPath('meta.total', 2)->assertJsonCount(1, 'data')->json();
        parse_str(parse_url($body['links']['next'], PHP_URL_QUERY), $params);
        $this->assertSame(['type' => 'hosts', 'search' => 'UC45', 'per_page' => '1', 'page' => '2'], $params);
        $this->getJson(self::URL.'?type=hosts&page=99')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.current_page', 99);
        $this->getJson(self::URL.'?type=hosts&search=absent')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.last_page', 1);
    }

    public function test_options_are_unique_current_owners_with_invoices_and_keep_historical_accounts(): void
    {
        $this->signIn();
        $db = $this->app['db'];
        $a = $this->fixture['actors']['host_a']; $b = $this->fixture['actors']['host_b'];
        $db->table('users')->whereIn('id', [$a, $b])->update(['name' => 'Trùng tên']);
        $db->table('users')->where('id', $a)->update(['role' => 'CUSTOMER', 'status' => 'BLOCKED']);
        $db->table('users')->where('id', $b)->update(['status' => 'INACTIVE']);
        // This owner has only the empty property and must not enter the options.
        $db->table('properties')->where('id', $this->fixture['properties'][3])->update(['host_id' => $this->fixture['actors']['inactive']]);
        $this->getJson(self::URL.'?type=hosts&status=REFUNDED&property_id=999')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0', ['id' => $a, 'name' => 'Trùng tên'])->assertJsonPath('data.1', ['id' => $b, 'name' => 'Trùng tên']);
        $this->getJson('/api/admin/invoices?host_id='.$a)->assertOk()->assertJsonPath('meta.total', 8);
        $db->table('properties')->whereIn('id', array_slice($this->fixture['properties'], 0, 3))->update(['host_id' => $b]);
        $this->getJson(self::URL.'?type=hosts')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $b);
    }

    public function test_search_literals_unicode_zero_and_name_order(): void
    {
        $this->signIn();
        $db = $this->app['db'];
        $a = $this->fixture['actors']['host_a']; $b = $this->fixture['actors']['host_b'];
        $db->table('users')->where('id', $b)->update(['name' => 'Alpha']);
        foreach (['%', '_', '!', '\\', '0', 'Tiếng Việt', "O'Reilly"] as $needle) {
            $db->table('users')->where('id', $a)->update(['name' => 'Z '.$needle]);
            $this->getJson(self::URL.'?type=hosts&search='.rawurlencode($needle))->assertOk()
                ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $a);
        }
        $this->getJson(self::URL.'?type=hosts')->assertOk()->assertJsonPath('data.0.id', $b);
        $this->getJson(self::URL.'?type=hosts&search='.rawurlencode("' OR 1=1 --"))->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_service_role_guard_no_n_plus_one_and_http_does_not_write(): void
    {
        $db = $this->app['db']->connection();
        $service = $this->app->make(InvoiceQueryService::class);
        $actor = User::findOrFail($this->fixture['actors']['customer']);
        $db->enableQueryLog(); $db->flushQueryLog();
        try {
            try { $service->getAdminInvoiceHostOptions($actor, new AdminInvoiceFilterOptionsRequest()); $this->fail('Role accepted'); }
            catch (AppException $error) { $this->assertSame(403, $error->status); $this->assertSame([], $db->getQueryLog()); }
            $admin = User::findOrFail($this->fixture['actors']['admin']);
            $db->flushQueryLog();
            \App\Common\PageResponse::toResponse($service->getAdminInvoiceHostOptions($admin, new AdminInvoiceFilterOptionsRequest()));
            $this->assertCount(2, $db->getQueryLog());
            foreach ($db->getQueryLog() as $query) $this->assertStringStartsWith('select', strtolower($query['query']));
        } finally { $db->disableQueryLog(); $db->flushQueryLog(); }
        $snapshot = function () use ($db): array {
            $hashes = [];
            foreach (['users', 'properties', 'rooms', 'bookings', 'booking_services', 'invoices', 'invoice_items'] as $table) {
                $hashes[$table] = hash('sha256', json_encode($db->table($table)->orderBy('id')->get(), JSON_THROW_ON_ERROR));
            }
            return $hashes;
        };
        $this->signIn(); $before = $snapshot();
        $this->getJson(self::URL.'?type=hosts')->assertOk();
        $this->assertSame($before, $snapshot());
    }

    public function test_unexpected_error_is_safe_for_the_new_named_route(): void
    {
        $this->signIn(); config(['app.debug' => true]);
        $service = Mockery::mock(InvoiceQueryService::class);
        $service->shouldReceive('getAdminInvoiceFilterOptions')->once()->andThrow(new \RuntimeException('PRIVATE-HOST-SENTINEL'));
        $this->app->instance(InvoiceQueryService::class, $service);
        $response = $this->getJson(self::URL.'?type=hosts')->assertStatus(500)->assertJsonPath('code', 'INTERNAL_SERVER_ERROR');
        $this->assertStringNotContainsString('PRIVATE-HOST-SENTINEL', $response->getContent());
        $this->assertArrayNotHasKey('trace', $response->json());
    }
}
