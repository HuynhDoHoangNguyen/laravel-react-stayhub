<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\Auth\RegistrationService;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private ?string $sessionCookie = null;
    private const PASSWORD = 'UC1-fixture-password!';

    public function createApplication()
    {
        foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:',
            'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'] as $key => $expected) {
            if (($_ENV[$key] ?? getenv($key)) !== $expected) {
                throw new RuntimeException('UC1 tests require an isolated in-memory test environment before boot.');
            }
        }
        if (is_file(dirname(__DIR__, 3).'/bootstrap/cache/config.php')) {
            throw new RuntimeException('UC1 tests cannot run with cached application configuration.');
        }

        $app = parent::createApplication();
        if ($app['config']->get('database.default') !== 'sqlite'
            || $app['config']->get('database.connections.sqlite.database') !== ':memory:'
            || !empty($app['config']->get('database.connections.sqlite.url'))
            || $app['config']->get('cache.default') !== 'array'
            || $app['config']->get('session.driver') !== 'array') {
            throw new RuntimeException('UC1 database isolation must be verified before RefreshDatabase runs.');
        }
        $app['config']->set('sanctum.stateful', ['localhost:5173']);
        return $app;
    }

    private function browser(string $method, string $uri, array $data = []): TestResponse
    {
        Auth::forgetGuards();
        Auth::shouldUse('web');
        $this->withCredentials();
        if ($this->sessionCookie !== null) {
            $this->withCookie(config('session.cookie'), $this->sessionCookie);
        }
        $response = $this->json($method, $uri, $data, [
            'Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/',
        ]);
        if ($cookie = $response->getCookie(config('session.cookie'))) {
            $this->sessionCookie = $cookie->getValue();
        }
        return $response;
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Khách Fixture', 'email' => 'uc1@example.test',
            'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD,
        ], $overrides);
    }

    private function fixture(UserRole $role = UserRole::CUSTOMER): User
    {
        return User::create([
            'name' => 'Existing fixture', 'email' => 'existing@example.test',
            'password' => self::PASSWORD, 'role' => $role, 'status' => UserStatus::ACTIVE,
        ]);
    }

    public function test_registration_preflight_allows_frontend_credentials_without_consuming_attempts(): void
    {
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->options('/register', [], [
                'Origin' => 'http://localhost:5173',
                'Access-Control-Request-Method' => 'POST',
                'Access-Control-Request-Headers' => 'content-type,x-xsrf-token',
            ])->assertNoContent()
                ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173')
                ->assertHeader('Access-Control-Allow-Credentials', 'true')
                ->assertHeader('Access-Control-Allow-Methods', 'POST')
                ->assertHeader('Access-Control-Allow-Headers', 'content-type,x-xsrf-token');
        }

        $this->browser('POST', '/register', $this->payload())->assertCreated()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173')
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    public function test_registration_preflight_does_not_allow_an_untrusted_origin(): void
    {
        $response = $this->options('/register', [], [
            'Origin' => 'https://untrusted.example',
            'Access-Control-Request-Method' => 'POST',
        ])->assertNoContent();

        // A single configured origin may be returned as a fixed header; browsers
        // reject it because it does not match the requesting origin.
        $this->assertNotSame('https://untrusted.example', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertNotSame('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_guest_registers_one_safe_customer_and_must_login_explicitly(): void
    {
        $this->browser('POST', '/register', $this->payload())->assertCreated()
            ->assertExactJson(['message' => 'Đăng ký thành công. Vui lòng đăng nhập để tiếp tục.']);
        $this->assertDatabaseCount('users', 1);
        $user = User::firstOrFail();
        $this->assertSame(UserRole::CUSTOMER, $user->role);
        $this->assertSame(UserStatus::ACTIVE, $user->status);
        $this->assertNull($user->email_verified_at);
        $this->assertNull($user->remember_token);
        $this->assertTrue($user->password !== self::PASSWORD);
        $this->assertTrue(Hash::check(self::PASSWORD, $user->password));
        $this->browser('GET', '/api/me')->assertUnauthorized();
        $this->browser('POST', '/login', [
            'email' => $user->email, 'password' => self::PASSWORD, 'expected_role' => 'CUSTOMER',
        ])->assertOk()->assertJsonPath('user.id', $user->id);
        $this->browser('GET', '/api/me')->assertOk()->assertJsonPath('user.role', 'CUSTOMER');
    }

    public function test_name_email_are_trimmed_without_lowercasing_and_schema_boundaries_are_supported(): void
    {
        $name = str_repeat('a', 255);
        $password = 'Aa1!'.str_repeat('x', 68);
        $this->browser('POST', '/register', $this->payload([
            'name' => ' '.$name.' ', 'email' => ' Case.Person@Example.test ',
            'password' => $password, 'password_confirmation' => $password,
        ]))->assertCreated();
        $this->assertDatabaseHas('users', ['name' => $name, 'email' => 'Case.Person@Example.test']);
        $this->assertTrue(Hash::check($password, User::firstOrFail()->password));
    }

    public function test_a_hash_shaped_password_is_still_hashed_as_the_submitted_plaintext(): void
    {
        $literalPassword = password_hash('short', PASSWORD_BCRYPT, ['cost' => 4]);
        $this->browser('POST', '/register', $this->payload([
            'password' => $literalPassword, 'password_confirmation' => $literalPassword,
        ]))->assertCreated();
        $user = User::firstOrFail();
        $this->assertTrue(Hash::check($literalPassword, $user->password));
        $this->assertFalse(Hash::check('short', $user->password));
        $this->browser('POST', '/login', ['email' => $user->email, 'password' => 'short'])->assertUnprocessable();
        $this->browser('POST', '/login', ['email' => $user->email, 'password' => $literalPassword])->assertOk();
    }

    public static function invalidPayloads(): array
    {
        return [
            [['name' => null], 'name'], [['name' => '   '], 'name'], [['name' => ['bad']], 'name'],
            [['name' => str_repeat('a', 256)], 'name'], [['email' => null], 'email'],
            [['email' => 'invalid'], 'email'], [['email' => ['bad']], 'email'],
            [['email' => str_repeat('a', 256).'@example.test'], 'email'],
            [['password' => null], 'password'], [['password' => ['bad']], 'password'],
            [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
            [['password' => str_repeat('x', 73), 'password_confirmation' => str_repeat('x', 73)], 'password'],
            [['password' => str_repeat('á', 37), 'password_confirmation' => str_repeat('á', 37)], 'password'],
            [['password' => "valid\0password", 'password_confirmation' => "valid\0password"], 'password'],
            [['password_confirmation' => 'mismatch'], 'password'],
            [['password_confirmation' => null], 'password_confirmation'],
            [['password_confirmation' => ['bad']], 'password_confirmation'],
        ];
    }

    public static function invalidSignupEmails(): array
    {
        return array_map(fn (string $email): array => [$email], [
            'customer@localhost', 'customer@example', 'customer@example.c',
            'customer@example.123', 'customer name@example.com',
            'customer..name@example.com', '.customer@example.com', 'customer.@example.com',
            'customer@@example.com', 'customer@example..com', 'customer@-example.com',
            'customer@example-.com', 'customer@exam_ple.com', 'customer@example.com.',
            'customer@'.str_repeat('a', 64).'.com', str_repeat('a', 65).'@example.com',
            'customer@127.0.0.1', 'customer@[127.0.0.1]', '"customer"@example.com',
            "customer\nname@example.com", 'customer@vídụ.com',
        ]);
    }

    #[DataProvider('invalidSignupEmails')]
    public function test_malformed_signup_email_is_rejected_by_the_api(string $email): void
    {
        $this->browser('POST', '/register', $this->payload(['email' => $email]))
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertDatabaseCount('users', 0);
    }

    public static function validSignupEmails(): array
    {
        return [
            ['customer.name+booking@example.com'], ['Customer_1@sub.example.com.vn'],
            ["o'connor@example.travel"],
            [str_repeat('a', 64).'@'.str_repeat('b', 63).'.'.str_repeat('c', 63).'.'.str_repeat('d', 61)],
        ];
    }

    #[DataProvider('validSignupEmails')]
    public function test_valid_signup_email_is_preserved(string $email): void
    {
        $this->browser('POST', '/register', $this->payload(['email' => ' '.$email.' ']))->assertCreated();
        $this->assertDatabaseHas('users', ['email' => $email]);
    }

    public static function weakSignupPasswords(): array
    {
        return array_map(fn (string $password): array => [$password], [
            '12345678', 'abcdefgh', 'ABCDEFGH', 'Abcdefgh', 'Abcdefg1', 'abcdef1!', 'ABCDEFG1!',
            'Abcdefg!', 'Aa1!aaa', 'Aa1! aaa', ' Aa1!aaaa', 'Aa1!aaaa ',
            "Aa1!\taaaa", "Aa1!\naaaa", "Aa1!\u{200B}aaaa",
            'Aa1!'.str_repeat('á', 35),
        ]);
    }

    #[DataProvider('weakSignupPasswords')]
    public function test_weak_or_unsafe_password_is_rejected_by_the_api(string $password): void
    {
        $this->browser('POST', '/register', $this->payload([
            'password' => $password, 'password_confirmation' => $password,
        ]))->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertDatabaseCount('users', 0);
    }

    public static function validSignupPasswords(): array
    {
        return [['Aa1!aaaa'], ['ĐăngKý1!'], ['Aa1!'.str_repeat('á', 34)]];
    }

    #[DataProvider('validSignupPasswords')]
    public function test_strong_password_can_register_and_login(string $password): void
    {
        $this->browser('POST', '/register', $this->payload([
            'password' => $password, 'password_confirmation' => $password,
        ]))->assertCreated();
        $this->browser('POST', '/login', ['email' => 'uc1@example.test', 'password' => $password])->assertOk();
    }

    #[DataProvider('invalidPayloads')]
    public function test_invalid_fields_never_create_a_user(array $overrides, string $field): void
    {
        $this->browser('POST', '/register', $this->payload($overrides))->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_missing_fields_are_reported_together(): void
    {
        $this->browser('POST', '/register')->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password', 'password_confirmation']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_duplicate_email_returns_a_field_error_without_changing_the_existing_user(): void
    {
        $existing = $this->fixture(UserRole::HOST);
        $this->browser('POST', '/register', $this->payload(['email' => $existing->email]))
            ->assertUnprocessable()->assertJsonPath('errors.email.0', RegistrationService::DUPLICATE_EMAIL);
        $this->assertDatabaseCount('users', 1);
        $this->assertSame(UserRole::HOST, $existing->fresh()->role);
    }

    public static function maliciousRoles(): array
    {
        return [['ADMIN', 'BLOCKED'], ['HOST', 'INACTIVE']];
    }

    #[DataProvider('maliciousRoles')]
    public function test_protected_payload_fields_cannot_grant_privileges_or_populate_profile_data(string $role, string $status): void
    {
        $this->browser('POST', '/register', $this->payload([
            'role' => $role, 'status' => $status, 'id' => 99999, 'expected_role' => 'ADMIN',
            'email_verified_at' => '2000-01-01', 'remember_token' => 'injected',
            'created_at' => '2000-01-01', 'updated_at' => '2000-01-01',
            'phone' => 'injected', 'address' => 'injected', 'avatar' => 'injected',
            'gender' => 'MALE', 'date_of_birth' => '2000-01-01',
            'properties' => [['name' => 'Injected property']],
        ]))->assertCreated();
        $user = User::firstOrFail();
        $this->assertSame(UserRole::CUSTOMER, $user->role);
        $this->assertSame(UserStatus::ACTIVE, $user->status);
        $this->assertNotSame(99999, $user->id);
        foreach (['email_verified_at', 'remember_token', 'phone', 'address', 'avatar', 'gender', 'date_of_birth'] as $field) {
            $this->assertNull($user->$field);
        }
        $this->assertNotSame('2000-01-01', $user->created_at->toDateString());
        $this->assertDatabaseCount('properties', 0);
        $this->browser('GET', '/api/me')->assertUnauthorized();
    }

    public static function roles(): array
    {
        return [['CUSTOMER'], ['HOST'], ['ADMIN']];
    }

    #[DataProvider('roles')]
    public function test_a_real_authenticated_session_gets_json_conflict_without_identity_changes(string $role): void
    {
        $user = $this->fixture(UserRole::from($role));
        $this->browser('POST', '/login', ['email' => $user->email, 'password' => self::PASSWORD])->assertOk();
        $this->browser('POST', '/register', $this->payload())->assertConflict()
            ->assertExactJson([
                'message' => 'Bạn đang đăng nhập. Vui lòng đăng xuất trước khi đăng ký tài khoản mới.',
                'code' => 'ALREADY_AUTHENTICATED',
            ])->assertHeader('Content-Type', 'application/json');
        $this->assertDatabaseCount('users', 1);
        $this->browser('GET', '/api/me')->assertOk()->assertJsonPath('user.id', $user->id);
    }

    public function test_registration_does_not_replace_the_account_status_policy(): void
    {
        $user = $this->fixture();
        $this->browser('POST', '/login', ['email' => $user->email, 'password' => self::PASSWORD])->assertOk();
        $user->update(['status' => UserStatus::BLOCKED]);
        $this->browser('POST', '/register', $this->payload())->assertConflict();
        $this->browser('GET', '/api/me')->assertForbidden()->assertJsonPath('code', 'ACCOUNT_BLOCKED');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_validation_failures_count_toward_the_registration_limit_and_clock_recovery(): void
    {
        $this->freezeTime();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->browser('POST', '/register')->assertUnprocessable();
        }
        $this->browser('POST', '/register', $this->payload())->assertStatus(429)
            ->assertHeader('Retry-After', '60')->assertJsonPath('retry_after', 60);
        $this->assertDatabaseCount('users', 0);
        $this->travel(61)->seconds();
        $this->browser('POST', '/register', $this->payload())->assertCreated();
    }

    public function test_successful_registrations_also_count_and_other_ips_are_independent(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->browser('POST', '/register', $this->payload(['email' => "uc1-{$attempt}@example.test"]))->assertCreated();
        }
        $this->browser('POST', '/register', $this->payload())->assertStatus(429);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.50']);
        $this->browser('POST', '/register', $this->payload())->assertCreated();
        $this->assertDatabaseCount('users', 6);
    }

    public function test_fetching_csrf_cookies_does_not_consume_registration_attempts(): void
    {
        for ($attempt = 0; $attempt < 7; $attempt++) {
            $this->browser('GET', '/sanctum/csrf-cookie')->assertNoContent();
        }
        $this->browser('POST', '/register', $this->payload())->assertCreated();
    }

    public function test_register_neither_resets_nor_shares_the_login_counter(): void
    {
        $user = $this->fixture();
        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->browser('POST', '/login', ['email' => $user->email, 'password' => 'wrong'])->assertUnprocessable();
        }
        $this->browser('POST', '/register', $this->payload())->assertCreated();
        $this->browser('POST', '/login', ['email' => $user->email, 'password' => 'wrong'])->assertUnprocessable();
        $this->browser('POST', '/login', ['email' => $user->email, 'password' => self::PASSWORD])->assertStatus(429);
        $this->browser('POST', '/register', $this->payload(['email' => 'second@example.test']))->assertCreated();
    }

    public function test_login_does_not_reset_or_share_the_registration_counter(): void
    {
        $user = $this->fixture();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->browser('POST', '/register')->assertUnprocessable();
        }
        $this->browser('POST', '/login', ['email' => $user->email, 'password' => self::PASSWORD])->assertOk();
        $this->browser('POST', '/logout')->assertOk();
        $this->browser('POST', '/register', $this->payload())->assertStatus(429);
    }

    public function test_database_email_unique_violation_after_validation_is_mapped_precisely(): void
    {
        $existing = $this->fixture();
        try {
            $this->app->make(RegistrationService::class)->register($this->payload(['email' => $existing->email]));
            $this->fail('Expected a database email unique conflict.');
        } catch (ValidationException $exception) {
            $this->assertSame(['email' => [RegistrationService::DUPLICATE_EMAIL]], $exception->errors());
        }
        $this->assertDatabaseCount('users', 1);
    }

    public static function databaseErrors(): array
    {
        return [
            ['23000', 1062, "Duplicate entry 'fixture' for key 'users.users_email_unique'", true],
            ['23000', 1062, "Duplicate entry 'fixture' for key 'users_email_unique'", true],
            ['23000', 1062, "Duplicate entry 'fixture' for key 'PRIMARY'", false],
            ['23000', 19, 'UNIQUE constraint failed: users.id', false],
            ['HY000', 1, 'Fixture unrelated database failure', false],
        ];
    }

    #[DataProvider('databaseErrors')]
    public function test_only_known_email_constraints_become_duplicate_email_errors(string $state, int $code, string $detail, bool $emailConflict): void
    {
        $previous = new PDOException('Fixture database error');
        $previous->errorInfo = [$state, $code, $detail];
        $exception = $code === 1
            ? new QueryException('sqlite', 'fixture query', [], $previous)
            : new UniqueConstraintViolationException('sqlite', 'fixture query', [], $previous);
        User::creating(function () use ($exception): void { throw $exception; });
        $this->withoutExceptionHandling();
        try {
            $this->browser('POST', '/register', $this->payload());
            $this->fail('Expected the fixture database error.');
        } catch (ValidationException $caught) {
            $this->assertTrue($emailConflict);
            $this->assertSame(['email' => [RegistrationService::DUPLICATE_EMAIL]], $caught->errors());
        } catch (QueryException $caught) {
            $this->assertFalse($emailConflict);
            $this->assertSame($exception, $caught);
        } finally {
            User::flushEventListeners();
        }
        $this->assertDatabaseCount('users', 0);
    }
}
