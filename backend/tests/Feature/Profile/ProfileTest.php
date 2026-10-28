<?php

namespace Tests\Feature\Profile;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\Profile\ProfileService;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Services\Profile\AvatarService;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private ?string $sessionCookie = null;
    private const PASSWORD = 'Profile-Fixture1!';

    private function fixture(string $role = 'CUSTOMER'): User
    {
        $user = User::create([
            'name' => 'Original fixture', 'email' => 'profile-'.User::count().'@example.test',
            'password' => self::PASSWORD, 'role' => UserRole::from($role), 'status' => UserStatus::ACTIVE,
            'phone' => '0901234567', 'address' => 'Địa chỉ fixture', 'avatar' => '/fixture/avatar.png',
        ]);
        $user->forceFill(['email_verified_at' => now(), 'remember_token' => 'fixture-remember'])->save();
        return $user->refresh();
    }

    private function login(User $user): void
    {
        $this->browser('POST', '/login', ['email' => $user->email, 'password' => self::PASSWORD])->assertOk();
    }

    public function test_guests_cannot_read_update_or_change_password(): void
    {
        foreach ([['GET', '/api/profile'], ['PATCH', '/api/profile'], ['PATCH', '/api/profile/password'], ['GET', '/api/profile/avatar'], ['POST', '/api/profile/avatar']] as [$method, $uri]) {
            $this->browser($method, $uri)->assertUnauthorized();
        }
    }

    public static function roles(): array { return [['CUSTOMER'], ['HOST'], ['ADMIN']]; }

    #[DataProvider('roles')]
    public function test_each_active_role_can_read_and_update_only_its_own_profile(string $role): void
    {
        $user = $this->fixture($role);
        $other = $this->fixture();
        $this->login($user);
        $response = $this->browser('GET', '/api/profile')->assertOk()->assertJsonPath('user.id', $user->id);
        $keys = array_keys($response->json('user'));
        sort($keys);
        $expected = ['id', 'name', 'email', 'phone', 'address', 'avatar', 'role', 'status', 'email_verified_at', 'created_at', 'updated_at', 'gender', 'date_of_birth'];
        sort($expected);
        $this->assertSame($expected, $keys);
        $this->browser('PATCH', '/api/profile', ['name' => ' Updated name ', 'phone' => '0987654321'])
            ->assertOk()->assertJsonPath('user.name', 'Updated name')->assertJsonPath('user.phone', '0987654321')
            ->assertJsonPath('user.role', $role)->assertJsonPath('message', 'Cập nhật hồ sơ thành công.');
        $this->browser('GET', '/api/me')->assertOk()->assertJsonPath('user.name', 'Updated name');
        $this->assertSame('Original fixture', $other->fresh()->name);
        $this->assertSame($user->email, $user->fresh()->email);
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertSame('/fixture/avatar.png', $user->fresh()->avatar);
    }

    public static function forbiddenFields(): array
    {
        return array_map(fn ($field) => [$field], ['id', 'user_id', 'customer_id', 'host_id', 'role', 'status', 'password',
            'email_verified_at', 'remember_token', 'created_at', 'updated_at', 'properties', 'bookings', 'avatar', 'email', 'unexpected']);
    }

    #[DataProvider('forbiddenFields')]
    public function test_unapproved_fields_are_rejected_even_if_null(string $field): void
    {
        $user = $this->fixture();
        $this->login($user);
        $before = $user->getAttributes();
        $this->browser('PATCH', '/api/profile', ['name' => 'Must not save', $field => null])
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertSame($before, $user->fresh()->getAttributes());
    }

    public function test_client_id_never_selects_another_user(): void
    {
        $actor = $this->fixture(); $other = $this->fixture('HOST');
        $this->login($actor);
        $this->browser('PATCH', '/api/profile', ['user_id' => $other->id, 'name' => 'Injected'])->assertUnprocessable();
        $this->browser('PATCH', '/api/profile/'.$other->id, ['name' => 'Injected'])->assertNotFound();
        $this->assertSame('Original fixture', $other->fresh()->name);
        $this->assertSame('Original fixture', $actor->fresh()->name);
    }

    public static function invalidFields(): array
    {
        return [
            ['gender', 'INVALID'], ['gender', 1], ['gender', ['MALE']],
            ['date_of_birth', '2001-02-29'], ['date_of_birth', '2020-13-01'], ['date_of_birth', '2020-04-31'],
            ['date_of_birth', '01/01/2000'], ['date_of_birth', '1899-12-31'], ['date_of_birth', '9999-01-01'],
            ['date_of_birth', ['2000-01-01']], ['date_of_birth', 20000101],
            ['name', null], ['name', ' '], ['name', ['invalid']], ['name', str_repeat('a', 256)],
            ['email', null], ['email', ' '], ['email', ['invalid']], ['email', 'person@localhost'],
            ['email', 'person..name@example.com'], ['email', 'person@example.123'], ['email', str_repeat('a', 65).'@example.com'],
            ['phone', ['invalid']], ['phone', '123'], ['phone', '0901abc123'], ['phone', '0901234567 ext 1'],
            ['phone', '090123456'], ['phone', '09012345678'], ['phone', '+84901234567'],
            ['phone', '090 1234567'], ['phone', '090.123.4567'], ['phone', '090-123-4567'],
            ['phone', '０９０１２３４５６７'], ['phone', 901234567],
            ['email', 'not-an-email'], ['email', 'person@@example.com'], ['email', 'person name@example.com'],
            ['email', 'person@example..com'], ['email', '.person@example.com'], ['email', 'person.@example.com'],
            ['email', 'person@example.com.'], ['email', 'person@-example.com'], ['email', 'person@exam_ple.com'],
            ['email', 'person@example.c'], ['email', 'person@example.com,other@example.com'],
            ['phone', '0901234567('], ['phone', '090)1234(567'], ['phone', '+1234567890123456'], ['phone', str_repeat('1', 21)],
            ['address', ['invalid']], ['address', str_repeat('a', 501)],
        ];
    }

    #[DataProvider('invalidFields')]
    public function test_invalid_patch_is_atomic(string $field, mixed $value): void
    {
        $user = $this->fixture(); $this->login($user);
        $before = $user->getAttributes();
        $this->browser('PATCH', '/api/profile', ['address' => 'Must not save', $field => $value])
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertSame($before, $user->fresh()->getAttributes());
    }

    public function test_empty_patch_is_rejected_and_nullable_contacts_can_be_cleared(): void
    {
        $user = $this->fixture(); $this->login($user);
        $this->browser('PATCH', '/api/profile')->assertUnprocessable()->assertJsonValidationErrors('profile');
        $this->browser('PATCH', '/api/profile', ['phone' => ' ', 'address' => null])->assertOk()
            ->assertJsonPath('user.phone', null)->assertJsonPath('user.address', null);
        $this->assertSame($user->name, $user->fresh()->name);
    }

    public function test_exactly_ten_ascii_digits_are_saved_as_a_string_with_leading_zero(): void
    {
        $user = $this->fixture(); $this->login($user);
        $this->browser('PATCH', '/api/profile', ['phone' => ' 0987654321 '])
            ->assertOk()->assertJsonPath('user.phone', '0987654321');
        $this->assertSame('0987654321', $user->fresh()->phone);
    }

    public function test_gender_and_birth_date_can_be_saved_read_and_cleared_without_changing_email(): void
    {
        $user = $this->fixture(); $this->login($user);
        foreach (['MALE', 'FEMALE', 'OTHER'] as $gender) {
            $this->browser('PATCH', '/api/profile', ['gender' => $gender, 'date_of_birth' => '2000-02-29'])
                ->assertOk()->assertJsonPath('user.gender', $gender)->assertJsonPath('user.date_of_birth', '2000-02-29')
                ->assertJsonPath('user.email', $user->email);
        }
        $this->browser('GET', '/api/me')->assertOk()->assertJsonPath('user.date_of_birth', '2000-02-29');
        $this->browser('PATCH', '/api/profile', ['gender' => '', 'date_of_birth' => null])->assertOk()
            ->assertJsonPath('user.gender', null)->assertJsonPath('user.date_of_birth', null);
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_birth_date_boundary_uses_vietnam_today_and_forbids_tomorrow(): void
    {
        $user = $this->fixture(); $this->login($user);
        $this->travelTo(\Carbon\Carbon::parse('2026-10-28 18:00:00', 'UTC'));
        $this->browser('PATCH', '/api/profile', ['date_of_birth' => '2026-10-29'])->assertOk();
        $this->browser('PATCH', '/api/profile', ['date_of_birth' => '2026-10-30'])
            ->assertUnprocessable()->assertJsonValidationErrors('date_of_birth');
    }

    private function png(string $name = 'avatar.png', int $width = 1): UploadedFile
    {
        // Generate a tiny valid fixture without requiring the optional GD extension.
        $chunk = fn ($type, $data) => pack('N', strlen($data)).$type.$data.pack('H*', hash('crc32b', $type.$data));
        $bytes = "\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNCCCCC', $width, 1, 8, 6, 0, 0, 0))
            .$chunk('IDAT', gzcompress(str_repeat("\0", 1 + 4 * $width))).$chunk('IEND', '');
        return UploadedFile::fake()->createWithContent($name, $bytes);
    }

    #[DataProvider('roles')]
    public function test_each_role_can_upload_and_read_own_avatar_with_an_opaque_url(string $role): void
    {
        Storage::fake('local');
        $user = $this->fixture($role); $this->login($user);
        $response = $this->browser('POST', '/api/profile/avatar', ['avatar' => $this->png('my-local-file.png')])
            ->assertOk()->assertJsonPath('user.id', $user->id);
        $path = $user->fresh()->avatar;
        Storage::disk('local')->assertExists($path);
        $this->assertStringStartsWith('profile-avatars/'.$user->id.'/', $path);
        $this->assertStringNotContainsString('my-local-file', $path);
        $this->assertStringContainsString('/api/profile/avatar?v=', $response->json('user.avatar'));
        $this->assertStringNotContainsString('profile-avatars', $response->json('user.avatar'));
        $this->browser('GET', '/api/profile/avatar')->assertOk()->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->browser('GET', '/api/me')->assertOk()->assertJsonPath('user.avatar', $response->json('user.avatar'));
    }

    public function test_replacing_avatar_deletes_only_the_previous_managed_file_owned_by_actor(): void
    {
        Storage::fake('local');
        $user = $this->fixture(); $other = $this->fixture(); $this->login($user);
        $otherPath = 'profile-avatars/'.$other->id.'/'.Str::uuid().'.png';
        Storage::disk('local')->put($otherPath, 'Shared fixture');
        $this->browser('POST', '/api/profile/avatar', ['avatar' => $this->png()])->assertOk();
        $oldPath = $user->fresh()->avatar;
        $this->browser('POST', '/api/profile/avatar', ['avatar' => $this->png()])->assertOk();
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($user->fresh()->avatar);
        Storage::disk('local')->assertExists($otherPath);
        $this->assertSame('/fixture/avatar.png', $other->fresh()->avatar);
    }

    public function test_file_cleanup_does_not_delete_legacy_or_another_users_path(): void
    {
        Storage::fake('local');
        $user = $this->fixture(); $other = $this->fixture();
        $otherPath = 'profile-avatars/'.$other->id.'/'.Str::uuid().'.png';
        Storage::disk('local')->put($otherPath, 'Legacy shared fixture');
        $user->update(['avatar' => $otherPath]); $this->login($user);
        $this->browser('POST', '/api/profile/avatar', ['avatar' => $this->png()])->assertOk();
        Storage::disk('local')->assertExists($otherPath);
    }

    public function test_invalid_avatar_files_and_identity_payload_leave_profile_and_storage_unchanged(): void
    {
        Storage::fake('local');
        $user = $this->fixture(); $other = $this->fixture(); $this->login($user);
        $invalid = [
            ['avatar' => UploadedFile::fake()->createWithContent('fake.png', '<?php echo 1;')],
            ['avatar' => UploadedFile::fake()->createWithContent('avatar.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>')],
            ['avatar' => $this->png()->size(2049)], ['avatar' => $this->png('wide.png', 4097)],
            ['avatar' => 'https://example.com/photo.png'],
            ['avatar' => $this->png(), 'user_id' => $other->id],
            ['avatar' => $this->png(), 'email' => 'injected@example.com'],
        ];
        foreach ($invalid as $data) $this->browser('POST', '/api/profile/avatar', $data)->assertUnprocessable();
        $this->assertSame('/fixture/avatar.png', $user->fresh()->avatar);
        $this->assertSame($user->email, $user->fresh()->email);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_avatar_database_failure_removes_new_file_and_keeps_old_file_and_row(): void
    {
        Storage::fake('local');
        $user = $this->fixture();
        $this->app->make(AvatarService::class)->upload($user, $this->png());
        $oldPath = $user->fresh()->avatar;
        User::updated(fn () => throw new RuntimeException('Fixture avatar SQL failure'));
        try {
            $this->app->make(AvatarService::class)->upload($user, $this->png());
            $this->fail('Expected fixture failure.');
        } catch (RuntimeException $error) {
            $this->assertSame('Fixture avatar SQL failure', $error->getMessage());
        } finally { User::flushEventListeners(); }
        $this->assertSame($oldPath, $user->fresh()->avatar);
        $this->assertSame([$oldPath], Storage::disk('local')->allFiles());
    }

    public function test_storage_write_failure_does_not_change_the_database_avatar(): void
    {
        $user = $this->fixture();
        $file = \Mockery::mock(UploadedFile::class);
        $file->shouldReceive('extension')->once()->andReturn('png');
        $file->shouldReceive('storeAs')->once()->andReturn(false);
        try {
            $this->app->make(AvatarService::class)->upload($user, $file);
            $this->fail('Expected storage write failure.');
        } catch (RuntimeException $error) {
            $this->assertSame('Avatar storage failed.', $error->getMessage());
        }
        $this->assertSame('/fixture/avatar.png', $user->fresh()->avatar);
    }

    public function test_avatar_read_does_not_accept_a_client_path_or_return_another_users_file(): void
    {
        Storage::fake('local');
        $user = $this->fixture(); $other = $this->fixture(); $this->login($user);
        $this->app->make(AvatarService::class)->upload($other, $this->png());
        $this->browser('GET', '/api/profile/avatar?path='.$other->fresh()->avatar)->assertNotFound();
        $this->browser('GET', '/api/profile/avatar/'.$other->id)->assertNotFound();
    }

    public function test_email_is_read_only_even_when_the_same_value_is_sent(): void
    {
        $user = $this->fixture(); $this->login($user);
        $before = $user->getAttributes();
        foreach ([$user->email, 'new@example.com', null] as $email) {
            $this->browser('PATCH', '/api/profile', ['email' => $email, 'name' => 'Must not save'])
                ->assertUnprocessable()->assertJsonValidationErrors('email');
            $this->assertSame($before, $user->fresh()->getAttributes());
        }
        $this->browser('GET', '/api/me')->assertOk()->assertJsonPath('user.email', $user->email);
    }

    public function test_service_cannot_change_email_verification_or_roles_from_internal_payload(): void
    {
        $user = $this->fixture(); $other = $this->fixture();
        $updated = $this->app->make(ProfileService::class)->update($user, [
            'email' => $other->email, 'role' => 'ADMIN', 'name' => 'Valid name', 'email_verified_at' => null,
        ]);
        $this->assertSame($user->email, $updated->email);
        $this->assertSame(UserRole::CUSTOMER, $updated->role);
        $this->assertNotNull($updated->email_verified_at);
        $this->assertSame('Valid name', $updated->name);
    }

    public function test_database_error_after_update_rolls_back_the_whole_transaction(): void
    {
        $user = $this->fixture(); $this->login($user);
        $before = $user->getAttributes();
        User::updated(fn () => throw new RuntimeException('Fixture failure after SQL update'));
        $this->withoutExceptionHandling();
        try {
            $this->browser('PATCH', '/api/profile', ['name' => 'Must rollback', 'address' => 'Must rollback too']);
            $this->fail('Expected fixture exception.');
        } catch (RuntimeException $error) {
            $this->assertSame('Fixture failure after SQL update', $error->getMessage());
        } finally { User::flushEventListeners(); }
        $this->assertSame($before, $user->fresh()->getAttributes());
    }

    public static function dbErrors(): array
    {
        return [[1062, "Duplicate entry 'fixture' for key 'users_email_unique'", true],
            [1062, "Duplicate entry 'fixture' for key 'PRIMARY'", false], [19, 'UNIQUE constraint failed: users.id', false]];
    }

    #[DataProvider('dbErrors')]
    public function test_profile_database_constraints_are_not_mislabeled_as_email_validation(int $code, string $detail, bool $email): void
    {
        $user = $this->fixture(); $before = $user->getAttributes();
        $previous = new PDOException('Fixture constraint'); $previous->errorInfo = ['23000', $code, $detail];
        $failure = new UniqueConstraintViolationException('sqlite', 'fixture', [], $previous);
        User::updating(fn () => throw $failure);
        try {
            $this->app->make(ProfileService::class)->update($user, ['name' => 'Must not save']);
            $this->fail('Expected constraint exception.');
        } catch (QueryException $error) {
            $this->assertSame($failure, $error);
        } finally { User::flushEventListeners(); }
        $this->assertSame($before, $user->fresh()->getAttributes());
    }

    public static function deniedStatuses(): array { return [['BLOCKED', 'ACCOUNT_BLOCKED'], ['INACTIVE', 'ACCOUNT_INACTIVE']]; }

    #[DataProvider('deniedStatuses')]
    public function test_status_change_after_login_blocks_every_new_endpoint(string $status, string $code): void
    {
        $user = $this->fixture();
        foreach ([['GET', '/api/profile'], ['PATCH', '/api/profile'], ['PATCH', '/api/profile/password'], ['GET', '/api/profile/avatar'], ['POST', '/api/profile/avatar']] as [$method, $uri]) {
            $user->update(['status' => UserStatus::ACTIVE]);
            $this->login($user);
            $user->update(['status' => UserStatus::from($status)]);
            $this->browser($method, $uri)->assertForbidden()->assertJsonPath('code', $code);
        }
    }

    #[DataProvider('roles')]
    public function test_password_change_keeps_current_session_and_replaces_only_own_password(string $role): void
    {
        $user = $this->fixture($role); $other = $this->fixture(); $this->login($user);
        $this->browser('PATCH', '/api/profile/password', ['current_password' => self::PASSWORD,
            'password' => 'New-Password2!', 'password_confirmation' => 'New-Password2!'])
            ->assertOk()->assertExactJson(['message' => 'Đổi mật khẩu thành công.']);
        $this->assertTrue(Hash::check('New-Password2!', $user->fresh()->password));
        $this->assertTrue(Hash::check(self::PASSWORD, $other->fresh()->password));
        $this->browser('GET', '/api/me')->assertOk()->assertJsonPath('user.id', $user->id);
        $this->browser('POST', '/logout')->assertOk();
        $this->browser('POST', '/login', ['email' => $user->email, 'password' => self::PASSWORD])->assertUnprocessable();
        $this->browser('POST', '/login', ['email' => $user->email, 'password' => 'New-Password2!'])->assertOk();
    }

    public static function invalidPasswords(): array
    {
        return [
            [['current_password' => 'wrong'], 'current_password'], [['current_password' => null], 'current_password'],
            [['password' => '12345678', 'password_confirmation' => '12345678'], 'password'],
            [['password_confirmation' => 'mismatch'], 'password'],
            [['password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD], 'password'],
            [['user_id' => 5], 'user_id'], [['role' => 'ADMIN'], 'role'],
            [['password' => 'Aa1!'.str_repeat('á', 35), 'password_confirmation' => 'Aa1!'.str_repeat('á', 35)], 'password'],
        ];
    }

    #[DataProvider('invalidPasswords')]
    public function test_bad_password_change_preserves_the_existing_hash(array $overrides, string $field): void
    {
        $user = $this->fixture(); $this->login($user); $hash = $user->password;
        $this->browser('PATCH', '/api/profile/password', array_replace(['current_password' => self::PASSWORD,
            'password' => 'New-Password2!', 'password_confirmation' => 'New-Password2!'], $overrides))
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertSame($hash, $user->fresh()->password);
    }

    public function test_password_attempts_are_throttled_and_profile_reads_are_not(): void
    {
        $user = $this->fixture(); $this->login($user);
        for ($i = 0; $i < 5; ++$i) $this->browser('PATCH', '/api/profile/password')->assertUnprocessable();
        $this->browser('PATCH', '/api/profile/password')->assertStatus(429)->assertHeader('Retry-After');
        $this->browser('GET', '/api/profile')->assertOk();
    }

    public function test_password_database_failure_rolls_back_and_the_old_password_still_works(): void
    {
        $user = $this->fixture(); $this->login($user); $hash = $user->password;
        User::updated(fn () => throw new RuntimeException('Fixture password write failure'));
        try {
            $this->app->make(ProfileService::class)->changePassword($user, [
                'current_password' => self::PASSWORD, 'password' => 'New-Password2!',
            ]);
            $this->fail('Expected fixture exception.');
        } catch (RuntimeException $error) {
            $this->assertSame('Fixture password write failure', $error->getMessage());
        } finally { User::flushEventListeners(); }
        $this->assertSame($hash, $user->fresh()->password);
        $this->browser('GET', '/api/me')->assertOk();
    }

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

}
