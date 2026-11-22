<?php

namespace Tests\Feature\UserManagement;

use App\DTOs\UserManagement\Requests\AdminCustomerQueryRequest;
use App\DTOs\UserManagement\Requests\AdminCustomerDetailQuery;
use App\Enums\Gender;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\UserManagement\AdminCustomerQueryService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class AdminCustomerTest extends TestCase
{
    use RefreshDatabase;

    private ?string $sessionCookie = null;

    public function createApplication()
    {
        foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:',
            'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'] as $key => $expected) {
            if (($_ENV[$key] ?? getenv($key)) !== $expected) {
                throw new RuntimeException('UC40 requires an isolated SQLite in-memory environment before boot.');
            }
        }
        if (is_file(dirname(__DIR__, 3).'/bootstrap/cache/config.php')) {
            throw new RuntimeException('UC40 tests cannot run with cached configuration.');
        }
        $app = parent::createApplication();
        if ($app['config']->get('database.default') !== 'sqlite'
            || $app['config']->get('database.connections.sqlite.database') !== ':memory:'
            || !empty($app['config']->get('database.connections.sqlite.url'))) {
            throw new RuntimeException('UC40 database isolation must hold before RefreshDatabase.');
        }
        $app['config']->set('sanctum.stateful', ['localhost:5173']);

        return $app;
    }

    private function browser(string $method = 'GET', string $uri = '/api/admin/users', array $body = []): TestResponse
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
        return $this->browser('GET', '/api/admin/users'.($query ? '?'.http_build_query($query) : ''));
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

    public function test_list_is_customer_only_all_statuses_and_has_an_exact_projection(): void
    {
        $this->freezeSecond();
        $this->login();
        User::factory()->host()->create();
        $active = User::factory()->customer()->create(['name' => 'Khách hàng', 'phone' => '0901234567']);
        $inactive = User::factory()->customer()->create(['status' => UserStatus::INACTIVE, 'phone' => null]);
        $blocked = User::factory()->customer()->create(['status' => UserStatus::BLOCKED]);
        $response = $this->listing()->assertOk()->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.total', 3)->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 10)->assertJsonPath('meta.last_page', 1);
        $this->assertSame([$blocked->id, $inactive->id, $active->id], array_column($response->json('data'), 'id'));
        $this->assertSame(['id' => $active->id, 'name' => $active->name, 'email' => $active->email,
            'phone' => '0901234567', 'status' => 'ACTIVE', 'created_at' => now()->toISOString()], $response->json('data.2'));
        $this->assertNull($response->json('data.1.phone'));
        foreach ($response->json('data') as $row) {
            $this->assertSame(['id', 'name', 'email', 'phone', 'status', 'created_at'], array_keys($row));
        }
        $this->assertSame(['data', 'links', 'meta'], array_keys($response->json()));
        $this->assertSame(['first', 'last', 'prev', 'next'], array_keys($response->json('links')));
    }

    public function test_filters_keep_the_customer_scope_and_search_all_three_fields(): void
    {
        $this->login();
        User::factory()->admin()->create(['name' => 'match']);
        User::factory()->host()->create(['email' => 'match-host@example.test', 'phone' => '0901111111']);
        $name = User::factory()->customer()->create(['name' => 'match name', 'phone' => null]);
        $email = User::factory()->customer()->create(['email' => 'match-customer@example.test', 'status' => UserStatus::INACTIVE]);
        $phone = User::factory()->customer()->create(['phone' => '0901111111', 'status' => UserStatus::BLOCKED]);
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
        $target = User::factory()->customer()->create(['name' => 'Literal '.$term.' end']);
        User::factory()->customer()->create(['name' => 'Other customer', 'phone' => null]);
        $this->listing(['q' => $term])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $target->id);
    }

    public function test_pagination_preserves_query_and_orders_by_date_then_id(): void
    {
        $this->freezeSecond();
        $this->login();
        $users = User::factory()->customer()->count(12)->create(['name' => 'Page fixture']);
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
        User::factory()->customer()->create(['created_at' => null, 'phone' => null]);
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
        User::factory()->customer()->create();
        $before = DB::table('users')->orderBy('id')->get()->toJson();
        $this->browser('GET', '/api/admin/users', ['role' => 'ADMIN'])->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->listing()->assertOk();
        $this->assertSame($before, DB::table('users')->orderBy('id')->get()->toJson());
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
        $this->app->make(AdminCustomerQueryService::class)->listing($actor, new AdminCustomerQueryRequest());
    }

    public function test_detail_guest_is_unauthorized_before_id_validation(): void
    {
        $this->browser('GET', '/api/admin/users/invalid')->assertUnauthorized();
    }

    #[DataProvider('otherRoles')]
    public function test_detail_other_roles_are_forbidden_before_id_validation(string $role): void
    {
        $this->login(User::factory()->create(['role' => UserRole::from($role)]));
        $this->browser('GET', '/api/admin/users/invalid')->assertForbidden();
    }

    #[DataProvider('inactiveStatuses')]
    public function test_detail_blocks_an_existing_admin_session_after_status_change(string $status): void
    {
        $admin = $this->login();
        $customer = User::factory()->customer()->create();
        $admin->update(['status' => UserStatus::from($status)]);
        $this->browser('GET', '/api/admin/users/'.$customer->id)->assertForbidden()->assertJsonPath('code', 'ACCOUNT_'.$status);
        $this->browser('GET', '/api/me')->assertUnauthorized();
    }

    public static function detailVariants(): array
    {
        return [['ACTIVE', 'FEMALE'], ['INACTIVE', 'MALE'], ['BLOCKED', 'OTHER']];
    }

    #[DataProvider('detailVariants')]
    public function test_detail_exact_projection_dates_and_no_user_writes(string $status, string $gender): void
    {
        $this->freezeSecond();
        $this->login();
        $customer = User::factory()->customer()->create(['phone' => '0901234567', 'address' => 'Địa chỉ fixture',
            'gender' => Gender::from($gender), 'date_of_birth' => '2000-02-29', 'status' => UserStatus::from($status),
            'avatar' => 'profile-avatars/private-fixture.png']);
        $before = DB::table('users')->orderBy('id')->get()->toJson();
        $this->browser('GET', '/api/admin/users/'.$customer->id)->assertOk()->assertExactJson(['data' => [
            'id' => $customer->id, 'name' => $customer->name, 'email' => $customer->email,
            'phone' => '0901234567', 'address' => 'Địa chỉ fixture', 'gender' => $gender,
            'date_of_birth' => '2000-02-29', 'status' => $status,
            'created_at' => now()->toISOString(), 'updated_at' => now()->toISOString(),
        ]]);
        $this->assertSame($before, DB::table('users')->orderBy('id')->get()->toJson());
    }

    public function test_detail_preserves_nullable_personal_fields_and_timestamps(): void
    {
        $this->login();
        $customer = User::factory()->customer()->create(['phone' => null, 'address' => null, 'gender' => null,
            'date_of_birth' => null, 'created_at' => null, 'updated_at' => null]);
        $response = $this->browser('GET', '/api/admin/users/'.$customer->id)->assertOk();
        foreach (['phone', 'address', 'gender', 'date_of_birth', 'created_at', 'updated_at'] as $field) {
            $response->assertJsonPath('data.'.$field, null);
        }
    }

    public function test_detail_hides_missing_host_admin_and_changed_role_behind_the_same_404(): void
    {
        $admin = $this->login();
        $host = User::factory()->host()->create();
        $customer = User::factory()->customer()->create();
        $this->listing()->assertOk()->assertJsonPath('data.0.id', $customer->id);
        $customer->update(['role' => UserRole::HOST]);
        foreach ([$admin->id, $host->id, $customer->id, 999999] as $id) {
            $this->browser('GET', '/api/admin/users/'.$id)->assertNotFound()->assertExactJson(['message' => 'Không tìm thấy khách hàng.']);
        }
    }

    public static function invalidDetailIds(): array
    {
        return [['0'], ['-1'], ['01'], ['+1'], ['1.0'], ['1e2'], ['abc'], ['9223372036854775808']];
    }

    #[DataProvider('invalidDetailIds')]
    public function test_detail_rejects_noncanonical_and_overflow_ids(string $id): void
    {
        $this->login();
        $this->browser('GET', '/api/admin/users/'.rawurlencode($id))->assertUnprocessable()->assertJsonValidationErrors('user');
    }

    public function test_detail_rejects_query_and_body_before_lookup(): void
    {
        $this->login();
        $this->browser('GET', '/api/admin/users/999999?role=CUSTOMER')->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->browser('GET', '/api/admin/users/999999?q=')->assertUnprocessable()->assertJsonValidationErrors('q');
        $this->browser('GET', '/api/admin/users/999999', ['status' => null])->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    #[DataProvider('deniedActors')]
    public function test_detail_service_denies_without_reading_the_database(string $role, string $status): void
    {
        $actor = User::factory()->make(['role' => UserRole::from($role), 'status' => UserStatus::from($status)]);
        DB::enableQueryLog();
        try {
            $this->app->make(AdminCustomerQueryService::class)->detail($actor, new AdminCustomerDetailQuery(1));
            $this->fail('Expected authorization denial.');
        } catch (AuthorizationException) {
            $this->assertSame([], DB::getQueryLog());
        } finally { DB::disableQueryLog(); }
    }
}
