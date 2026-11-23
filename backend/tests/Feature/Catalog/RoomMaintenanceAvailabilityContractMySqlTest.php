<?php

namespace Tests\Feature\Catalog;

use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use App\Services\Catalog\RoomMaintenanceQueryService;
use Illuminate\Database\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\HostRoomMaintenanceRaceWorker;
use Tests\TestCase;

final class RoomMaintenanceAvailabilityContractMySqlTest extends TestCase
{
    private const CHECK_IN = '2026-12-12';
    private const CHECK_OUT = '2026-12-14';

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
            ->whereIn('TABLE_NAME', ['maintenances', 'rooms', 'properties'])
            ->pluck('ENGINE');
        $this->assertCount(3, $engines);
        $this->assertSame(['InnoDB'], $engines->unique()->values()->all());
        $this->travelTo(now()->setDate(2026, 12, 10)->setTime(12, 0));
    }

    public static function maintenanceCases(): array
    {
        return [
            'scheduled overlaps' => ['SCHEDULED', '2026-12-13', '2026-12-15', true],
            'scheduled ends at check-in' => ['SCHEDULED', '2026-12-09', self::CHECK_IN, false],
            'scheduled starts at checkout' => ['SCHEDULED', self::CHECK_OUT, '2026-12-16', false],
            'overdue work in progress' => ['IN_PROGRESS', '2026-12-05', '2026-12-08', true],
            'reversed scheduled dates' => ['SCHEDULED', '2026-12-17', '2026-12-11', true],
            'equal scheduled dates outside requested dates' => ['SCHEDULED', '2026-12-16', '2026-12-16', true],
            'scheduled invalid calendar day' => ['SCHEDULED', '2026-02-30', '2026-03-03', true],
            'scheduled zero date' => ['SCHEDULED', '0000-00-00', '2026-12-01', true],
            'completed overlaps' => ['COMPLETED', self::CHECK_IN, self::CHECK_OUT, false],
            'cancelled overlaps' => ['CANCELLED', self::CHECK_IN, self::CHECK_OUT, false],
            'schedule belongs to another room' => ['SCHEDULED', self::CHECK_IN, self::CHECK_OUT, false, true],
        ];
    }

    #[DataProvider('maintenanceCases')]
    public function test_availability_and_search_match_uc51(
        string $status,
        string $start,
        string $end,
        bool $blocked,
        bool $otherRoom = false,
    ): void {
        $property = Property::factory()->create(['status' => 'ACTIVE']);
        $attributes = ['property_id' => $property->id, 'status' => 'ACTIVE', 'capacity' => 2];
        $target = Room::factory()->create($attributes);
        $control = Room::factory()->create($attributes);
        $scheduledRoom = $otherRoom ? Room::factory()->create($attributes) : $target;
        $mode = $this->db->selectOne('SELECT @@SESSION.sql_mode AS mode')->mode;
        $this->db->statement("SET SESSION sql_mode = 'ALLOW_INVALID_DATES'");
        try {
            $this->db->table('maintenances')->insert([
                'room_id' => $scheduledRoom->id, 'status' => $status, 'start_date' => $start, 'end_date' => $end,
            ]);
        } finally {
            $this->db->statement('SET SESSION sql_mode = ?', [$mode]);
        }
        $filters = ['check_in_date' => self::CHECK_IN, 'check_out_date' => self::CHECK_OUT, 'guest_count' => 2];
        $availability = $this->getJson('/api/public/rooms/'.$target->id.'/availability?'.http_build_query($filters))
            ->assertOk()->json('data');
        $search = $this->getJson('/api/public/rooms/search?'.http_build_query($filters + ['property_id' => $property->id]))
            ->assertOk();
        $listedIds = array_column($search->json('data'), 'id');
        $this->assertContains($control->id, $listedIds, 'A room without maintenance must remain in search results.');
        if ($otherRoom) {
            $this->assertNotContains($scheduledRoom->id, $listedIds);
        }

        $this->assertSame([
            'uc51_blocks' => $blocked,
            'available' => !$blocked,
            'reasons' => $blocked ? ['MAINTENANCE_OVERLAP'] : [],
            'listed' => !$blocked,
        ], [
            'uc51_blocks' => app(RoomMaintenanceQueryService::class)->hasBlockingMaintenance($target->id, self::CHECK_IN, self::CHECK_OUT),
            'available' => $availability['available'],
            'reasons' => $availability['reasons'],
            'listed' => in_array($target->id, $listedIds, true),
        ]);
        if ($blocked) {
            $this->actingAs(User::factory()->customer()->create())
                ->postJson('/api/customer/bookings', $filters + ['room_id' => $target->id])
                ->assertConflict()->assertJsonPath('errors.reasons', ['MAINTENANCE_OVERLAP']);
            $this->assertSame(0, $this->db->table('bookings')->where('room_id', $target->id)->count());
        }
    }
}
