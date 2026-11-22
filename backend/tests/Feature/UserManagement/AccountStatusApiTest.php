<?php

namespace Tests\Feature\UserManagement;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Repositories\UserManagement\AccountStatusRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class AccountStatusApiTest extends TestCase
{
    use RefreshDatabase;

    private ?string $sessionCookie = null;

    public function createApplication()
    {
        foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:',
            'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'] as $key => $expected) {
            if (($_ENV[$key] ?? getenv($key)) !== $expected) {
                throw new RuntimeException('UC42 HTTP tests require isolated SQLite in-memory before boot.');
            }
        }
        if (is_file(dirname(__DIR__, 3).'/bootstrap/cache/config.php')) {
            throw new RuntimeException('UC42 HTTP tests cannot run with cached configuration.');
        }
        $app = parent::createApplication();
        if ($app['config']->get('database.default') !== 'sqlite'
            || $app['config']->get('database.connections.sqlite.database') !== ':memory:'
            || !empty($app['config']->get('database.connections.sqlite.url'))) {
            throw new RuntimeException('UC42 HTTP database isolation must hold before migrations.');
        }
        $app['config']->set('sanctum.stateful', ['localhost:5173']);
        $app['config']->set('session.driver', 'database');

        return $app;
    }

    private function browser(string $method, string $uri, array $body = []): TestResponse
    {
        Auth::forgetGuards();
        Auth::shouldUse('web');
        $this->app['session']->driver()->flush();
        $this->withCredentials()->withCookie(config('session.cookie'), $this->sessionCookie ?? '');
        $response = $this->json($method, $uri, $body,
            ['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/']);
        if ($cookie = $response->getCookie(config('session.cookie'))) {
            $this->sessionCookie = $cookie->getValue();
        }

        return $response;
    }

    private function login(?User $user = null): User
    {
        $user ??= User::factory()->admin()->create();
        $this->sessionCookie = null;
        $this->browser('POST', '/login', ['email' => $user->email, 'password' => 'password'])->assertOk();

        return $user;
    }

    private function patchStatus(int|string $id, array $body = ['status' => 'BLOCKED', 'expected_status' => 'ACTIVE'], string $query = ''): TestResponse
    {
        return $this->browser('PATCH', '/api/admin/users/'.$id.'/status'.$query, $body);
    }

    private function snapshot(): string { return DB::table('users')->orderBy('id')->get()->toJson(); }

    public function test_route_is_registered_with_the_required_middleware(): void
    {
        $route = app('router')->getRoutes()->getByName('admin.users.status.update');
        $this->assertNotNull($route);
        $this->assertSame(['PATCH'], $route->methods());
        $this->assertSame('api/admin/users/{user}/status', $route->uri());
        foreach (['auth:sanctum', 'account.active', 'role:ADMIN'] as $middleware) {
            $this->assertContains($middleware, $route->gatherMiddleware());
        }
    }

    public function test_guest_is_denied_before_input_validation(): void
    {
        $this->patchStatus('bad', [])->assertUnauthorized();
    }

    public static function roles(): array { return [['CUSTOMER'], ['HOST']]; }

    #[DataProvider('roles')]
    public function test_non_admin_is_denied_before_input_validation(string $role): void
    {
        $this->login(User::factory()->create(['role' => UserRole::from($role)]));
        $before = $this->snapshot();
        $this->patchStatus('bad', [])->assertForbidden();
        $this->assertSame($before, $this->snapshot());
    }

    public static function rejectedActorStatuses(): array { return [['BLOCKED'], ['INACTIVE']]; }

    #[DataProvider('rejectedActorStatuses')]
    public function test_existing_admin_session_is_invalidated_when_actor_becomes_inactive(string $status): void
    {
        $actor = $this->login();
        $target = User::factory()->create();
        $actor->update(['status' => UserStatus::from($status)]);
        $this->patchStatus($target->id)->assertForbidden()->assertJsonPath('code', 'ACCOUNT_'.$status);
        $this->assertSame(UserStatus::ACTIVE, $target->fresh()->status);
        $this->browser('GET', '/api/me')->assertUnauthorized();
    }

    public static function transitions(): array
    {
        return [['CUSTOMER', 'ACTIVE', 'BLOCKED'], ['CUSTOMER', 'BLOCKED', 'ACTIVE'],
            ['HOST', 'ACTIVE', 'BLOCKED'], ['HOST', 'BLOCKED', 'ACTIVE']];
    }

    #[DataProvider('transitions')]
    public function test_admin_changes_status_with_exact_response_and_write_allowlist(string $role, string $from, string $to): void
    {
        $this->freezeSecond();
        $this->login();
        $target = User::factory()->create(['role' => UserRole::from($role), 'status' => UserStatus::from($from),
            'updated_at' => now()->subDay()]);
        $before = $target->fresh()->getRawOriginal();

        $response = $this->patchStatus($target->id, ['status' => $to, 'expected_status' => $from])->assertOk();

        $this->assertSame(['data' => ['id' => $target->id, 'status' => $to,
            'updated_at' => now()->toISOString(), 'changed' => true]], $response->json());
        $this->assertSame(array_replace($before, ['status' => $to, 'updated_at' => now()->format('Y-m-d H:i:s')]),
            $target->fresh()->getRawOriginal());
    }

    public function test_matching_noop_preserves_timestamp_and_returns_changed_false(): void
    {
        $this->login();
        $target = User::factory()->create(['updated_at' => null]);
        $before = $this->snapshot();
        $this->patchStatus($target->id, ['status' => 'ACTIVE', 'expected_status' => 'ACTIVE'])->assertOk()
            ->assertExactJson(['data' => ['id' => $target->id, 'status' => 'ACTIVE', 'updated_at' => null, 'changed' => false]]);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_admin_self_and_missing_targets_have_the_same_private_404(): void
    {
        $actor = $this->login();
        $other = User::factory()->admin()->create();
        $before = $this->snapshot();
        foreach ([$actor->id, $other->id, $other->id + 100] as $id) {
            $this->patchStatus($id)->assertNotFound()->assertExactJson(['message' => 'Không tìm thấy tài khoản.']);
        }
        $this->assertSame($before, $this->snapshot());
    }

    public function test_inactive_target_is_a_conflict_without_an_account_failure_code(): void
    {
        $this->login();
        $target = User::factory()->create(['status' => UserStatus::INACTIVE]);
        $before = $this->snapshot();
        $this->patchStatus($target->id)->assertConflict()->assertJsonPath('code', 'ACCOUNT_STATUS_CONFLICT');
        $this->assertSame($before, $this->snapshot());
        $this->browser('GET', '/api/me')->assertOk();
    }

    public function test_repeated_stale_patch_conflicts_instead_of_succeeding_as_a_noop(): void
    {
        $this->login();
        $target = User::factory()->create();
        $this->patchStatus($target->id)->assertOk()->assertJsonPath('data.changed', true);
        $before = $this->snapshot();
        $this->patchStatus($target->id)->assertConflict()->assertJsonPath('code', 'ACCOUNT_STATUS_CONFLICT');
        $this->assertSame($before, $this->snapshot());
        $this->browser('GET', '/api/me')->assertOk();
    }

    public static function invalidInputs(): array
    {
        return [
            ['01', ['status' => 'BLOCKED', 'expected_status' => 'ACTIVE'], '', 'user'],
            ['0', ['status' => 'BLOCKED', 'expected_status' => 'ACTIVE'], '', 'user'],
            [(string) PHP_INT_MAX.'0', ['status' => 'BLOCKED', 'expected_status' => 'ACTIVE'], '', 'user'],
            ['12', [], '', 'status'], ['12', ['status' => 'BLOCKED'], '', 'expected_status'],
            ['12', ['status' => 'INACTIVE', 'expected_status' => 'ACTIVE'], '', 'status'],
            ['12', ['status' => ['BLOCKED'], 'expected_status' => 'ACTIVE'], '', 'status'],
            ['12', ['status' => 'BLOCKED', 'expected_status' => true], '', 'expected_status'],
            ['12', ['status' => 'BLOCKED', 'expected_status' => 'INACTIVE'], '', 'expected_status'],
            ['12', ['status' => 'BLOCKED', 'expected_status' => 'ACTIVE', 'role' => 'ADMIN'], '', 'role'],
            ['12', ['status' => 'BLOCKED', 'expected_status' => 'ACTIVE', 'actor_id' => 1], '', 'actor_id'],
            ['12', ['status' => 'BLOCKED', 'expected_status' => 'ACTIVE', 'user' => 99], '', 'user'],
            ['12', ['status' => 'BLOCKED', 'expected_status' => 'ACTIVE'], '?status=BLOCKED', 'status'],
        ];
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_input_returns_field_errors_without_writes(string $id, array $body, string $query, string $field): void
    {
        $this->login();
        $before = $this->snapshot();
        $this->patchStatus($id, $body, $query)->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertSame($before, $this->snapshot());
    }

    public static function accountSessions(): array
    {
        return [[UserRole::CUSTOMER, '/api/profile'], [UserRole::HOST, '/api/host/properties']];
    }

    #[DataProvider('accountSessions')]
    public function test_session_is_denied_after_block_and_not_restored_by_unblock(UserRole $role, string $protectedRoute): void
    {
        $target = $this->login(User::factory()->create(['role' => $role]));
        $targetCookie = $this->sessionCookie;
        $this->browser('GET', $protectedRoute)->assertOk();
        $this->login();
        $this->patchStatus($target->id)->assertOk();
        $adminCookie = $this->sessionCookie;

        $this->sessionCookie = $targetCookie;
        $this->browser('GET', $protectedRoute)->assertForbidden()->assertJsonPath('code', 'ACCOUNT_BLOCKED');
        $invalidatedCookie = $this->sessionCookie;
        $this->browser('GET', '/api/me')->assertUnauthorized();

        $this->sessionCookie = $adminCookie;
        $this->patchStatus($target->id, ['status' => 'ACTIVE', 'expected_status' => 'BLOCKED'])->assertOk();
        $this->sessionCookie = $invalidatedCookie;
        $this->browser('GET', '/api/me')->assertUnauthorized();
        $this->login($target);
        $this->browser('GET', $protectedRoute)->assertOk();
    }

    #[DataProvider('roles')]
    public function test_blocked_account_cannot_login_and_admin_session_stays_valid(string $role): void
    {
        $actor = $this->login();
        $target = User::factory()->create(['role' => UserRole::from($role)]);
        $this->patchStatus($target->id)->assertOk();
        $adminCookie = $this->sessionCookie;
        $this->sessionCookie = null;
        $this->browser('POST', '/login', ['email' => $target->email, 'password' => 'password'])
            ->assertForbidden()->assertJsonPath('code', 'ACCOUNT_BLOCKED');
        $this->sessionCookie = $adminCookie;
        $this->browser('GET', '/api/me')->assertOk()->assertJsonPath('user.id', $actor->id);
    }

    public function test_unexpected_persistence_failure_is_not_translated_to_a_business_conflict(): void
    {
        $this->login();
        $target = User::factory()->create();
        $before = $this->snapshot();
        $real = new AccountStatusRepository;
        $this->partialMock(AccountStatusRepository::class, function ($mock) use ($real): void {
            $mock->shouldReceive('updateStatus')->once()->andReturnUsing(static function (User $target, UserStatus $status) use ($real): never {
                $real->updateStatus($target, $status);
                throw new RuntimeException('UC42 HTTP rollback fixture.');
            });
        });
        config(['app.debug' => false]);
        $response = $this->patchStatus($target->id)->assertStatus(500);
        $this->assertStringNotContainsString('UC42 HTTP rollback fixture', $response->getContent());
        $this->assertSame($before, $this->snapshot());
    }
}
