<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Mappers\Admin\AdminUserMapper;
use App\Models\HostAgreementAcceptance;
use App\Models\PartnerAgreement;
use App\Models\User;
use App\Repositories\Admin\AdminUserRepository;
use App\Services\Admin\AdminUserService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;
use Throwable;

class AdminHostApprovalRegressionTest extends TestCase
{
    // Observe afterCommit using real outer transactions, not a wrapping test transaction.
    use DatabaseMigrations;

    public function createApplication()
    {
        foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:',
            'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'] as $key => $expected) {
            if (($_ENV[$key] ?? getenv($key)) !== $expected) {
                throw new RuntimeException('Approval regression requires SQLite in-memory before boot.');
            }
        }
        if (is_file(dirname(__DIR__, 3).'/bootstrap/cache/config.php')) {
            throw new RuntimeException('Approval regression rejects cached configuration.');
        }
        $app = parent::createApplication();
        if ($app['config']->get('database.default') !== 'sqlite'
            || $app['config']->get('database.connections.sqlite.database') !== ':memory:'
            || !empty($app['config']->get('database.connections.sqlite.url'))) {
            throw new RuntimeException('Approval regression requires isolated database configuration.');
        }

        return $app;
    }

    protected function beforeRefreshingDatabase() { $this->assertSafeTestDatabase(); }

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 22)->setTime(10, 0));
        Log::spy();
    }

    private function pending(User $host, string $method = 'REGISTRATION_PENDING', string $version = 'fixture-v1'): HostAgreementAcceptance
    {
        $agreement = PartnerAgreement::create(['version' => $version, 'title' => 'Fixture', 'terms_body' => 'Fixture',
            'content_sha256' => hash('sha256', $version), 'commission_rate_bps' => 1000, 'status' => 'PUBLISHED',
            'effective_at' => now()->subDays(2)]);

        return HostAgreementAcceptance::create(['host_id' => $host->id, 'partner_agreement_id' => $agreement->id,
            'accepted_content_sha256' => $agreement->content_sha256, 'accepted_at' => now()->subDay(),
            'method' => $method, 'created_at' => now()->subDay(), 'updated_at' => now()->subDay()]);
    }

    private function approve(User $actor, User $host): User
    {
        return $this->app->make(AdminUserService::class)->approveHost($host->id, $actor->id);
    }

    private function snapshot(string $table): string { return DB::table($table)->orderBy('id')->get()->toJson(); }

    private function rejected(string $class, callable $operation): Throwable
    {
        try { $operation(); } catch (Throwable $error) {
            $this->assertInstanceOf($class, $error);
            return $error;
        }
        $this->fail('Expected '.$class);
    }

    public static function statuses(): array { return [['ACTIVE'], ['BLOCKED'], ['INACTIVE']]; }

    #[DataProvider('statuses')]
    public function test_approval_preserves_all_user_fields_including_status_and_timestamp(string $status): void
    {
        $actor = User::factory()->admin()->create();
        $host = User::factory()->host()->create(['status' => $status, 'updated_at' => now()->subDay()]);
        $acceptance = $this->pending($host);
        $other = User::factory()->host()->create();
        $otherAcceptance = $this->pending($other, 'REGISTRATION_PENDING', 'fixture-v2');
        $beforeUsers = $this->snapshot('users');
        $beforeAcceptance = $acceptance->fresh()->getRawOriginal();
        $beforeOther = $otherAcceptance->fresh()->getRawOriginal();

        $result = $this->approve($actor, $host);

        $this->assertSame($beforeUsers, $this->snapshot('users'));
        $this->assertSame($status, $result->status->value);
        $this->assertSame(array_replace($beforeAcceptance, ['method' => 'REGISTRATION_APPROVED',
            'updated_at' => now()->format('Y-m-d H:i:s')]), $acceptance->fresh()->getRawOriginal());
        $this->assertSame($beforeOther, $otherAcceptance->fresh()->getRawOriginal());
        $row = $this->app->make(AdminUserMapper::class)->toAdminUserItem($result);
        $this->assertTrue($row['is_host_approved']);
        $this->assertSame('APPROVED', $row['host_approval_status']);
        $this->assertSame($status, $row['status']);
        Log::shouldHaveReceived('info')->once()->with('Admin đã phê duyệt Đối tác (Host).', [
            'admin_id' => $actor->id, 'host_id' => $host->id,
        ]);
    }

    public function test_repeated_approval_has_no_write_no_timestamp_touch_and_no_duplicate_log(): void
    {
        $actor = User::factory()->admin()->create();
        $host = User::factory()->host()->blocked()->create();
        $acceptance = $this->pending($host);
        $this->approve($actor, $host);
        $beforeUsers = $this->snapshot('users');
        $beforeAcceptance = $acceptance->fresh()->getRawOriginal();
        $this->travel(5)->minutes();
        DB::enableQueryLog();
        $result = $this->approve($actor, $host);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame($beforeUsers, $this->snapshot('users'));
        $this->assertSame($beforeAcceptance, $acceptance->fresh()->getRawOriginal());
        $this->assertSame(UserStatus::BLOCKED, $result->status);
        foreach ($queries as $query) $this->assertStringStartsWith('select ', strtolower($query['query']));
        Log::shouldHaveReceived('info')->once();
    }

    public function test_approval_changes_all_pending_acceptances_but_preserves_existing_accepted_history(): void
    {
        $actor = User::factory()->admin()->create();
        $host = User::factory()->host()->blocked()->create();
        $first = $this->pending($host);
        $second = $this->pending($host, 'REGISTRATION_PENDING', 'fixture-v2');
        $accepted = $this->pending($host, 'CHECKBOX_CLICKWRAP', 'fixture-v3');
        $history = $accepted->fresh()->getRawOriginal();
        $beforeUsers = $this->snapshot('users');

        $result = $this->approve($actor, $host);

        $this->assertSame('REGISTRATION_APPROVED', $first->fresh()->method);
        $this->assertSame('REGISTRATION_APPROVED', $second->fresh()->method);
        $this->assertSame($history, $accepted->fresh()->getRawOriginal());
        $this->assertSame($beforeUsers, $this->snapshot('users'));
        $this->assertTrue($this->app->make(AdminUserMapper::class)->toAdminUserItem($result)['is_host_approved']);
        Log::shouldHaveReceived('info')->once();
    }

    public function test_legacy_host_without_pending_records_is_a_read_only_noop(): void
    {
        $actor = User::factory()->admin()->create();
        $host = User::factory()->host()->create(['status' => 'INACTIVE']);
        $before = $this->snapshot('users');
        $row = $this->app->make(AdminUserMapper::class)->toAdminUserItem($this->approve($actor, $host));
        $this->assertSame($before, $this->snapshot('users'));
        $this->assertTrue($row['is_host_approved']);
        $this->assertSame('INACTIVE', $row['status']);
        Log::shouldNotHaveReceived('info');
    }

    public static function deniedActors(): array
    {
        return [['CUSTOMER', 'ACTIVE'], ['HOST', 'ACTIVE'], ['ADMIN', 'BLOCKED'], ['ADMIN', 'INACTIVE']];
    }

    #[DataProvider('deniedActors')]
    public function test_direct_service_rechecks_actor_authority_from_database(string $role, string $status): void
    {
        $actor = User::factory()->admin()->create();
        $host = User::factory()->host()->create();
        $this->pending($host);
        User::query()->whereKey($actor->id)->update(['role' => $role, 'status' => $status]);
        $beforeUsers = $this->snapshot('users');
        $beforeAcceptances = $this->snapshot('host_agreement_acceptances');

        $this->rejected(AuthorizationException::class, fn () => $this->approve($actor, $host));

        $this->assertSame($beforeUsers, $this->snapshot('users'));
        $this->assertSame($beforeAcceptances, $this->snapshot('host_agreement_acceptances'));
        Log::shouldNotHaveReceived('info');
    }

    public function test_missing_actor_cannot_approve(): void
    {
        $host = User::factory()->host()->create();
        $this->pending($host);
        $before = $this->snapshot('host_agreement_acceptances');
        $this->rejected(AuthorizationException::class,
            fn () => $this->app->make(AdminUserService::class)->approveHost($host->id, 999));
        $this->assertSame($before, $this->snapshot('host_agreement_acceptances'));
        Log::shouldNotHaveReceived('info');
    }

    public function test_nonhost_and_missing_targets_keep_existing_errors(): void
    {
        $actor = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $this->rejected(ValidationException::class, fn () => $this->approve($actor, $customer));
        $this->rejected(ValidationException::class, fn () => $this->approve($actor, $actor));
        $this->rejected(NotFoundHttpException::class,
            fn () => $this->app->make(AdminUserService::class)->approveHost(999, $actor->id));
        Log::shouldNotHaveReceived('info');
    }

    public function test_success_log_waits_until_real_outer_commit(): void
    {
        $actor = User::factory()->admin()->create();
        $host = User::factory()->host()->blocked()->create();
        $acceptance = $this->pending($host);
        DB::beginTransaction();
        try {
            $this->approve($actor, $host);
            $this->assertSame('REGISTRATION_APPROVED', $acceptance->fresh()->method);
            $this->assertSame(1, DB::transactionLevel());
            Log::shouldNotHaveReceived('info');
            DB::commit();
        } finally { if (DB::transactionLevel() > 0) DB::rollBack(); }
        Log::shouldHaveReceived('info')->once()->with('Admin đã phê duyệt Đối tác (Host).', [
            'admin_id' => $actor->id, 'host_id' => $host->id,
        ]);
    }

    public function test_outer_rollback_restores_acceptance_without_logging_success(): void
    {
        $actor = User::factory()->admin()->create();
        $host = User::factory()->host()->blocked()->create();
        $this->pending($host);
        $beforeUsers = $this->snapshot('users');
        $beforeAcceptances = $this->snapshot('host_agreement_acceptances');
        DB::beginTransaction();
        try { $this->approve($actor, $host); } finally { DB::rollBack(); }
        $this->assertSame($beforeUsers, $this->snapshot('users'));
        $this->assertSame($beforeAcceptances, $this->snapshot('host_agreement_acceptances'));
        DB::transaction(static fn () => null);
        Log::shouldNotHaveReceived('info');
    }

    public function test_failure_after_acceptance_write_rolls_back_and_keeps_exception_identity(): void
    {
        $actor = User::factory()->admin()->create();
        $host = User::factory()->host()->create();
        $this->pending($host);
        $before = $this->snapshot('host_agreement_acceptances');
        $failure = new RuntimeException('Approval persistence fixture failure.');
        $repository = new AdminUserRepository;
        $this->partialMock(AdminUserRepository::class, function ($mock) use ($repository, $failure): void {
            $mock->shouldReceive('approvePendingAcceptances')->once()->andReturnUsing(static function (int $id) use ($repository, $failure): never {
                $repository->approvePendingAcceptances($id);
                throw $failure;
            });
        });
        $this->assertSame($failure, $this->rejected(RuntimeException::class, fn () => $this->approve($actor, $host)));
        $this->assertSame($before, $this->snapshot('host_agreement_acceptances'));
        $this->assertSame(0, DB::transactionLevel());
        Log::shouldNotHaveReceived('info');
    }

    public function test_mapper_requires_loaded_facts_and_does_not_query(): void
    {
        $host = User::factory()->host()->create();
        DB::enableQueryLog();
        $this->rejected(\LogicException::class, fn () => $this->app->make(AdminUserMapper::class)->toAdminUserItem($host));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertSame([], $queries);
    }

    public function test_approval_locks_target_and_actor_in_ascending_order_when_target_id_is_lower(): void
    {
        $host = User::factory()->host()->create();
        $actor = User::factory()->admin()->create();
        $this->pending($host);
        DB::enableQueryLog();
        $this->approve($actor, $host);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertSame([$host->id], $queries[0]['bindings']);
        $this->assertSame([$actor->id], $queries[1]['bindings']);
        // SQLite establishes lookup order, not MySQL concurrency behavior.
    }
}
