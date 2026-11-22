<?php

namespace Tests\Feature\UserManagement;

use App\DTOs\UserManagement\Requests\AdminHostQueryRequest;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Models\Property;
use App\Enums\PropertyStatus;
use App\Services\UserManagement\AdminHostQueryService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class AdminHostTest extends TestCase
{
    use RefreshDatabase;

    private ?string $sessionCookie = null;

    public function createApplication()
    {
        foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:',
            'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'] as $key => $expected) {
            if (($_ENV[$key] ?? getenv($key)) !== $expected) {
                throw new RuntimeException('UC41 requires an isolated SQLite in-memory environment before boot.');
            }
        }
        if (is_file(dirname(__DIR__, 3).'/bootstrap/cache/config.php')) {
            throw new RuntimeException('UC41 tests cannot run with cached configuration.');
        }
        $app = parent::createApplication();
        if ($app['config']->get('database.default') !== 'sqlite'
            || $app['config']->get('database.connections.sqlite.database') !== ':memory:'
            || !empty($app['config']->get('database.connections.sqlite.url'))) {
            throw new RuntimeException('UC41 database isolation must hold before RefreshDatabase.');
        }
        $app['config']->set('sanctum.stateful', ['localhost:5173']);

        return $app;
    }

    private function browser(string $method = 'GET', string $uri = '/api/admin/hosts', array $body = []): TestResponse
    {
        Auth::forgetGuards();
        Auth::shouldUse('web');
        $this->withCredentials();
        if ($this->sessionCookie !== null) {
            $this->withCookie(config('session.cookie'), $this->sessionCookie);
        }
        $response = $this->json($method, $uri, $body, ['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/']);
        if ($cookie = $response->getCookie(config('session.cookie'))) {
            $this->sessionCookie = $cookie->getValue();
        }

        return $response;
    }

    private function login(?User $user = null): User
    {
        $user ??= User::factory()->admin()->create();
        $this->browser('POST', '/login', ['email' => $user->email, 'password' => 'password'])->assertOk();

        return $user;
    }

    private function listing(array $query = []): TestResponse
    {
        return $this->browser('GET', '/api/admin/hosts'.($query ? '?'.http_build_query($query) : ''));
    }

    public function test_guest_is_unauthorized(): void
    {
        $this->listing()->assertUnauthorized();
    }

    public static function otherRoles(): array { return [['CUSTOMER'], ['HOST']]; }

    #[DataProvider('otherRoles')]
    public function test_other_roles_are_denied_before_query_validation(string $role): void
    {
        $this->login(User::factory()->create(['role' => UserRole::from($role)]));
        $this->listing(['page' => 'invalid'])->assertForbidden();
    }

    public static function inactiveStatuses(): array { return [['BLOCKED'], ['INACTIVE']]; }

    #[DataProvider('inactiveStatuses')]
    public function test_existing_admin_session_is_rejected_after_status_changes(string $status): void
    {
        $admin = $this->login();
        $admin->update(['status' => UserStatus::from($status)]);
        $this->listing()->assertForbidden()->assertJsonPath('code', 'ACCOUNT_'.$status);
        $this->browser('GET', '/api/me')->assertUnauthorized();
    }

    public function test_list_is_host_only_all_statuses_and_has_an_exact_projection(): void
    {
        $this->freezeSecond();
        $this->login();
        User::factory()->customer()->create();
        $active = User::factory()->host()->create(['name' => 'Khách hàng', 'phone' => '0901234567']);
        $inactive = User::factory()->host()->create(['status' => UserStatus::INACTIVE, 'phone' => null]);
        $blocked = User::factory()->host()->create(['status' => UserStatus::BLOCKED]);
        $response = $this->listing()->assertOk()->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.total', 3)->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 10)->assertJsonPath('meta.last_page', 1);
        $this->assertSame([$blocked->id, $inactive->id, $active->id], array_column($response->json('data'), 'id'));
        $this->assertSame(['id' => $active->id, 'name' => $active->name, 'email' => $active->email,
            'phone' => '0901234567', 'properties_count' => 0, 'status' => 'ACTIVE', 'created_at' => now()->toISOString()], $response->json('data.2'));
        $this->assertNull($response->json('data.1.phone'));
        foreach ($response->json('data') as $row) {
            $this->assertSame(['id', 'name', 'email', 'phone', 'properties_count', 'status', 'created_at'], array_keys($row));
        }
        $this->assertSame(['data', 'links', 'meta'], array_keys($response->json()));
        $this->assertSame(['first', 'last', 'prev', 'next'], array_keys($response->json('links')));
    }

    public function test_filters_keep_the_host_scope_and_search_all_three_fields(): void
    {
        $this->login();
        User::factory()->admin()->create(['name' => 'match']);
        User::factory()->customer()->create(['email' => 'match-host@example.test', 'phone' => '0901111111']);
        $name = User::factory()->host()->create(['name' => 'match name', 'phone' => null]);
        $email = User::factory()->host()->create(['email' => 'match-customer@example.test', 'status' => UserStatus::INACTIVE]);
        $phone = User::factory()->host()->create(['phone' => '0901111111', 'status' => UserStatus::BLOCKED]);
        $this->listing(['q' => ' match '])->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 2);
        $this->listing(['q' => 'match', 'status' => 'INACTIVE'])->assertOk()->assertJsonPath('data.0.id', $email->id)->assertJsonCount(1, 'data');
        $this->listing(['q' => '090111'])->assertOk()->assertJsonPath('data.0.id', $phone->id)->assertJsonCount(1, 'data');
        $this->listing(['q' => 'match', 'status' => 'ACTIVE'])->assertOk()->assertJsonPath('data.0.id', $name->id)->assertJsonCount(1, 'data');
        $this->listing(['status' => 'BLOCKED'])->assertOk()->assertJsonPath('data.0.id', $phone->id);
    }

    public static function literalSearches(): array { return [['%'], ['_'], ['!'], ["' OR 1=1 --"], ['\\']]; }

    #[DataProvider('literalSearches')]
    public function test_search_special_characters_are_literal(string $term): void
    {
        $this->login();
        $target = User::factory()->host()->create(['name' => 'Literal '.$term.' end']);
        User::factory()->host()->create(['name' => 'Other customer', 'phone' => null]);
        $this->listing(['q' => $term])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $target->id);
    }

    public function test_pagination_preserves_query_and_orders_by_date_then_id(): void
    {
        $this->freezeSecond();
        $this->login();
        $users = User::factory()->host()->count(12)->create(['name' => 'Page fixture']);
        $users[0]->forceFill(['created_at' => now()->addDay()])->save();
        $first = $this->listing(['q' => 'Page', 'page' => 1, 'per_page' => 10])->assertOk()
            ->assertJsonCount(10, 'data')->assertJsonPath('data.0.id', $users[0]->id)->assertJsonPath('meta.last_page', 2);
        $second = $this->listing(['q' => 'Page', 'page' => 2, 'per_page' => 10])->assertOk()->assertJsonCount(2, 'data');
        $this->assertSame([$users[2]->id, $users[1]->id], array_column($second->json('data'), 'id'));
        parse_str(parse_url($first->json('links.next'), PHP_URL_QUERY), $next);
        $this->assertEquals(['q' => 'Page', 'page' => '2', 'per_page' => '10'], $next);
        $this->listing(['page' => 3])->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.total', 12)->assertJsonPath('meta.current_page', 3);
        foreach ([20, 50] as $size) {
            $this->listing(['per_page' => $size])->assertOk()->assertJsonCount(12, 'data')->assertJsonPath('meta.per_page', $size);
        }
    }

    public function test_empty_and_whitespace_search_and_legacy_nullable_date(): void
    {
        $this->login();
        $this->listing()->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.total', 0);
        User::factory()->host()->create(['created_at' => null, 'phone' => null]);
        $this->listing(['q' => '   '])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.created_at', null);
        $this->listing(['q' => 'not-found'])->assertOk()->assertJsonPath('data', []);
    }

    public static function invalidQueries(): array
    {
        return [
            [['q' => ['x']], 'q'], [['q' => str_repeat('á', 101)], 'q'],
            [['status' => 'DELETED'], 'status'], [['status' => ''], 'status'], [['status' => ['ACTIVE']], 'status'],
            [['page' => '0'], 'page'], [['page' => '-1'], 'page'], [['page' => '1.5'], 'page'],
            [['page' => '01'], 'page'], [['page' => '1e2'], 'page'], [['page' => '2147483648'], 'page'],
            [['page' => ['1']], 'page'], [['page' => ''], 'page'],
            [['per_page' => '11'], 'per_page'], [['per_page' => '010'], 'per_page'], [['per_page' => ['10']], 'per_page'],
            [['role' => 'HOST'], 'role'], [['sort' => 'email'], 'sort'],
        ];
    }

    #[DataProvider('invalidQueries')]
    public function test_invalid_query_returns_field_errors(array $query, string $field): void
    {
        $this->login();
        $this->listing($query)->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    public function test_get_body_is_rejected_and_listing_does_not_write_users(): void
    {
        $this->login();
        User::factory()->host()->create();
        $before = DB::table('users')->orderBy('id')->get()->toJson();
        $this->browser('GET', '/api/admin/hosts', ['role' => 'ADMIN'])->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->listing()->assertOk();
        $this->assertSame($before, DB::table('users')->orderBy('id')->get()->toJson());
    }

    public function test_property_counts_include_all_statuses_and_hosts_with_no_properties(): void
    {
        $this->login();
        $owner = User::factory()->host()->create();
        $empty = User::factory()->host()->create(['status' => UserStatus::INACTIVE]);
        foreach (PropertyStatus::cases() as $status) {
            Property::factory()->create(['host_id' => $owner->id, 'status' => $status]);
        }
        $other = User::factory()->host()->create(['status' => UserStatus::BLOCKED]);
        Property::factory()->count(2)->create(['host_id' => $other->id]);
        $usersBefore = DB::table('users')->orderBy('id')->get()->toJson();
        $propertiesBefore = DB::table('properties')->orderBy('id')->get()->toJson();
        $rows = collect($this->listing()->assertOk()->assertJsonCount(3, 'data')->json('data'))->keyBy('id');
        $this->assertSame(4, $rows[$owner->id]['properties_count']);
        $this->assertSame(0, $rows[$empty->id]['properties_count']);
        $this->assertSame(2, $rows[$other->id]['properties_count']);
        $this->assertSame($usersBefore, DB::table('users')->orderBy('id')->get()->toJson());
        $this->assertSame($propertiesBefore, DB::table('properties')->orderBy('id')->get()->toJson());
    }

    public function test_property_count_and_mapping_do_not_add_queries_per_host(): void
    {
        $admin = $this->login();
        $first = User::factory()->host()->create();
        Property::factory()->create(['host_id' => $first->id]);
        $service = $this->app->make(AdminHostQueryService::class);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $single = json_decode($service->listing($admin, new AdminHostQueryRequest())->toJson(), true, flags: JSON_THROW_ON_ERROR);
        $oneQueries = count(DB::getQueryLog());
        $this->assertSame(1, $single['data'][0]['properties_count']);
        User::factory()->host()->count(9)->create();
        DB::flushQueryLog();
        $many = json_decode($service->listing($admin, new AdminHostQueryRequest())->toJson(), true, flags: JSON_THROW_ON_ERROR);
        $manyQueries = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertCount(10, $many['data']);
        $this->assertSame(2, $oneQueries);
        $this->assertSame($oneQueries, $manyQueries);
    }

    public static function deniedActors(): array
    {
        return [['HOST', 'ACTIVE'], ['CUSTOMER', 'ACTIVE'], ['ADMIN', 'BLOCKED'], ['ADMIN', 'INACTIVE']];
    }

    #[DataProvider('deniedActors')]
    public function test_query_service_enforces_permissions_without_http(string $role, string $status): void
    {
        $actor = User::factory()->make(['role' => UserRole::from($role), 'status' => UserStatus::from($status)]);
        $this->expectException(AuthorizationException::class);
        $this->app->make(AdminHostQueryService::class)->listing($actor, new AdminHostQueryRequest());
    }

}
