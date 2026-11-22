<?php

namespace Tests\Feature\Catalog;

use App\DTOs\Booking\Requests\StoreBookingRequest;
use App\DTOs\Catalog\Requests\CreateHostRoomMaintenanceRequest;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use App\Services\Booking\BookingService;
use App\Services\Catalog\HostRoomMaintenanceService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\Support\HostRoomMaintenanceRaceWorker;
use Tests\TestCase;

final class BookingMaintenanceConcurrencyMySqlTest extends TestCase
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

    public static function cases(): array
    {
        $bookingConflict = ['status' => 409, 'reasons' => ['MAINTENANCE_OVERLAP']];
        $maintenanceConflict = ['status' => 409, 'code' => 'CATALOG_ROOM_BOOKING_CONFLICT'];

        return [
            'booking commits first' => ['booking', 'fresh', true, $maintenanceConflict, 'bookings'],
            'maintenance commits first with no preexisting snapshot' => ['maintenance', 'fresh', true, $bookingConflict, 'maintenances'],
            'maintenance commits first with older booking snapshot' => ['maintenance', 'snapshot', true, $bookingConflict, 'maintenances'],
            'maintenance rolls back while booking waits' => ['maintenance', 'fresh', false, ['booking_status' => 'PENDING'], 'bookings'],
        ];
    }

    #[DataProvider('cases')]
    public function test_only_the_eligible_writer_commits(
        string $holder,
        string $snapshotMode,
        bool $commitHolder,
        array $expectedOutcome,
        string $winnerTable,
    ): void {
        [$host, $customer, $room] = $this->fixture();
        $bookingHoldsLock = $holder === 'booking';
        $holderId = (int) HostRoomMaintenanceRaceWorker::identity($this->db)->connection_id;
        $worker = $this->worker($bookingHoldsLock ? $host : $customer, $room,
            $bookingHoldsLock ? 'maintenance' : 'booking', $snapshotMode);
        $this->db->beginTransaction();

        try {
            if ($bookingHoldsLock) {
                $input = new StoreBookingRequest($room->id, self::START, self::END, guestCount: 2);
                $booking = $this->app->make(BookingService::class)->create($customer, $input);
                $this->assertSame('PENDING', $booking->getRawOriginal('status'));
            } else {
                $input = new CreateHostRoomMaintenanceRequest($room->id, self::START, self::END, 'holding');
                $maintenance = $this->app->make(HostRoomMaintenanceService::class)->create($host, $input);
                $this->assertSame('SCHEDULED', $maintenance?->status);
            }
            $this->assertSame(1, $this->db->transactionLevel());

            $worker->start();
            $phase = $snapshotMode === 'snapshot' ? 'snapshot' : 'ready';
            $ready = $this->waitUntil($worker, fn () => $this->event($worker, $phase), 'worker '.$phase);
            $this->assertNotSame($holderId, $ready['connection_id']);
            if ($snapshotMode === 'snapshot') {
                $this->assertSame(0, $ready['count'], 'The booking snapshot predates the maintenance commit.');
            }
            $this->waitUntil($worker, fn () => $this->waitingLockTable($ready['connection_id']), 'waiter SELECT FOR UPDATE');
            $this->assertNull($this->event($worker, 'result'));

            if ($commitHolder) {
                $this->db->commit();
            } else {
                $this->db->rollBack();
            }
            $this->assertSame(0, $worker->wait(), $worker->getErrorOutput());
            $outcome = $this->event($worker, 'result');
            $this->assertNotNull($outcome);
            if ($snapshotMode === 'snapshot') {
                $this->assertSame(0, $outcome['snapshot_count'], 'The old snapshot must still be observable after the service call.');
            }
            unset($outcome['phase'], $outcome['snapshot_count']);

            $expected = [
                'waiter' => $expectedOutcome,
                'bookings' => $winnerTable === 'bookings' ? [$this->period('PENDING')] : [],
                'maintenances' => $winnerTable === 'maintenances' ? [$this->period('SCHEDULED')] : [],
            ];
            $this->assertSame($expected, [
                'waiter' => $outcome,
                'bookings' => $this->periods($room, 'bookings', 'check_in_date', 'check_out_date'),
                'maintenances' => $this->periods($room, 'maintenances', 'start_date', 'end_date'),
            ], 'The waiter outcome and committed rows must match the shared inventory contract.');
        } finally {
            if ($this->db->transactionLevel() > 0) {
                $this->db->rollBack(0);
            }
            $worker->stop();
        }
    }

    public static function confirmationOrders(): array
    {
        return [
            'confirmation locks first' => [true],
            'maintenance locks first' => [false],
        ];
    }

    #[DataProvider('confirmationOrders')]
    public function test_confirmation_and_maintenance_use_consistent_locking(bool $confirmationFirst): void
    {
        [$host, $customer, $room] = $this->fixture();
        $input = new StoreBookingRequest($room->id, self::START, self::END, guestCount: 2);
        $pending = $this->app->make(BookingService::class)->create($customer, $input);
        $this->assertSame('PENDING', $pending->getRawOriginal('status'));
        $this->db->statement('SET SESSION innodb_lock_wait_timeout = 15');

        $holder = $confirmationFirst ? 'confirm' : 'maintenance';
        $waiter = $confirmationFirst ? 'maintenance' : 'confirm';
        $holderId = (int) HostRoomMaintenanceRaceWorker::identity($this->db)->connection_id;
        $worker = $this->worker($host, $room, $waiter, 'fresh', $pending->id);
        $runHolder = fn () => HostRoomMaintenanceRaceWorker::invoke(
            $this->app, $holder, $host, $room->id, self::START, self::END, $pending->id,
        );
        $waitingTable = null;

        try {
            if ($confirmationFirst) {
                $this->db->beginTransaction();
                $runs[$holder] = HostRoomMaintenanceRaceWorker::observe($this->db, $runHolder);
                $this->assertSame(['booking_status' => 'CONFIRMED'], $runs[$holder]['outcome']);
                $waitingTable = $this->startAndObserveWait($worker, $holderId);
                $this->db->commit();
            } else {
                $runs[$holder] = HostRoomMaintenanceRaceWorker::observe(
                    $this->db,
                    $runHolder,
                    function (string $table) use (&$waitingTable, $worker, $holderId): void {
                        if ($table === 'bookings' && $waitingTable === null) {
                            // Maintenance holds the Room lock as it reaches the booking-fact locking read.
                            $waitingTable = $this->startAndObserveWait($worker, $holderId);
                        }
                    },
                );
                $this->assertNotNull($waitingTable, 'Maintenance did not reach the booking-fact locking read.');
            }

            $this->assertSame(0, $worker->wait(), $worker->getErrorOutput());
            $outcome = $this->event($worker, 'result');
            $trace = $this->event($worker, 'trace');
            $this->assertNotNull($outcome);
            $this->assertNotNull($trace);
            unset($outcome['phase']);
            $runs[$waiter] = ['outcome' => $outcome, 'attempts' => $trace['attempts'], 'locks' => $trace['locks']];
            $this->assertContains($waitingTable, $runs[$holder]['locks']);
            $this->assertContains($waitingTable, $runs[$waiter]['locks']);
            $this->assertSame(0, $this->db->transactionLevel());

            $evidence = [
                'waiting_on' => $waitingTable,
                'confirm_locks' => $runs['confirm']['locks'],
                'maintenance_locks' => $runs['maintenance']['locks'],
            ];
            $this->assertSame([
                'confirm' => ['booking_status' => 'CONFIRMED'],
                'maintenance' => ['status' => 409, 'code' => 'CATALOG_ROOM_BOOKING_CONFLICT'],
                'attempts' => ['confirm' => 1, 'maintenance' => 1],
                'bookings' => [$this->period('CONFIRMED')],
                'maintenances' => [],
            ], [
                'confirm' => $runs['confirm']['outcome'],
                'maintenance' => $runs['maintenance']['outcome'],
                'attempts' => ['confirm' => $runs['confirm']['attempts'], 'maintenance' => $runs['maintenance']['attempts']],
                'bookings' => $this->periods($room, 'bookings', 'check_in_date', 'check_out_date'),
                'maintenances' => $this->periods($room, 'maintenances', 'start_date', 'end_date'),
            ], 'Service outcomes and committed rows must match the contract. Lock trace: '.json_encode($evidence, JSON_THROW_ON_ERROR));
        } finally {
            if ($this->db->transactionLevel() > 0) {
                $this->db->rollBack(0);
            }
            $worker->stop();
        }
    }

    private function worker(User $actor, Room $room, string $action, string $snapshotMode, int $bookingId = 0): Process
    {
        return new Process([
            PHP_BINARY, dirname(__DIR__, 2).'/Support/HostRoomMaintenanceRaceWorker.php',
            (string) $actor->id, (string) $room->id, self::START, self::END, $action, $snapshotMode, (string) $bookingId,
        ], base_path(), timeout: 35);
    }

    private function startAndObserveWait(Process $worker, int $holderId): string
    {
        $worker->start();
        $ready = $this->waitUntil($worker, fn () => $this->event($worker, 'ready'), 'worker ready');
        $this->assertNotSame($holderId, $ready['connection_id']);
        $table = $this->waitUntil($worker, fn () => $this->waitingLockTable($ready['connection_id']), 'waiter SELECT FOR UPDATE');
        $this->assertNull($this->event($worker, 'result'));

        return $table;
    }

    private function period(string $status): array
    {
        return ['status' => $status, 'start_date' => self::START, 'end_date' => self::END];
    }

    private function periods(Room $room, string $table, string $startColumn, string $endColumn): array
    {
        return $this->db->table($table)->where('room_id', $room->id)->orderBy('id')
            ->get(['status', $startColumn.' as start_date', $endColumn.' as end_date'])
            ->map(fn (object $row) => (array) $row)->all();
    }

    private function event(Process $worker, string $phase): ?array
    {
        $lines = explode("\n", $worker->getOutput());
        array_pop($lines); // The child may still be writing the last line.
        foreach ($lines as $line) {
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

    private function waitingLockTable(int $connectionId): ?string
    {
        foreach ($this->db->select('SHOW FULL PROCESSLIST') as $entry) {
            if ((int) $entry->Id === $connectionId) {
                $table = HostRoomMaintenanceRaceWorker::lockTable((string) $entry->Info);

                return in_array($table, ['properties', 'rooms'], true) ? $table : null;
            }
        }

        return null;
    }

    /** @return array{User, User, Room} */
    private function fixture(): array
    {
        $fixtureKey = bin2hex(random_bytes(8));
        $host = User::factory()->create(['role' => 'HOST', 'email' => "host-{$fixtureKey}@uc23.example.test"]);
        $customer = User::factory()->create(['role' => 'CUSTOMER', 'email' => "customer-{$fixtureKey}@uc23.example.test"]);
        $property = Property::create([
            'host_id' => $host->id, 'name' => 'UC23 shared inventory fixture', 'type' => 'HOTEL',
            'address' => 'Fixture address', 'status' => 'ACTIVE',
        ]);
        $type = $property->roomTypes()->create(['name' => 'Standard']);
        $room = $property->rooms()->create([
            'room_type_id' => $type->id, 'room_number' => '101', 'name' => 'Fixture room',
            'capacity' => 2, 'price_per_night' => '100.00', 'status' => 'ACTIVE',
        ]);

        return [$host, $customer, $room];
    }
}
