<?php

namespace Tests\Feature\Profile;

use App\DTOs\Profile\Requests\UploadAvatarRequest;
use App\Models\User;
use App\Services\Profile\AvatarService;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

final class AvatarTransactionTest extends TestCase
{
    private const CONNECTION = 'profile_avatar_commit_test';
    private const SUCCESS = 'Cập nhật ảnh đại diện thành công.';
    private ?string $previousConnection = null;
    private User $actor;
    private string $originalPath;

    public function createApplication()
    {
        foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:',
            'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'] as $key => $expected) {
            if (($_ENV[$key] ?? getenv($key)) !== $expected) {
                throw new RuntimeException('Avatar commit tests require isolated SQLite in-memory.');
            }
        }
        if (is_file(dirname(__DIR__, 3).'/bootstrap/cache/config.php')) {
            throw new RuntimeException('Avatar commit tests refuse cached configuration.');
        }

        $app = parent::createApplication();
        if ($app['config']->get('database.default') !== 'sqlite'
            || $app['config']->get('database.connections.sqlite.database') !== ':memory:'
            || !empty($app['config']->get('database.connections.sqlite.url'))) {
            throw new RuntimeException('Unexpected effective avatar test database.');
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousConnection = DB::getDefaultConnection();
        config(['database.connections.'.self::CONNECTION => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        DB::setDefaultConnection(self::CONNECTION);
        // Use Laravel's production manager, without RefreshDatabase's enclosing test transaction.
        $manager = new DatabaseTransactionsManager();
        $this->app->instance('db.transactions', $manager);
        DB::connection()->setTransactionManager($manager);
        $this->artisan('migrate', ['--database' => self::CONNECTION, '--force' => true])->assertExitCode(0);
        Storage::fake('local');
        $this->actor = User::factory()->create(['role' => 'CUSTOMER', 'status' => 'ACTIVE']);
        $this->originalPath = 'profile-avatars/'.$this->actor->id.'/'.Str::uuid().'.png';
        Storage::disk('local')->put($this->originalPath, 'Original owned image');
        $this->actor->update(['avatar' => $this->originalPath]);
        Log::spy();
        $this->assertSame(0, DB::transactionLevel());
    }

    protected function tearDown(): void
    {
        try {
            if ($this->previousConnection !== null) {
                while (DB::connection(self::CONNECTION)->transactionLevel() > 0) {
                    DB::connection(self::CONNECTION)->rollBack();
                }
                DB::purge(self::CONNECTION);
                DB::setDefaultConnection($this->previousConnection);
            }
        } finally {
            parent::tearDown();
        }
    }

    private function upload(): string
    {
        $chunk = fn ($type, $data) => pack('N', strlen($data)).$type.$data.pack('H*', hash('crc32b', $type.$data));
        $bytes = "\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNCCCCC', 1, 1, 8, 6, 0, 0, 0))
            .$chunk('IDAT', gzcompress("\0\xff\0\0\xff")).$chunk('IEND', '');
        $request = new UploadAvatarRequest(UploadedFile::fake()->createWithContent('fixture.png', $bytes));

        return $this->app->make(AvatarService::class)->upload($this->actor, $request)->avatar;
    }

    public function test_standalone_commit_removes_only_previous_owned_photo_and_logs_once(): void
    {
        $otherPath = 'profile-avatars/999/'.Str::uuid().'.png';
        Storage::disk('local')->put($otherPath, 'Other owner photo');
        $path = $this->upload();

        $this->assertSame($path, $this->actor->fresh()->avatar);
        Storage::disk('local')->assertExists($path);
        Storage::disk('local')->assertMissing($this->originalPath);
        Storage::disk('local')->assertExists($otherPath);
        Log::shouldHaveReceived('info')->once()->with(self::SUCCESS);
    }

    public function test_outer_commit_defers_cleanup_and_log_until_real_commit(): void
    {
        DB::beginTransaction();
        $path = $this->upload();

        Storage::disk('local')->assertExists($this->originalPath);
        Storage::disk('local')->assertExists($path);
        Log::shouldNotHaveReceived('info');
        DB::commit();

        Storage::disk('local')->assertMissing($this->originalPath);
        Storage::disk('local')->assertExists($path);
        $this->assertSame($path, $this->actor->fresh()->avatar);
        Log::shouldHaveReceived('info')->once()->with(self::SUCCESS);
    }

    public function test_outer_rollback_keeps_original_and_removes_uncommitted_file_without_success_log(): void
    {
        DB::beginTransaction();
        $path = $this->upload();
        DB::rollBack();

        $this->assertSame($this->originalPath, $this->actor->fresh()->avatar);
        Storage::disk('local')->assertExists($this->originalPath);
        Storage::disk('local')->assertMissing($path);
        Log::shouldNotHaveReceived('info');
    }

    public function test_savepoint_rollback_then_outer_commit_preserves_parent_upload(): void
    {
        DB::beginTransaction();
        $parentPath = $this->upload();
        DB::beginTransaction();
        $childPath = $this->upload();
        DB::rollBack();

        $this->assertSame($parentPath, $this->actor->fresh()->avatar);
        Storage::disk('local')->assertExists($this->originalPath);
        Storage::disk('local')->assertExists($parentPath);
        Storage::disk('local')->assertMissing($childPath);
        Log::shouldNotHaveReceived('info');
        DB::commit();

        Storage::disk('local')->assertMissing($this->originalPath);
        Storage::disk('local')->assertExists($parentPath);
        Log::shouldHaveReceived('info')->once()->with(self::SUCCESS);
    }

    public function test_committed_savepoint_then_outer_rollback_cleans_child_file(): void
    {
        DB::beginTransaction();
        DB::beginTransaction();
        $path = $this->upload();
        DB::commit();
        DB::rollBack();

        $this->assertSame($this->originalPath, $this->actor->fresh()->avatar);
        Storage::disk('local')->assertExists($this->originalPath);
        Storage::disk('local')->assertMissing($path);
        Log::shouldNotHaveReceived('info');
        Log::shouldNotHaveReceived('warning');
    }

    public static function outerResults(): array
    {
        return [[true], [false]];
    }

    #[DataProvider('outerResults')]
    public function test_multiple_uploads_follow_final_outer_result(bool $commit): void
    {
        DB::beginTransaction();
        $first = $this->upload();
        $second = $this->upload();
        Storage::disk('local')->assertExists($this->originalPath);
        Storage::disk('local')->assertExists($first);
        Storage::disk('local')->assertExists($second);
        Log::shouldNotHaveReceived('info');

        if ($commit) {
            DB::commit();
            $this->assertSame($second, $this->actor->fresh()->avatar);
            Storage::disk('local')->assertExists($second);
            Storage::disk('local')->assertMissing($this->originalPath);
            Storage::disk('local')->assertMissing($first);
            Log::shouldHaveReceived('info')->twice()->with(self::SUCCESS);
        } else {
            DB::rollBack();
            $this->assertSame($this->originalPath, $this->actor->fresh()->avatar);
            Storage::disk('local')->assertExists($this->originalPath);
            Storage::disk('local')->assertMissing($first);
            Storage::disk('local')->assertMissing($second);
            Log::shouldNotHaveReceived('info');
            Log::shouldNotHaveReceived('warning');
        }
    }

    public static function cleanupFailures(): array
    {
        return [[false], [true]];
    }

    public function test_success_logger_failure_does_not_remove_a_committed_new_photo(): void
    {
        Log::shouldReceive('info')->once()->with(self::SUCCESS)
            ->andThrow(new RuntimeException('Fixture logger failure'));
        try {
            $this->upload();
            $this->fail('Expected fixture logger failure.');
        } catch (RuntimeException $error) {
            $this->assertSame('Fixture logger failure', $error->getMessage());
        }

        $path = $this->actor->fresh()->avatar;
        $this->assertNotSame($this->originalPath, $path);
        Storage::disk('local')->assertExists($path);
        Storage::disk('local')->assertMissing($this->originalPath);
    }

    public function test_rollback_cleanup_failure_preserves_the_original_database_exception(): void
    {
        $disk = Storage::disk('local');
        $proxy = Mockery::mock($disk);
        $proxy->shouldReceive('delete')->once()->andThrow(new RuntimeException('Fixture delete failure'));
        Storage::shouldReceive('disk')->with('local')->andReturn($proxy);
        User::updated(fn () => throw new RuntimeException('Fixture database failure'));
        try {
            $this->upload();
            $this->fail('Expected fixture database failure.');
        } catch (RuntimeException $error) {
            $this->assertSame('Fixture database failure', $error->getMessage());
        } finally {
            User::flushEventListeners();
        }

        $this->assertSame($this->originalPath, $this->actor->fresh()->avatar);
        $this->assertTrue($disk->exists($this->originalPath));
        Log::shouldNotHaveReceived('info');
        Log::shouldHaveReceived('warning')->once()->with('Không thể dọn tệp ảnh đại diện.');
    }

    #[DataProvider('cleanupFailures')]
    public function test_cleanup_failure_keeps_committed_new_photo_and_reports_warning(bool $throws): void
    {
        $disk = Storage::disk('local');
        $proxy = Mockery::mock($disk);
        $failure = $proxy->shouldReceive('delete')->once()->with($this->originalPath);
        if ($throws) {
            $failure->andThrow(new RuntimeException('Fixture delete failure'));
        } else {
            $failure->andReturn(false);
        }
        Storage::shouldReceive('disk')->with('local')->andReturn($proxy);
        $path = $this->upload();

        $this->assertSame($path, $this->actor->fresh()->avatar);
        $this->assertTrue($disk->exists($path));
        $this->assertTrue($disk->exists($this->originalPath));
        Log::shouldHaveReceived('warning')->once()->with($throws
            ? 'Không thể dọn tệp ảnh đại diện.' : 'Không thể dọn tệp ảnh đại diện cũ.');
        Log::shouldHaveReceived('info')->once()->with(self::SUCCESS);
    }
}
