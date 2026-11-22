<?php

namespace Tests\Feature\UserManagement;

use App\DTOs\UserManagement\Requests\UpdateAccountStatusRequest;
use App\DTOs\UserManagement\Responses\AccountStatusResponse;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\UserManagement\AccountStatusConflictException;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Repositories\UserManagement\AccountStatusRepository;
use App\Services\UserManagement\AccountStatusService;
use App\Validators\UserManagement\AccountStatusValidator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class AccountStatusTest extends TestCase
{
    // No wrapping test transaction: afterCommit must run at a real outer commit.
    use DatabaseMigrations;

    public function createApplication()
    {
        foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:',
            'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'] as $key => $expected) {
            if (($_ENV[$key] ?? getenv($key)) !== $expected) {
                throw new RuntimeException('UC42 requires an isolated SQLite in-memory environment before boot.');
            }
        }
        if (is_file(dirname(__DIR__, 3).'/bootstrap/cache/config.php')) {
            throw new RuntimeException('UC42 tests cannot run with cached configuration.');
        }
        $app = parent::createApplication();
        if ($app['config']->get('database.default') !== 'sqlite'
            || $app['config']->get('database.connections.sqlite.database') !== ':memory:'
            || !empty($app['config']->get('database.connections.sqlite.url'))) {
            throw new RuntimeException('UC42 database isolation must hold before migrations.');
        }

        return $app;
    }

    protected function beforeRefreshingDatabase()
    {
        $this->assertSafeTestDatabase();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 22)->setTime(3, 0));
        Log::spy();
    }

    private function command(User $target, string $status = 'BLOCKED', string $expected = 'ACTIVE'): UpdateAccountStatusRequest
    {
        return new UpdateAccountStatusRequest($target->id, UserStatus::from($status), UserStatus::from($expected));
    }

    private function update(User $actor, UpdateAccountStatusRequest $input): AccountStatusResponse
    {
        return $this->app->make(AccountStatusService::class)->update($actor, $input);
    }

    private function snapshot(string $table = 'users'): string
    {
        return DB::table($table)->orderBy('id')->get()->toJson();
    }

    /** @param class-string<Throwable> $class */
    private function rejected(string $class, callable $operation): Throwable
    {
        try {
            $operation();
        } catch (Throwable $error) {
            $this->assertInstanceOf($class, $error);

            return $error;
        }
        $this->fail('Expected '.$class);
    }

    public static function transitions(): array
    {
        return [
            ['CUSTOMER', 'ACTIVE', 'BLOCKED'], ['HOST', 'ACTIVE', 'BLOCKED'],
            ['CUSTOMER', 'BLOCKED', 'ACTIVE'], ['HOST', 'BLOCKED', 'ACTIVE'],
        ];
    }

    #[DataProvider('transitions')]
    public function test_admin_changes_only_target_status_and_timestamp(string $role, string $from, string $to): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create(['role' => UserRole::from($role), 'status' => UserStatus::from($from),
            'updated_at' => now()->subDay()]);
        $other = User::factory()->create();
        $before = $target->fresh()->getRawOriginal();
        $adminBefore = $admin->fresh()->getRawOriginal();
        $otherBefore = $other->fresh()->getRawOriginal();

        $response = $this->update($admin, $this->command($target, $to, $from));

        $after = $target->fresh()->getRawOriginal();
        $expected = array_replace($before, ['status' => $to, 'updated_at' => now()->format('Y-m-d H:i:s')]);
        $this->assertSame($expected, $after);
        $this->assertSame($adminBefore, $admin->fresh()->getRawOriginal());
        $this->assertSame($otherBefore, $other->fresh()->getRawOriginal());
        $this->assertSame(['id' => $target->id, 'status' => $to,
            'updated_at' => $target->fresh()->updated_at->toISOString(), 'changed' => true],
            json_decode(json_encode($response, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR));
        Log::shouldHaveReceived('info')->once()->with('Account status updated.', [
            'actor_id' => $admin->id, 'user_id' => $target->id, 'from_status' => $from, 'to_status' => $to,
        ]);
        $this->assertSame(0, DB::transactionLevel());
    }

    public static function noops(): array
    {
        return [['CUSTOMER', 'ACTIVE'], ['HOST', 'ACTIVE'], ['CUSTOMER', 'BLOCKED'], ['HOST', 'BLOCKED']];
    }

    #[DataProvider('noops')]
    public function test_matching_noop_has_no_write_timestamp_change_or_success_log(string $role, string $status): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create(['role' => UserRole::from($role), 'status' => UserStatus::from($status),
            'updated_at' => now()->subDay()]);
        $before = $this->snapshot();
        DB::enableQueryLog();

        $response = $this->update($admin, $this->command($target, $status, $status));

        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertFalse($response->changed);
        $this->assertSame($status, $response->status);
        $this->assertSame($target->updated_at->toISOString(), $response->updated_at);
        $this->assertSame($before, $this->snapshot());
        foreach ($queries as $query) {
            $this->assertStringStartsWith('select ', strtolower($query['query']));
        }
        Log::shouldNotHaveReceived('info');
    }

    public static function deniedActors(): array
    {
        return [['CUSTOMER', 'ACTIVE'], ['HOST', 'ACTIVE'], ['ADMIN', 'BLOCKED'], ['ADMIN', 'INACTIVE']];
    }

    #[DataProvider('deniedActors')]
    public function test_direct_service_denies_wrong_role_or_inactive_actor(string $role, string $status): void
    {
        $actor = User::factory()->create(['role' => UserRole::from($role), 'status' => UserStatus::from($status)]);
        $target = User::factory()->create();
        $before = $this->snapshot();

        $this->rejected(AuthorizationException::class, fn () => $this->update($actor, $this->command($target)));

        $this->assertSame($before, $this->snapshot());
        Log::shouldNotHaveReceived('info');
    }

    #[DataProvider('deniedActors')]
    public function test_authority_is_reread_even_when_actor_model_still_says_active_admin(string $role, string $status): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();
        User::query()->whereKey($admin->id)->update(['role' => $role, 'status' => $status]);
        $this->assertSame(UserRole::ADMIN, $admin->role);
        $this->assertSame(UserStatus::ACTIVE, $admin->status);
        $before = $this->snapshot();

        $this->rejected(AuthorizationException::class, fn () => $this->update($admin, $this->command($target)));

        $this->assertSame($before, $this->snapshot());
        Log::shouldNotHaveReceived('info');
    }

    public function test_cached_attributes_do_not_override_persisted_actor_authority(): void
    {
        $admin = User::factory()->admin()->create();
        $admin->role = UserRole::HOST;
        $admin->status = UserStatus::BLOCKED;
        $target = User::factory()->create();

        $this->assertTrue($this->update($admin, $this->command($target))->changed);
        $this->assertSame(UserRole::ADMIN, $admin->fresh()->role);
        $this->assertSame(UserStatus::ACTIVE, $admin->fresh()->status);
    }

    public function test_deleted_actor_is_denied_without_writing_target(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();
        User::query()->whereKey($admin->id)->delete();
        $before = $this->snapshot();

        $this->rejected(AuthorizationException::class, fn () => $this->update($admin, $this->command($target)));

        $this->assertSame($before, $this->snapshot());
        Log::shouldNotHaveReceived('info');
    }

    public function test_unsaved_actor_cannot_borrow_an_existing_admin_id(): void
    {
        $admin = User::factory()->admin()->create();
        $unsaved = User::factory()->admin()->make(['id' => $admin->id]);
        $target = User::factory()->create();
        $before = $this->snapshot();

        $this->rejected(AuthorizationException::class, fn () => $this->update($unsaved, $this->command($target)));

        $this->assertSame($before, $this->snapshot());
        Log::shouldNotHaveReceived('info');
    }

    public function test_admin_self_and_missing_targets_share_the_same_not_found_result(): void
    {
        $actor = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();
        $missingId = $otherAdmin->id + 100;
        $before = $this->snapshot();
        $messages = [];
        foreach ([$actor->id, $otherAdmin->id, $missingId] as $id) {
            $error = $this->rejected(ModelNotFoundException::class, fn () => $this->update($actor,
                new UpdateAccountStatusRequest($id, UserStatus::BLOCKED, UserStatus::ACTIVE)));
            $messages[] = $error->getMessage();
            $this->assertSame(User::class, $error->getModel());
            $this->assertSame([], $error->getIds());
            $this->assertSame($before, $this->snapshot());
        }
        $this->assertCount(1, array_unique($messages));
        Log::shouldNotHaveReceived('info');
    }

    #[DataProvider('noops')]
    public function test_inactive_target_is_never_activated_or_blocked(string $role, string $desired): void
    {
        $actor = User::factory()->admin()->create();
        $target = User::factory()->create(['role' => UserRole::from($role), 'status' => UserStatus::INACTIVE]);
        $before = $this->snapshot();

        $this->rejected(AccountStatusConflictException::class,
            fn () => $this->update($actor, $this->command($target, $desired)));

        $this->assertSame($before, $this->snapshot());
        Log::shouldNotHaveReceived('info');
    }

    #[DataProvider('noops')]
    public function test_expected_status_conflict_is_checked_before_noop(string $role, string $current): void
    {
        $actor = User::factory()->admin()->create();
        $target = User::factory()->create(['role' => UserRole::from($role), 'status' => UserStatus::from($current)]);
        $expected = $current === 'ACTIVE' ? 'BLOCKED' : 'ACTIVE';
        $before = $this->snapshot();

        $this->rejected(AccountStatusConflictException::class,
            fn () => $this->update($actor, $this->command($target, $current, $expected)));

        $this->assertSame($before, $this->snapshot());
        Log::shouldNotHaveReceived('info');
    }

    public function test_success_log_waits_for_real_outer_commit_and_contains_original_facts(): void
    {
        $actor = User::factory()->admin()->create();
        $target = User::factory()->create();
        DB::beginTransaction();
        try {
            $response = $this->update($actor, $this->command($target));
            $this->assertTrue($response->changed);
            $this->assertSame(1, DB::transactionLevel());
            $this->assertSame(UserStatus::BLOCKED, $target->fresh()->status);
            Log::shouldNotHaveReceived('info');
            // Later in-memory changes cannot alter the committed audit facts.
            $target->status = UserStatus::INACTIVE;
            $actor->id = 999;
            DB::commit();
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }
        Log::shouldHaveReceived('info')->once()->with('Account status updated.', [
            'actor_id' => $actor->getRawOriginal('id'), 'user_id' => $target->id,
            'from_status' => 'ACTIVE', 'to_status' => 'BLOCKED',
        ]);
    }

    public function test_outer_rollback_restores_status_and_discards_success_log_callback(): void
    {
        $actor = User::factory()->admin()->create();
        $target = User::factory()->create();
        $before = $this->snapshot();
        DB::beginTransaction();
        try {
            $this->assertTrue($this->update($actor, $this->command($target))->changed);
            Log::shouldNotHaveReceived('info');
        } finally {
            DB::rollBack();
        }
        $this->assertSame($before, $this->snapshot());
        // A later unrelated commit must not release the rolled-back callback.
        DB::transaction(static fn () => null);
        Log::shouldNotHaveReceived('info');
    }

    public function test_failure_after_database_write_rolls_back_and_preserves_exception_identity(): void
    {
        $actor = User::factory()->admin()->create();
        $target = User::factory()->create();
        $before = $this->snapshot();
        $realRepository = new AccountStatusRepository;
        $failure = new RuntimeException('Persistence fixture failure.');
        $this->partialMock(AccountStatusRepository::class, function ($mock) use ($realRepository, $failure): void {
            $mock->shouldReceive('updateStatus')->once()->andReturnUsing(
                static function (User $target, UserStatus $status) use ($realRepository, $failure): never {
                    $realRepository->updateStatus($target, $status);
                    throw $failure;
                },
            );
        });

        $error = $this->rejected(RuntimeException::class, fn () => $this->update($actor, $this->command($target)));

        $this->assertSame($failure, $error);
        $this->assertSame($before, $this->snapshot());
        $this->assertSame(0, DB::transactionLevel());
        Log::shouldNotHaveReceived('info');
    }

    public function test_lock_lookups_follow_ascending_ids_when_target_precedes_actor(): void
    {
        $target = User::factory()->create();
        $actor = User::factory()->admin()->create();
        DB::enableQueryLog();

        $this->update($actor, $this->command($target));

        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertStringStartsWith('select ', $queries[0]['query']);
        $this->assertStringStartsWith('select ', $queries[1]['query']);
        $this->assertSame([$target->id], $queries[0]['bindings']);
        $this->assertSame([$actor->id], $queries[1]['bindings']);
        // This verifies query order only; SQLite does not exercise FOR UPDATE locks.
    }

    public function test_noop_preserves_nullable_legacy_timestamp(): void
    {
        $actor = User::factory()->admin()->create();
        $target = User::factory()->create();
        DB::table('users')->where('id', $target->id)->update(['updated_at' => null]);

        $response = $this->update($actor, $this->command($target, 'ACTIVE'));

        $this->assertNull($response->updated_at);
        $this->assertFalse($response->changed);
        $this->assertNull($target->fresh()->updated_at);
        Log::shouldNotHaveReceived('info');
    }

    public function test_locking_customer_and_host_does_not_change_related_business_snapshots(): void
    {
        $actor = User::factory()->admin()->create();
        $host = User::factory()->host()->create();
        $customer = User::factory()->customer()->create();
        $property = Property::factory()->create(['host_id' => $host->id]);
        $roomType = RoomType::factory()->create(['property_id' => $property->id]);
        $room = Room::factory()->create(['property_id' => $property->id, 'room_type_id' => $roomType->id]);
        $booking = Booking::factory()->confirmed()->create(['customer_id' => $customer->id, 'room_id' => $room->id]);
        $invoice = Invoice::create(['invoice_code' => 'UC42-SNAPSHOT', 'booking_id' => $booking->id,
            'customer_name' => 'Fixture', 'customer_email' => 'uc42@example.test', 'customer_phone' => '0900000000',
            'property_name' => 'Fixture', 'room_name' => 'Fixture', 'subtotal' => '100.25',
            'discount_amount' => '0.00', 'extra_fee' => '0.00', 'total_amount' => '100.25', 'payment_status' => 'UNPAID']);
        $invoice->items()->create(['item_type' => 'ROOM', 'item_name' => 'Fixture', 'quantity' => '1.00',
            'unit_price' => '100.25', 'amount' => '100.25']);
        $tables = ['properties', 'room_types', 'rooms', 'bookings', 'invoices', 'invoice_items'];
        $before = [];
        foreach ($tables as $table) {
            $before[$table] = $this->snapshot($table);
        }

        foreach ([$host, $customer] as $target) {
            $this->assertTrue($this->update($actor, $this->command($target))->changed);
            $this->assertTrue($this->update($actor, $this->command($target, 'ACTIVE', 'BLOCKED'))->changed);
            foreach ($tables as $table) {
                $this->assertSame($before[$table], $this->snapshot($table), $table);
            }
        }
    }

    public function test_raw_validator_returns_typed_command_without_database_queries(): void
    {
        DB::enableQueryLog();
        $validator = $this->app->make(AccountStatusValidator::class);
        $command = $validator->update('12', ['status' => 'BLOCKED', 'expected_status' => 'ACTIVE']);
        $maxId = $validator->update((string) PHP_INT_MAX, ['status' => 'ACTIVE', 'expected_status' => 'BLOCKED']);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame(12, $command->userId);
        $this->assertSame(UserStatus::BLOCKED, $command->status);
        $this->assertSame(UserStatus::ACTIVE, $command->expectedStatus);
        $this->assertSame(PHP_INT_MAX, $maxId->userId);
        $this->assertSame([], $queries);
    }

    public static function invalidIds(): array
    {
        return array_map(static fn ($id) => [$id], ['', '0', '-1', '+1', '01', '1.0', '1e2', ' 1', '1 ', 'abc', (string) PHP_INT_MAX.'0']);
    }

    #[DataProvider('invalidIds')]
    public function test_raw_validator_rejects_noncanonical_or_overflow_id_before_cast(string $id): void
    {
        $error = $this->rejected(ValidationException::class, fn () => $this->app->make(AccountStatusValidator::class)
            ->update($id, ['status' => 'BLOCKED', 'expected_status' => 'ACTIVE']));

        $this->assertArrayHasKey('user', $error->errors());
    }

    public static function invalidBodies(): array
    {
        return [
            [[], [], 'status'],
            [['status' => 'BLOCKED'], [], 'expected_status'],
            [['status' => null, 'expected_status' => 'ACTIVE'], [], 'status'],
            [['status' => true, 'expected_status' => 'ACTIVE'], [], 'status'],
            [['status' => ['BLOCKED'], 'expected_status' => 'ACTIVE'], [], 'status'],
            [['status' => 'blocked', 'expected_status' => 'ACTIVE'], [], 'status'],
            [['status' => ' BLOCKED ', 'expected_status' => 'ACTIVE'], [], 'status'],
            [['status' => 'INACTIVE', 'expected_status' => 'ACTIVE'], [], 'status'],
            [['status' => 'BLOCKED', 'expected_status' => 'INACTIVE'], [], 'expected_status'],
            [['status' => 'BLOCKED', 'expected_status' => 1], [], 'expected_status'],
            [['status' => 'BLOCKED', 'expected_status' => 'ACTIVE', 'role' => 'ADMIN'], [], 'role'],
            [['status' => 'BLOCKED', 'expected_status' => 'ACTIVE', 'actor_id' => 1], [], 'actor_id'],
            [['status' => 'BLOCKED', 'expected_status' => 'ACTIVE', 'user' => '99'], [], 'user'],
            [['status' => 'BLOCKED', 'expected_status' => 'ACTIVE', 'name' => 'Changed'], [], 'name'],
            [['status' => 'BLOCKED', 'expected_status' => 'ACTIVE'], ['status' => 'ACTIVE'], 'status'],
        ];
    }

    #[DataProvider('invalidBodies')]
    public function test_raw_validator_rejects_bad_fields_and_unknown_input(array $body, array $query, string $field): void
    {
        $error = $this->rejected(ValidationException::class,
            fn () => $this->app->make(AccountStatusValidator::class)->update('12', $body, $query));

        $this->assertArrayHasKey($field, $error->errors());
    }

    public static function invalidCommands(): array
    {
        return [[0, 'BLOCKED', 'ACTIVE', 'user'], [-1, 'BLOCKED', 'ACTIVE', 'user'],
            [12, 'INACTIVE', 'ACTIVE', 'status'], [12, 'BLOCKED', 'INACTIVE', 'expected_status']];
    }

    #[DataProvider('invalidCommands')]
    public function test_direct_service_cannot_bypass_command_bounds(int $id, string $status, string $expected, string $field): void
    {
        $actor = User::factory()->admin()->create();
        $before = $this->snapshot();

        $error = $this->rejected(ValidationException::class, fn () => $this->update($actor,
            new UpdateAccountStatusRequest($id, UserStatus::from($status), UserStatus::from($expected))));

        $this->assertArrayHasKey($field, $error->errors());
        $this->assertSame($before, $this->snapshot());
        Log::shouldNotHaveReceived('info');
    }
}
