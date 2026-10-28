<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private ?string $sessionCookie = null;
    private const PASSWORD = 'fixture-password-UC2';

    public function createApplication()
    {
        // Runs before application boot and, critically, before RefreshDatabase.
        foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:',
            'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'] as $key => $expected) {
            $actual = $_ENV[$key] ?? getenv($key);
            if ($actual !== $expected) {
                throw new RuntimeException('UC2 tests require an isolated SQLite in-memory environment.');
            }
        }
        if (is_file(dirname(__DIR__, 3).'/bootstrap/cache/config.php')) {
            throw new RuntimeException('UC2 tests cannot run with a cached application configuration.');
        }

        $app = parent::createApplication();
        if ($app['config']->get('database.default') !== 'sqlite'
            || $app['config']->get('database.connections.sqlite.database') !== ':memory:'
            || !empty($app['config']->get('database.connections.sqlite.url'))
            || $app['config']->get('session.driver') !== 'array'
            || $app['config']->get('cache.default') !== 'array') {
            throw new RuntimeException('UC2 test configuration must be isolated before migrations run.');
        }

        // Model a first-party SPA origin for session-authenticated API requests.
        $app['config']->set('sanctum.stateful', ['localhost:5173']);

        return $app;
    }

    private function user(UserRole $role = UserRole::CUSTOMER, UserStatus $status = UserStatus::ACTIVE): User
    {
        return User::create([
            'name' => 'UC2 Fixture',
            'email' => 'uc2-'.User::count().'@example.test',
            'password' => self::PASSWORD,
            'role' => $role,
            'status' => $status,
        ]);
    }

    /** Start a new auth guard per request and carry the session cookie, never actingAs. */
    private function browser(string $method, string $uri, array $data = []): TestResponse
    {
        Auth::forgetGuards();
        Auth::shouldUse('web');
        $this->withCredentials();
        if ($this->sessionCookie !== null) {
            $this->withCookie(config('session.cookie'), $this->sessionCookie);
        }

        $response = $this->json($method, $uri, $data, [
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
        ]);

        $cookie = $response->getCookie(config('session.cookie'));
        if ($cookie !== null) {
            $this->sessionCookie = $cookie->getValue();
        }

        return $response;
    }

    private function login(User $user, array $extra = []): TestResponse
    {
        return $this->browser('POST', '/login', [
            'email' => $user->email,
            'password' => self::PASSWORD,
            ...$extra,
        ]);
    }

    public static function roles(): array
    {
        return [['CUSTOMER'], ['HOST'], ['ADMIN']];
    }

    #[DataProvider('roles')]
    public function test_active_users_login_and_restore_through_the_session_cookie(string $role): void
    {
        $user = $this->user(UserRole::from($role));
        $this->login($user)->assertOk()->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.role', $role)->assertJsonPath('user.status', 'ACTIVE')
            ->assertJsonMissingPath('data')->assertJsonMissingPath('user.password')
            ->assertJsonMissingPath('user.remember_token');

        $this->assertNotNull($this->sessionCookie);
        $this->browser('GET', '/api/me')->assertOk()->assertJsonPath('user.id', $user->id)
            ->assertJsonMissingPath('data')->assertJsonMissingPath('user.password');
    }

    public function test_login_response_only_serializes_the_allowed_user_fields(): void
    {
        $response = $this->login($this->user())->assertOk();
        $keys = array_keys($response->json('user'));
        sort($keys);
        $expected = ['id', 'name', 'email', 'phone', 'address', 'avatar', 'role', 'status',
            'email_verified_at', 'created_at', 'updated_at'];
        sort($expected);
        $this->assertSame($expected, $keys);
    }

    public function test_invalid_credentials_do_not_reveal_whether_the_email_exists(): void
    {
        $user = $this->user();
        $wrong = $this->browser('POST', '/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $unknown = $this->browser('POST', '/login', ['email' => 'unknown@example.test', 'password' => 'wrong'])
            ->assertUnprocessable();
        $this->assertSame($wrong->json(), $unknown->json());
        $this->browser('GET', '/api/me')->assertUnauthorized();
    }

    public static function invalidFields(): array
    {
        return [
            [[], ['email', 'password']],
            [['email' => 'invalid', 'password' => 'value'], ['email']],
            [['email' => ['bad'], 'password' => 'value'], ['email']],
            [['email' => 'valid@example.test', 'password' => ['bad']], ['password']],
        ];
    }

    #[DataProvider('invalidFields')]
    public function test_login_validates_fields(array $payload, array $fields): void
    {
        $this->browser('POST', '/login', $payload)->assertUnprocessable()->assertJsonValidationErrors($fields);
    }

    public function test_client_cannot_override_role_status_or_identity(): void
    {
        $user = $this->user();
        $this->login($user, ['role' => 'ADMIN', 'status' => 'BLOCKED', 'id' => 999, 'remember' => true])
            ->assertOk()->assertJsonPath('user.role', 'CUSTOMER')->assertJsonPath('user.status', 'ACTIVE')
            ->assertJsonPath('user.id', $user->id);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => 'CUSTOMER', 'status' => 'ACTIVE']);
        $this->assertNull($user->fresh()->remember_token);
    }

    public function test_login_regenerates_the_session_id_and_logout_invalidates_it(): void
    {
        $user = $this->user();
        $this->browser('POST', '/logout')->assertOk();
        $beforeLogin = $this->sessionCookie;
        $this->login($user)->assertOk();
        $this->assertNotSame($beforeLogin, $this->sessionCookie);
        $beforeLogout = $this->sessionCookie;
        $csrfBeforeLogout = $this->app['session']->token();
        $this->browser('POST', '/logout')->assertOk()->assertExactJson(['message' => 'Đăng xuất thành công.']);
        $this->assertNotSame($beforeLogout, $this->sessionCookie);
        $this->assertNotSame($csrfBeforeLogout, $this->app['session']->token());
        $this->assertNull($this->app['session']->get(Auth::guard('web')->getName()));
        // Replaying the previous session cookie must not restore its identity.
        $this->sessionCookie = $beforeLogout;
        $this->browser('GET', '/api/me')->assertUnauthorized();
    }

    public function test_guest_cannot_restore_a_user(): void
    {
        $this->browser('GET', '/api/me')->assertUnauthorized();
    }

    public static function rejectedStatuses(): array
    {
        return [['BLOCKED', 'ACCOUNT_BLOCKED'], ['INACTIVE', 'ACCOUNT_INACTIVE']];
    }

    #[DataProvider('rejectedStatuses')]
    public function test_non_active_accounts_are_only_identified_after_correct_credentials(string $status, string $code): void
    {
        $user = $this->user(status: UserStatus::from($status));
        $this->login($user, ['password' => 'wrong'])->assertUnprocessable()->assertJsonMissingPath('code');
        $this->login($user)->assertForbidden()->assertJsonPath('code', $code)->assertJsonMissingPath('user');
        $this->browser('GET', '/api/me')->assertUnauthorized();
    }

    public function test_rejected_login_also_clears_a_previously_authenticated_session(): void
    {
        $active = $this->user();
        $blocked = $this->user(status: UserStatus::BLOCKED);
        $this->login($active)->assertOk();
        $this->login($blocked)->assertForbidden()->assertJsonPath('code', 'ACCOUNT_BLOCKED');
        $this->browser('GET', '/api/me')->assertUnauthorized();
    }

    public function test_sixth_failed_attempt_is_rate_limited_with_retry_after_and_expires_without_sleep(): void
    {
        $this->freezeTime();
        $user = $this->user();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->login($user, ['password' => 'wrong'])->assertUnprocessable();
        }
        $this->login($user)->assertStatus(429)->assertHeader('Retry-After', '60')
            ->assertJsonPath('retry_after', 60);
        $this->travel(61)->seconds();
        $this->login($user)->assertOk();
    }

    public function test_successful_login_resets_the_failed_attempt_counter(): void
    {
        $user = $this->user();
        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->login($user, ['password' => 'wrong'])->assertUnprocessable();
        }
        $this->login($user)->assertOk();
        $this->browser('POST', '/logout')->assertOk();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->login($user, ['password' => 'wrong'])->assertUnprocessable();
        }
        $this->login($user)->assertStatus(429);
    }

    public function test_blocked_account_failures_count_toward_the_same_rate_limit(): void
    {
        $user = $this->user(status: UserStatus::BLOCKED);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->login($user)->assertForbidden();
        }
        $this->login($user)->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_rate_limit_normalizes_email_and_ignores_client_role(): void
    {
        $user = $this->user();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->login($user, ['email' => '  '.strtoupper($user->email).'  ', 'password' => 'wrong', 'role' => 'HOST'])
                ->assertUnprocessable();
        }
        $this->login($user, ['role' => 'ADMIN'])->assertStatus(429);
    }

    public function test_rate_limit_keys_are_separate_for_other_emails_and_ips(): void
    {
        $user = $this->user();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->login($user, ['password' => 'wrong'])->assertUnprocessable();
        }
        $this->login($this->user())->assertOk();
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10']);
        $this->login($user)->assertOk();
    }

    public static function sessionRejections(): array
    {
        $cases = [];
        foreach (self::rejectedStatuses() as [$status, $code]) {
            foreach (['/api/me', '/api/customer/invoices', '/api/customer/reviews'] as $uri) {
                $cases[] = [$status, $code, $uri];
            }
        }
        return $cases;
    }

    #[DataProvider('sessionRejections')]
    public function test_a_real_login_session_is_rejected_on_the_next_request_after_status_changes(string $status, string $code, string $uri): void
    {
        $user = $this->user();
        $this->login($user)->assertOk();
        $this->browser('GET', '/api/me')->assertOk();
        $user->update(['status' => $status]);
        $this->browser('GET', $uri)->assertForbidden()->assertJsonPath('code', $code);
        $this->browser('GET', '/api/me')->assertUnauthorized();
    }

    #[DataProvider('rejectedStatuses')]
    public function test_logout_is_available_after_a_logged_in_account_becomes_non_active(string $status, string $code): void
    {
        $user = $this->user();
        $this->login($user)->assertOk();
        $user->update(['status' => $status]);
        $this->browser('POST', '/logout')->assertOk();
        $this->browser('GET', '/api/me')->assertUnauthorized();
    }

    public function test_role_denial_keeps_the_session_and_public_routes_remain_public(): void
    {
        $this->login($this->user())->assertOk();
        $this->browser('GET', '/api/host/invoices')->assertForbidden()->assertJsonMissingPath('code');
        $this->browser('GET', '/api/me')->assertOk();
        $this->browser('GET', '/api/test')->assertOk();
        $this->browser('GET', '/api/public/properties/999999/reviews')->assertNotFound();
    }

    public function test_public_endpoints_do_not_enforce_account_status_on_an_existing_session(): void
    {
        $user = $this->user();
        $this->login($user)->assertOk();
        $user->update(['status' => UserStatus::BLOCKED]);
        $this->browser('GET', '/api/test')->assertOk();
        $this->browser('GET', '/api/public/properties/999999/reviews')->assertNotFound();
        $this->browser('GET', '/api/me')->assertForbidden()->assertJsonPath('code', 'ACCOUNT_BLOCKED');
    }

    #[DataProvider('roles')]
    public function test_matching_login_context_creates_a_session_for_the_actual_role(string $role): void
    {
        $user = $this->user(UserRole::from($role));
        $this->login($user, ['expected_role' => $role])->assertOk()->assertJsonPath('user.role', $role);
        $this->browser('GET', '/api/me')->assertOk()->assertJsonPath('user.id', $user->id);
    }

    public static function mismatchedRoles(): array
    {
        return [
            ['HOST', 'CUSTOMER', 'Khách hàng'],
            ['CUSTOMER', 'HOST', 'Đối tác (Host)'],
            ['ADMIN', 'CUSTOMER', 'Khách hàng'],
            ['ADMIN', 'HOST', 'Đối tác (Host)'],
            ['HOST', 'ADMIN', 'Quản trị viên'],
            ['CUSTOMER', 'ADMIN', 'Quản trị viên'],
        ];
    }

    #[DataProvider('mismatchedRoles')]
    public function test_wrong_login_context_never_creates_a_session_or_changes_the_role(string $actualRole, string $expectedRole, string $area): void
    {
        $user = $this->user(UserRole::from($actualRole));
        $this->login($user, ['expected_role' => $expectedRole, 'role' => $expectedRole])
            ->assertUnprocessable()->assertJsonValidationErrors('email')
            ->assertJsonPath('errors.email.0', "Tài khoản này không thuộc khu vực {$area}. Vui lòng chọn đúng khu vực đăng nhập.")
            ->assertJsonMissingPath('user');
        $this->browser('GET', '/api/me')->assertUnauthorized();
        $this->assertSame($actualRole, $user->fresh()->role->value);
    }

    public function test_wrong_password_does_not_reveal_role_mismatch(): void
    {
        $user = $this->user(UserRole::HOST);
        $wrong = $this->login($user, ['password' => 'wrong', 'expected_role' => 'CUSTOMER'])
            ->assertUnprocessable()->assertJsonPath('errors.email.0', 'Email hoặc mật khẩu không chính xác.');
        $unknown = $this->browser('POST', '/login', [
            'email' => 'unknown@example.test', 'password' => 'wrong', 'expected_role' => 'CUSTOMER',
        ])->assertUnprocessable();
        $this->assertSame($wrong->json(), $unknown->json());
    }

    public function test_role_mismatch_clears_any_previously_authenticated_session(): void
    {
        $user = $this->user(UserRole::HOST);
        $this->login($user, ['expected_role' => 'HOST'])->assertOk();
        $this->login($user, ['expected_role' => 'CUSTOMER'])->assertUnprocessable();
        $this->browser('GET', '/api/me')->assertUnauthorized();
    }

    public static function invalidContexts(): array
    {
        return [['ROOT'], [''], [null], [['HOST']]];
    }

    #[DataProvider('invalidContexts')]
    public function test_invalid_login_context_is_rejected_before_authentication(mixed $context): void
    {
        $response = $this->login($this->user(), ['expected_role' => $context])
            ->assertUnprocessable()->assertJsonValidationErrors('expected_role');
        if ($context === 'ROOT') {
            $response->assertJsonPath('errors.expected_role.0', 'Khu vực đăng nhập không hợp lệ.');
        }
        $this->browser('GET', '/api/me')->assertUnauthorized();
    }

    public function test_switching_login_context_does_not_bypass_the_failed_login_limit(): void
    {
        $this->freezeTime();
        $user = $this->user(UserRole::HOST);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->login($user, ['expected_role' => 'CUSTOMER'])->assertUnprocessable();
        }
        $this->login($user, ['expected_role' => 'HOST'])->assertStatus(429)->assertHeader('Retry-After');
        $this->travel(61)->seconds();
        $this->login($user, ['expected_role' => 'HOST'])->assertOk();
    }

    #[DataProvider('rejectedStatuses')]
    public function test_account_status_denial_is_preserved_even_in_a_different_login_context(string $status, string $code): void
    {
        $user = $this->user(UserRole::HOST, UserStatus::from($status));
        $this->login($user, ['expected_role' => 'CUSTOMER'])->assertForbidden()->assertJsonPath('code', $code);
        $this->browser('GET', '/api/me')->assertUnauthorized();
    }

    public function test_every_existing_protected_route_checks_account_status_after_auth(): void
    {
        $protected = 0;
        foreach ($this->app['router']->getRoutes() as $route) {
            $middleware = $route->gatherMiddleware();
            if (in_array('auth:sanctum', $middleware, true)) {
                $protected++;
                $this->assertContains('account.active', $middleware, $route->uri());
                $this->assertLessThan(array_search('account.active', $middleware, true), array_search('auth:sanctum', $middleware, true));
            } elseif (in_array($route->uri(), ['login', 'logout', 'api/test', 'api/public/properties/{property}/reviews'], true)) {
                $this->assertNotContains('account.active', $middleware);
            }
        }
        $this->assertSame(9, $protected);
    }
}
