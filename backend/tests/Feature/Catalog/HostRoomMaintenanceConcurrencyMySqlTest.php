<?php

namespace Tests\Feature\Catalog;

use App\DTOs\Catalog\Requests\CreateHostRoomMaintenanceRequest;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use App\Services\Catalog\HostRoomMaintenanceService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\Support\HostRoomMaintenanceRaceWorker;
use Tests\TestCase;

final class HostRoomMaintenanceConcurrencyMySqlTest extends TestCase
{
    private const START = '2026-12-12';
    private const END = '2026-12-14';

    private Connection $db;

    public function createApplication()
    {
        if (getenv('STAYHUB_UC23_TEST_DATABASE') === false) {
            $this->markTestSkipped('UC23 InnoDB requires an explicitly provisioned fixture schema.');
        }
        HostRoomMaintenanceRaceWorker::target();

        return parent::createApplication();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = HostRoomMaintenanceRaceWorker::connect($this->app);
        $schema = $this->db->getSchemaBuilder();
        if (! $schema->hasTable('migrations')) {
            if ($schema->getTables() !== []) {
                throw new RuntimeException('UC23 fixtures require an empty or migrated disposable schema.');
            }
            $this->artisan('migrate', ['--no-interaction' => true])->assertExitCode(0);
        }
        $engines = $this->db->table('information_schema.TABLES')
            ->where('TABLE_SCHEMA', $this->db->getDatabaseName())
            ->whereIn('TABLE_NAME', ['properties', 'rooms', 'bookings', 'maintenances'])
            ->pluck('ENGINE', 'TABLE_NAME');
        $this->assertCount(4, $engines);
        $this->assertSame(['InnoDB'], $engines->unique()->values()->all());
        $this->travelTo(CarbonImmutable::parse('2026-12-10 12:00:00', config('app.timezone')));
    }

    public static function races(): array
    {
        return [
            'overlap with committed holder and older waiter snapshot' => [self::START, self::END, true, true, 0, ['holding']],
            'adjacent days with committed holder' => [self::END, '2026-12-16', true, false, 1, ['holding', 'waiting']],
            'overlap with rolled back holder' => [self::START, self::END, false, false, 1, ['waiting']],
        ];
    }

    #[DataProvider('races')]
    public function test_waiting_request_uses_current_facts_on_innodb(
        string $waiterStart,
        string $waiterEnd,
        bool $commitHolder,
        bool $conflict,
        int $expectedSnapshotCount,
        array $expectedRemaining,
    ): void {
        [$host, $room] = $this->fixture();
        $holderId = (int) HostRoomMaintenanceRaceWorker::identity($this->db)->connection_id;
        $worker = $this->worker([(string) $host->id, (string) $room->id, $waiterStart, $waiterEnd]);
        $this->db->beginTransaction();

        try {
            $input = new CreateHostRoomMaintenanceRequest($room->id, self::START, self::END, 'holding');
            $holding = $this->app->make(HostRoomMaintenanceService::class)->create($host, $input);
            $this->assertNotNull($holding);
            $this->assertSame(1, $this->db->transactionLevel(), 'The outer transaction must still own the locks.');
            $this->assertTrue($this->db->table('maintenances')->where('id', $holding->id)->exists());

            $worker->start();
            $snapshot = $this->waitUntil($worker, fn () => $this->event($worker, 'snapshot'), 'worker snapshot');
            $this->assertNotSame($holderId, $snapshot['connection_id']);
            $this->assertSame(0, $snapshot['count'], 'The waiting connection must not see the uncommitted insert.');
            $this->waitUntil($worker, fn () => $this->lockQueryIsActive($snapshot['connection_id']), 'waiter SELECT FOR UPDATE');
            $this->assertNull($this->event($worker, 'result'));

            if ($commitHolder) {
                $this->db->commit();
            } else {
                $this->db->rollBack();
            }
            $this->assertSame(0, $worker->wait(), $worker->getErrorOutput());

            $result = $this->event($worker, 'result');
            $this->assertNotNull($result);
            $this->assertSame($expectedSnapshotCount, $result['snapshot_count']);
            if ($conflict) {
                $this->assertSame(409, $result['status']);
                $this->assertSame('CATALOG_ROOM_MAINTENANCE_CONFLICT', $result['code']);
                $this->assertArrayNotHasKey('maintenance', $result);
            } else {
                $this->assertSame([
                    'id' => $result['maintenance']['id'],
                    'start_date' => $waiterStart,
                    'end_date' => $waiterEnd,
                    'reason' => 'waiting',
                    'status' => 'SCHEDULED',
                ], $result['maintenance']);
            }

            $committed = $this->db->table('maintenances')->where('room_id', $room->id)->orderBy('id')
                ->get(['id', 'start_date', 'end_date', 'reason', 'status']);
            $this->assertCount(count($expectedRemaining), $committed);
            $committed = $committed->keyBy('reason');
            $this->assertSame($expectedRemaining, $committed->keys()->all());
            if ($commitHolder) {
                $this->assertSame($holding->jsonSerialize(), (array) $committed['holding']);
            }
            if (! $conflict) {
                $this->assertSame($result['maintenance'], (array) $committed['waiting']);
            }
        } finally {
            if ($this->db->transactionLevel() > 0) {
                $this->db->rollBack(0);
            }
            $worker->stop();
        }
    }

    public function test_worker_refuses_a_development_schema_before_booting(): void
    {
        $worker = $this->worker([], [
            'DB_DATABASE' => 'stayhub',
            'STAYHUB_UC23_TEST_DATABASE' => 'stayhub',
        ]);
        $this->assertSame(2, $worker->run());
        $this->assertSame('', $worker->getOutput());
        $this->assertStringContainsString('UC23 refuses any target', $worker->getErrorOutput());
    }

    private function worker(array $args, array $env = []): Process
    {
        $script = dirname(__DIR__, 2).'/Support/HostRoomMaintenanceRaceWorker.php';

        return new Process([PHP_BINARY, $script, ...$args], base_path(), $env, null, 35);
    }

    private function event(Process $worker, string $phase): ?array
    {
        $output = $worker->getOutput();
        $lastNewline = strrpos($output, "\n");
        if ($lastNewline === false) {
            return null;
        }
        foreach (explode("\n", substr($output, 0, $lastNewline)) as $line) {
            $event = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
            if ($event['phase'] === $phase) {
                return $event;
            }
        }

        return null;
    }

    private function waitUntil(Process $worker, callable $check, string $description): mixed
    {
        $deadline = hrtime(true) + 10_000_000_000;
        do {
            $value = $check();
            if ($value) {
                return $value;
            }
            if (! $worker->isRunning()) {
                break;
            }
            usleep(25_000);
        } while (hrtime(true) < $deadline);

        $this->fail('Could not observe '.$description.'. '.$worker->getErrorOutput());
    }

    private function lockQueryIsActive(int $connectionId): bool
    {
        foreach ($this->db->select('SHOW FULL PROCESSLIST') as $entry) {
            $sql = strtolower((string) $entry->Info);
            // The holder still owns this property row. The waiter's matching locking query cannot finish before release.
            if ((int) $entry->Id === $connectionId && str_contains($sql, 'from `properties`') && str_contains($sql, 'for update')) {
                return true;
            }
        }

        return false;
    }

    /** @return array{User, Room} */
    private function fixture(): array
    {
        $host = User::factory()->create([
            'email' => 'maintenance-'.bin2hex(random_bytes(8)).'@uc23.example.test',
            'role' => 'HOST',
        ]);
        $property = Property::create([
            'host_id' => $host->id, 'name' => 'UC23 maintenance fixture', 'type' => 'HOTEL',
            'address' => 'Fixture address', 'status' => 'ACTIVE',
        ]);
        $type = $property->roomTypes()->create(['name' => 'Standard']);
        $room = $property->rooms()->create([
            'room_type_id' => $type->id, 'room_number' => '101', 'name' => 'Fixture room',
            'capacity' => 2, 'price_per_night' => '100.00', 'status' => 'ACTIVE',
        ]);

        return [$host, $room];
    }
}
