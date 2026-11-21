<?php

namespace Tests\Feature\Auth;

use App\DTOs\Auth\Requests\LoginRequest;
use App\DTOs\Auth\Responses\AuthUserResponse;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\Auth\LoginThrottledException;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Models\User;
use App\Mappers\Auth\AuthUserMapper;
use App\Services\Auth\AuthService;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Session\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;

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
        $this->freezeSecond();
        $user = $this->user();
        $timestamp = now()->toISOString();
        $expected = [
            'id' => $user->id, 'name' => 'UC2 Fixture', 'email' => $user->email,
            'phone' => null, 'address' => null, 'avatar' => null, 'gender' => null,
            'date_of_birth' => null, 'role' => 'CUSTOMER', 'status' => 'ACTIVE',
            'email_verified_at' => null, 'created_at' => $timestamp, 'updated_at' => $timestamp,
        ];
        $this->login($user)->assertOk()
            ->assertExactJson(['message' => 'Đăng nhập thành công.', 'user' => $expected]);
        $this->browser('GET', '/api/me')->assertOk()->assertExactJson(['user' => $expected]);
    }

    public function test_container_maps_allowlisted_user_fields_through_symfony_without_queries(): void
    {
        $user = $this->user()->forceFill(['remember_token' => 'fixture-remember-token']);
        $before = $user->getAttributes();
        $engine = app(ObjectMapperInterface::class);
        $spy = $this->createMock(ObjectMapperInterface::class);
        $spy->expects($this->once())->method('map')->willReturnCallback(function (object $source, $target) use ($engine) {
            $this->assertInstanceOf(\stdClass::class, $source);
            $this->assertSame(AuthUserResponse::class, $target);
            $fields = get_object_vars($source);
            $this->assertCount(13, $fields);
            $this->assertArrayNotHasKey('password', $fields);
            $this->assertArrayNotHasKey('remember_token', $fields);
            $this->assertSame(UserRole::CUSTOMER, $fields['role']);

            return $engine->map($source, $target);
        });
        $this->app->instance(ObjectMapperInterface::class, $spy);
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $dto = app(AuthUserMapper::class)->toResponse($user);
            $data = json_decode(json_encode($dto, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
            $this->assertTrue((new \ReflectionClass($dto))->isReadOnly());
            $this->assertSame('CUSTOMER', $data['role']);
            $this->assertNull($data['email_verified_at']);
            $this->assertSame([], DB::getQueryLog());
            $this->assertSame($before, $user->getAttributes());
            $this->assertSame([], $user->getRelations());
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
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

    public function test_login_keeps_email_case_password_spaces_and_body_over_query(): void
    {
        $user = $this->user(UserRole::HOST);
        $password = '  spaced fixture password  ';
        $user->update(['email' => 'MixedCase@example.test', 'password' => $password]);

        $this->browser('POST', '/login?email=query@example.test&password=wrong&expected_role=ADMIN', [
            'email' => '  MixedCase@example.test  ',
            'password' => $password,
            'expected_role' => 'HOST',
        ])->assertOk()->assertJsonPath('user.email', 'MixedCase@example.test')->assertJsonPath('user.role', 'HOST');
        $this->browser('GET', '/api/me')->assertOk()->assertJsonPath('user.id', $user->id);
    }

    public function test_login_validation_preserves_json_errors_and_html_old_input(): void
    {
        $this->browser('POST', '/login', ['expected_role' => null])
            ->assertUnprocessable()->assertExactJson([
                'message' => 'Vui lòng nhập địa chỉ email. (and 2 more errors)',
                'errors' => [
                    'email' => ['Vui lòng nhập địa chỉ email.'],
                    'password' => ['Vui lòng nhập mật khẩu.'],
                    'expected_role' => ['Vui lòng chọn khu vực đăng nhập.'],
                ],
            ]);

        $this->from('/login')->post('/login', [
            'email' => '  MixedCase@example.test  ',
            'password' => 'fixture password',
            'expected_role' => 'OTHER',
        ])->assertRedirect('/login')->assertSessionHasErrors(['expected_role' => 'Khu vực đăng nhập không hợp lệ.'])
            ->assertSessionHasInput('email', 'MixedCase@example.test')
            ->assertSessionHasInput('expected_role', 'OTHER')
            ->assertSessionMissing('_old_input.password');
    }

    public function test_login_passes_validated_dto_request_ip_and_session_to_service(): void
    {
        $user = $this->user(UserRole::HOST);
        $workflow = app(AuthService::class);
        $service = $this->createPartialMock(AuthService::class, ['login']);
        $seenRoles = [];
        $service->expects($this->exactly(2))->method('login')
            ->willReturnCallback(function (LoginRequest $credentials, string $ip, Session $session) use ($workflow, $user, &$seenRoles): User {
                $this->assertSame(['email', 'password', 'expectedRole'], array_keys(get_object_vars($credentials)));
                $this->assertSame($user->email, $credentials->email);
                $this->assertTrue(hash_equals(self::PASSWORD, $credentials->password));
                $this->assertSame('127.0.0.1', $ip);
                $this->assertSame(request()->session(), $session);
                $seenRoles[] = $credentials->expectedRole;

                return $workflow->login($credentials, $ip, $session);
            });
        $this->app->instance(AuthService::class, $service);

        $this->login($user, ['expected_role' => null])->assertUnprocessable();
        $this->login($user, ['role' => 'ADMIN', 'remember' => true])->assertOk();
        $this->login($user, ['expected_role' => 'HOST'])->assertOk();
        $this->assertSame([null, UserRole::HOST], $seenRoles);
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
            ->assertExactJson([
                'message' => 'Bạn đã thử đăng nhập quá nhiều lần. Vui lòng thử lại sau.',
                'retry_after' => 60,
            ]);
        $this->travel(59)->seconds();
        $this->login($user)->assertStatus(429)->assertHeader('Retry-After', '1')
            ->assertJsonPath('retry_after', 1);
        $this->travel(2)->seconds();
        $this->login($user)->assertOk();
    }

    public function test_rate_limited_service_throws_retry_facts_without_a_reportable_http_response(): void
    {
        $this->freezeTime();
        $user = $this->user();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->login($user, ['password' => 'wrong'])->assertUnprocessable();
        }

        try {
            app(AuthService::class)->login(new LoginRequest($user->email, self::PASSWORD, null), '127.0.0.1', $this->app['session']->driver());
            $this->fail('Expected the login throttle exception.');
        } catch (LoginThrottledException $exception) {
            $this->assertSame(60, $exception->retryAfter);
            $this->assertFalse(app(ExceptionHandler::class)->shouldReport($exception));
        }
    }

    public function test_rate_limiting_keeps_an_existing_authenticated_session(): void
    {
        $active = $this->user();
        $limited = $this->user();
        $this->login($active)->assertOk();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->login($limited, ['password' => 'wrong'])->assertUnprocessable();
        }

        $this->login($limited)->assertStatus(429);
        $this->browser('GET', '/api/me')->assertOk()->assertJsonPath('user.id', $active->id);
    }

    public function test_login_throttle_keeps_its_json_contract_for_html_requests(): void
    {
        $this->freezeTime();
        $user = $this->user();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->login($user, ['password' => 'wrong'])->assertUnprocessable();
        }

        $this->withHeader('Accept', 'text/html')->post('/login', [
            'email' => $user->email, 'password' => self::PASSWORD,
        ])->assertStatus(429)->assertHeader('Content-Type', 'application/json')->assertHeader('Retry-After', '60')
            ->assertExactJson([
                'message' => 'Bạn đã thử đăng nhập quá nhiều lần. Vui lòng thử lại sau.',
                'retry_after' => 60,
            ]);
    }

    #[DataProvider('rejectedStatuses')]
    public function test_account_denial_keeps_its_json_contract_for_html_requests(string $status, string $code): void
    {
        $user = $this->user(status: UserStatus::from($status));
        $messages = [
            'BLOCKED' => 'Tài khoản của bạn đã bị khóa. Vui lòng liên hệ quản trị viên.',
            'INACTIVE' => 'Tài khoản của bạn chưa được kích hoạt. Vui lòng liên hệ quản trị viên.',
        ];

        $this->withHeader('Accept', 'text/html')->post('/login', [
            'email' => $user->email, 'password' => self::PASSWORD,
        ])->assertForbidden()->assertHeader('Content-Type', 'application/json')
            ->assertExactJson(['message' => $messages[$status], 'code' => $code]);
        $this->browser('GET', '/api/me')->assertUnauthorized();
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
        $this->browser('GET', '/api/host/invoices')->assertForbidden()->assertJsonPath('code', 'INVOICE_READ_FORBIDDEN');
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
        $router = $this->app['router'];
        foreach ($router->getRoutes() as $route) {
            // Identify intended protection before exclusions so removing auth cannot hide a route.
            $declared = $router->resolveMiddleware($route->gatherMiddleware());
            if (in_array(Authenticate::class.':sanctum', $declared, true)) {
                $this->assertProtectedRouteMiddleware($route);
            }
        }
    }

    public function test_known_protected_routes_exist_and_keep_auth_before_account_status(): void
    {
        $expected = [
            ['GET', 'api/me'],
            ['GET', 'api/profile'],
            ['PATCH', 'api/profile'],
            ['PATCH', 'api/profile/password'],
            ['GET', 'api/profile/avatar'],
            ['POST', 'api/profile/avatar'],
            ['GET', 'api/admin/dashboard'],
            ['GET', 'api/admin/statistics'],
            ['GET', 'api/admin/invoices/filter-options'],
            ['GET', 'api/admin/invoices'],
            ['GET', 'api/host/invoices'],
            ['POST', 'api/host/bookings/{booking}/invoice'],
            ['GET', 'api/host/invoices/{invoice}'],
            ['PATCH', 'api/host/invoices/{invoice}/payment-status'],
            ['GET', 'api/customer/invoices'],
            ['GET', 'api/customer/invoices/{invoice}'],
            ['POST', 'api/customer/bookings/{booking}/review'],
            ['GET', 'api/customer/reviews'],
            ['GET', 'api/host/reviews'],
            ['GET', 'api/host/properties'],
            ['POST', 'api/host/properties'],
            ['PUT', 'api/host/properties/{property}'],
            ['PATCH', 'api/host/properties/{property}/status'],
            ['GET', 'api/host/properties/{property}'],
            ['GET', 'api/host/properties/{property}/images'],
            ['POST', 'api/host/properties/{property}/images'],
            ['PATCH', 'api/host/properties/{property}/images/{image}/primary'],
            ['DELETE', 'api/host/properties/{property}/images/{image}'],
            ['GET', 'api/host/properties/{property}/room-types'],
            ['GET', 'api/host/properties/{property}/rooms'],
            ['POST', 'api/host/properties/{property}/rooms'],
            ['PATCH', 'api/host/rooms/{room}/status'],
            ['PUT', 'api/host/rooms/{room}'],
            ['GET', 'api/host/rooms/{room}'],
            ['POST', 'api/host/properties/{property}/room-types'],
            ['PUT', 'api/host/room-types/{roomType}'],
            ['DELETE', 'api/host/room-types/{roomType}'],
        ];

        foreach ($expected as [$method, $uri]) {
            $label = $method.' '.$uri;
            $route = $this->app['router']->getRoutes()->get($method)[$uri] ?? null;
            $this->assertNotNull($route, $label);
            $this->assertProtectedRouteMiddleware($route);
        }
    }

    public function test_known_public_routes_exist_without_auth_or_account_status_middleware(): void
    {
        $expected = [
            ['POST', 'login'],
            ['POST', 'register'],
            ['POST', 'logout'],
            ['GET', 'api/test'],
            ['GET', 'api/public/properties'],
            ['GET', 'api/public/properties/{property}'],
            ['GET', 'api/public/properties/{property}/reviews'],
            ['GET', 'api/public/rooms'],
            ['GET', 'api/public/rooms/filter-options'],
        ];

        foreach ($expected as [$method, $uri]) {
            $label = $method.' '.$uri;
            $route = $this->app['router']->getRoutes()->get($method)[$uri] ?? null;
            $this->assertNotNull($route, $label);
            $middleware = array_map(
                static fn ($entry) => is_string($entry) ? explode(':', $entry, 2)[0] : $entry,
                $this->app['router']->gatherRouteMiddleware($route),
            );
            $this->assertNotContains(Authenticate::class, $middleware, $label);
            $this->assertNotContains(EnsureAccountIsActive::class, $middleware, $label);
        }
    }

    private function assertProtectedRouteMiddleware(Route $route): void
    {
        $label = $route->methods()[0].' '.$route->uri();
        $middleware = $this->app['router']->gatherRouteMiddleware($route);
        $this->assertContains(Authenticate::class.':sanctum', $middleware, $label);
        $this->assertContains(EnsureAccountIsActive::class, $middleware, $label);
        $this->assertLessThan(
            array_search(EnsureAccountIsActive::class, $middleware, true),
            array_search(Authenticate::class.':sanctum', $middleware, true),
            $label,
        );
    }

    public static function routeProtectionRegressions(): array
    {
        $regressions = [
            'excluded account alias' => [['account.active'], false],
            'excluded auth alias' => [['auth:sanctum'], false],
            'excluded both guards' => [['auth:sanctum', 'account.active'], false],
            'excluded account class' => [[EnsureAccountIsActive::class], false],
            'excluded auth class' => [[Authenticate::class.':sanctum'], false],
            'reversed middleware priority' => [[], true],
        ];
        $cases = [];
        foreach (['test_every_existing_protected_route_checks_account_status_after_auth',
            'test_known_protected_routes_exist_and_keep_auth_before_account_status'] as $gate) {
            foreach ($regressions as $name => [$excluded, $reversePriority]) {
                $cases[$gate.' '.$name] = [$gate, $excluded, $reversePriority];
            }
        }

        return $cases;
    }

    #[DataProvider('routeProtectionRegressions')]
    public function test_route_gates_detect_excluded_guards_and_wrong_priority(string $gate, array $excluded, bool $reversePriority): void
    {
        $router = $this->app['router'];
        $route = $router->getRoutes()->get('GET')['api/me'];
        $action = $route->getAction();
        $priority = $router->middlewarePriority;
        try {
            $route->withoutMiddleware($excluded);
            if ($reversePriority) {
                $router->middlewarePriority = [EnsureAccountIsActive::class, Authenticate::class];
            }
            $this->expectException(AssertionFailedError::class);
            $this->expectExceptionMessage('GET api/me');
            $this->$gate();
        } finally {
            $route->setAction($action);
            $route->computedMiddleware = null;
            $router->middlewarePriority = $priority;
        }
    }

    public function test_route_gates_accept_resolved_groups_and_new_fully_qualified_routes(): void
    {
        $router = $this->app['router'];
        $router->middlewareGroup('e1-protected', ['auth:sanctum', 'account.active']);
        $route = $router->getRoutes()->get('GET')['api/me'];
        $action = $route->getAction();
        try {
            $grouped = $action;
            $grouped['middleware'] = ['api', 'e1-protected'];
            $route->setAction($grouped);
            $route->computedMiddleware = null;
            $router->get('/_e1/fully-qualified', static fn () => null)
                ->middleware([Authenticate::class.':sanctum', EnsureAccountIsActive::class]);

            $this->test_every_existing_protected_route_checks_account_status_after_auth();
            $this->test_known_protected_routes_exist_and_keep_auth_before_account_status();
            $this->test_known_public_routes_exist_without_auth_or_account_status_middleware();
        } finally {
            $route->setAction($action);
            $route->computedMiddleware = null;
        }
    }

    public function test_dynamic_route_gate_detects_a_new_fully_qualified_route_without_account_guard(): void
    {
        $this->app['router']->get('/_e1/fully-qualified', static fn () => null)
            ->middleware(Authenticate::class.':sanctum');

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('GET _e1/fully-qualified');
        $this->test_every_existing_protected_route_checks_account_status_after_auth();
    }

    public static function unexpectedPublicGuards(): array
    {
        return [['auth:sanctum'], [Authenticate::class.':web'], [EnsureAccountIsActive::class]];
    }

    #[DataProvider('unexpectedPublicGuards')]
    public function test_public_route_gate_detects_guards_inside_a_group(string $guard): void
    {
        $router = $this->app['router'];
        $router->middlewareGroup('e1-public-guard', [$guard]);
        $route = $router->getRoutes()->get('GET')['api/public/rooms'];
        $action = $route->getAction();
        try {
            $route->middleware('e1-public-guard');
            $route->computedMiddleware = null;
            $this->expectException(AssertionFailedError::class);
            $this->expectExceptionMessage('GET api/public/rooms');
            $this->test_known_public_routes_exist_without_auth_or_account_status_middleware();
        } finally {
            $route->setAction($action);
            $route->computedMiddleware = null;
        }
    }
}
