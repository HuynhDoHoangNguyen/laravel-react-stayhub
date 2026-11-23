<?php

namespace Tests\Feature\Catalog;

use App\DTOs\Catalog\Requests\AdminAmenityDetailQuery;
use App\DTOs\Catalog\Requests\CreateAdminAmenityRequest;
use App\DTOs\Catalog\Requests\HostRoomAmenityTarget;
use App\DTOs\Catalog\Requests\UpdateAdminAmenityRequest;
use App\Exceptions\AppException;
use App\Models\Amenity;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Services\Catalog\AdminAmenityService;
use App\Services\Catalog\HostRoomAmenityService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class AdminAmenityMySqlTest extends TestCase
{
    public function createApplication()
    {
        if (getenv('UC46_QA') !== '1') {
            $this->markTestSkipped('UC46 requires a provisioned disposable MySQL schema.');
        }
        self::guardEnvironment();

        return parent::createApplication();
    }

    protected function setUp(): void
    {
        parent::setUp();
        self::identity();
    }

    private static function guardEnvironment(): void
    {
        if (getenv('UC46_QA') !== '1' || getenv('ALLOW_EXTERNAL_TEST_DB') !== 'true'
            || !preg_match('/\Astayhub_uc46_[a-f0-9]{16}_test\z/', getenv('DB_DATABASE') ?: '')
            || getenv('DB_HOST') !== '127.0.0.1' || !ctype_digit(getenv('DB_PORT') ?: '')
            || getenv('DB_USERNAME') !== 'uc46qa' || getenv('DB_URL') || getenv('DATABASE_URL') || getenv('DB_SOCKET')) {
            throw new RuntimeException('Refusing a MySQL target outside the UC46 fixture.');
        }
    }

    private static function identity(): object
    {
        $config = config('database.connections.mysql');
        if (app()->configurationIsCached() || config('database.default') !== 'mysql'
            || $config['host'] !== '127.0.0.1' || (string) $config['port'] !== getenv('DB_PORT')
            || $config['username'] !== 'uc46qa' || $config['database'] !== getenv('DB_DATABASE')
            || !empty($config['url']) || !empty($config['unix_socket'])) {
            throw new RuntimeException('Unexpected effective UC46 database configuration.');
        }

        $identity = DB::selectOne('SELECT DATABASE() AS schema_name, CONNECTION_ID() AS connection_id');
        if ($identity->schema_name !== getenv('DB_DATABASE')) {
            throw new RuntimeException('Unexpected selected UC46 schema.');
        }

        return $identity;
    }

    public function test_mysql_collation_blocks_equivalent_new_names_and_releases_mutex_after_rollback(): void
    {
        $actor = User::factory()->admin()->create();
        $service = app(AdminAmenityService::class);
        $column = DB::selectOne("SHOW FULL COLUMNS FROM amenities WHERE Field = 'name'");
        $this->assertSame('utf8mb4_unicode_ci', $column->Collation);
        $name = 'UC46 Đèn Café '.bin2hex(random_bytes(4));
        $service->create($actor, new CreateAdminAmenityRequest($name, null, null, false));
        try {
            $service->create($actor, new CreateAdminAmenityRequest(mb_strtoupper(str_replace('Café', 'Cafe', $name)), null, null, true));
            $this->fail('The database collation should treat these names as equal.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('name', $error->errors());
        }
        $this->assertSame(0, DB::transactionLevel());
        $this->assertSame(1, (int) DB::selectOne('SELECT IS_FREE_LOCK(?) AS available', [$this->key()])->available);
        $another = $service->create($actor, new CreateAdminAmenityRequest($name.' khác', null, null, true));
        $this->assertTrue($another->status);
    }

    public function test_two_real_name_writers_cannot_both_commit(): void
    {
        $actor = User::factory()->admin()->create();
        $suffix = bin2hex(random_bytes(4));
        $name = 'UC46 Café Race '.$suffix;
        $key = $this->key();
        $this->assertSame(1, (int) DB::selectOne('SELECT GET_LOCK(?, 1) AS acquired', [$key])->acquired);
        $first = $this->worker('create', (string) $actor->id, $name);
        $second = $this->worker('create', (string) $actor->id, 'UC46 CAFE Race '.$suffix);

        try {
            try {
                $first->start();
                $second->start();
                $firstId = $this->waitForLock($first);
                $secondId = $this->waitForLock($second);
                $this->assertNotSame($firstId, $secondId);
            } finally {
                DB::selectOne('SELECT RELEASE_LOCK(?) AS unlocked', [$key]);
            }

            $codes = [$this->workerResult($first)['status'], $this->workerResult($second)['status']];
            sort($codes);
            $this->assertSame([201, 422], $codes);
            $this->assertSame(1, Amenity::query()->where('name', $name)->count());
            $this->assertSame(1, (int) DB::selectOne('SELECT IS_FREE_LOCK(?) AS available', [$key])->available);
        } finally {
            $first->stop();
            $second->stop();
        }
    }

    public function test_host_assign_rechecks_after_admin_disables_and_commits(): void
    {
        [$admin, $host, $room, $amenity] = $this->fixture();
        $service = app(AdminAmenityService::class);
        $before = $service->detail($admin, new AdminAmenityDetailQuery($amenity->id));
        $worker = $this->worker('assign', (string) $host->id, (string) $amenity->id, (string) $room->id);

        DB::beginTransaction();
        try {
            $service->update($admin, new UpdateAdminAmenityRequest($amenity->id,
                new CreateAdminAmenityRequest($amenity->name, $amenity->icon, $amenity->description, false), $before->version));
            $worker->start();
            $this->waitForLock($worker);
            DB::commit();
            $this->assertSame(['status' => 422, 'fields' => ['amenity_id']], $this->workerResult($worker));
            $this->assertDatabaseMissing('room_amenities', ['room_id' => $room->id, 'amenity_id' => $amenity->id]);
        } finally {
            if (DB::transactionLevel() > 0) DB::rollBack();
            $worker->stop();
        }
    }

    public function test_admin_disable_after_host_assign_commit_preserves_existing_pivot(): void
    {
        [$admin, $host, $room, $amenity] = $this->fixture();
        $worker = $this->worker('disable', (string) $admin->id, (string) $amenity->id);

        DB::beginTransaction();
        try {
            app(HostRoomAmenityService::class)->assign($host, new HostRoomAmenityTarget($room->id, $amenity->id));
            $worker->start();
            $this->waitForLock($worker);
            DB::commit();
            $this->assertSame(['status' => 200, 'rooms_count' => 1], $this->workerResult($worker));
            $this->assertDatabaseHas('room_amenities', ['room_id' => $room->id, 'amenity_id' => $amenity->id]);
            $this->assertFalse($amenity->fresh()->status);
        } finally {
            if (DB::transactionLevel() > 0) DB::rollBack();
            $worker->stop();
        }
    }

    private function fixture(): array
    {
        $admin = User::factory()->admin()->create();
        $host = User::factory()->host()->create();
        $property = Property::factory()->create(['host_id' => $host->id, 'status' => 'ACTIVE']);
        $type = RoomType::factory()->create(['property_id' => $property->id]);
        $room = Room::factory()->create(['property_id' => $property->id, 'room_type_id' => $type->id]);
        $amenity = Amenity::factory()->create(['name' => 'UC46 race '.bin2hex(random_bytes(4)), 'status' => true]);

        return [$admin, $host, $room, $amenity];
    }

    private function key(): string
    {
        return 'stayhub.uc46.'.substr(hash('sha256', DB::getDatabaseName()), 0, 48);
    }

    private function worker(string ...$args): Process
    {
        $script = 'require "vendor/autoload.php"; Tests\Feature\Catalog\AdminAmenityMySqlTest::runWorker(array_slice($argv, 1));';

        return new Process([PHP_BINARY, '-r', $script, '--', ...$args], base_path(), timeout: 15);
    }

    private function waitForLock(Process $process): int
    {
        $deadline = microtime(true) + 2.8;
        do {
            if (!$process->isRunning()) {
                $this->fail('Worker exited early: '.$process->getErrorOutput().' '.$process->getOutput());
            }
            if (preg_match('/^READY (\d+)/m', $process->getOutput(), $match)) {
                $query = DB::selectOne('SELECT INFO AS sql_text FROM information_schema.PROCESSLIST WHERE ID = ?', [(int) $match[1]]);
                if (preg_match('/GET_LOCK|for update/i', (string) ($query->sql_text ?? ''))) {
                    return (int) $match[1];
                }
            }
            usleep(20000);
        } while (microtime(true) < $deadline);

        $this->fail('Worker did not reach the expected blocked database statement.');
    }

    private function workerResult(Process $process): array
    {
        $this->assertSame(0, $process->wait(), $process->getErrorOutput());
        $lines = explode("\n", trim($process->getOutput()));

        return json_decode(end($lines), true, flags: JSON_THROW_ON_ERROR);
    }

    public static function runWorker(array $args): void
    {
        self::guardEnvironment();
        $app = require 'bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
        $identity = self::identity();
        [$operation, $actorId, $value] = $args;
        $actor = User::findOrFail($actorId);
        echo 'READY '.$identity->connection_id."\n";
        flush();

        try {
            $service = app(AdminAmenityService::class);
            if ($operation === 'create') {
                $service->create($actor, new CreateAdminAmenityRequest($value, null, null, true));
                $result = ['status' => 201];
            } elseif ($operation === 'assign') {
                app(HostRoomAmenityService::class)->assign($actor, new HostRoomAmenityTarget((int) $args[3], (int) $value));
                $result = ['status' => 200];
            } elseif ($operation === 'disable') {
                $id = (int) $value;
                $row = $service->detail($actor, new AdminAmenityDetailQuery($id));
                $changed = $service->update($actor, new UpdateAdminAmenityRequest($id,
                    new CreateAdminAmenityRequest($row->name, $row->icon, $row->description, false), $row->version));
                $result = ['status' => 200, 'rooms_count' => $changed->rooms_count];
            } else {
                throw new RuntimeException('Unknown UC46 fixture operation.');
            }
        } catch (ValidationException $error) {
            $result = ['status' => 422, 'fields' => array_keys($error->errors())];
        } catch (AppException $error) {
            $result = ['status' => $error->status, 'code' => $error->errorCode];
        }
        echo json_encode($result, JSON_THROW_ON_ERROR)."\n";
    }
}
