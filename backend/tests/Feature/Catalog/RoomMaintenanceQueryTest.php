<?php

namespace Tests\Feature\Catalog;

use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use App\Repositories\Catalog\CatalogRepository;
use App\Services\Catalog\RoomMaintenanceQueryService;
use App\Validators\Catalog\RoomMaintenanceValidator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use stdClass;

final class RoomMaintenanceQueryTest extends TestCase
{
    use RefreshDatabase;

    public function createApplication()
    {
        foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:',
            'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'] as $key => $expected) {
            if (($_ENV[$key] ?? getenv($key)) !== $expected) {
                throw new RuntimeException('UC51 tests require isolated in-memory configuration before boot.');
            }
        }
        if (is_file(dirname(__DIR__, 3).'/bootstrap/cache/config.php')) {
            throw new RuntimeException('UC51 tests refuse cached application configuration.');
        }
        $app = parent::createApplication();
        $config = $app['config'];
        if ($config->get('database.default') !== 'sqlite'
            || $config->get('database.connections.sqlite.database') !== ':memory:'
            || !empty($config->get('database.connections.sqlite.url'))
            || $config->get('cache.default') !== 'array' || $config->get('session.driver') !== 'array') {
            throw new RuntimeException('UC51 effective configuration must be isolated before RefreshDatabase.');
        }

        return $app;
    }

    private function room(?Property $property = null, string $status = 'ACTIVE'): Room
    {
        $property ??= Property::create([
            'host_id' => User::factory()->create(['role' => 'HOST'])->id,
            'name' => 'UC51 fixture', 'type' => 'HOTEL', 'address' => 'Fixture address', 'status' => 'ACTIVE',
        ]);
        $type = $property->roomTypes()->first() ?? $property->roomTypes()->create(['name' => 'Suite']);

        return $property->rooms()->create([
            'room_type_id' => $type->id, 'room_number' => 'UC51-'.bin2hex(random_bytes(4)),
            'name' => 'Fixture room', 'capacity' => 2, 'price_per_night' => '100.00', 'status' => $status,
        ]);
    }

    /** Direct inserts represent owned read fixtures, including legacy malformed dates in SQLite. */
    private function maintenance(Room $room, string $status, string $start = '2026-12-10', string $end = '2026-12-15'): int
    {
        return DB::table('maintenances')->insertGetId([
            'room_id' => $room->id, 'status' => $status, 'start_date' => $start,
            'end_date' => $end, 'reason' => 'INTERNAL fixture reason',
        ]);
    }

    private function check(int $roomId): bool
    {
        return $this->app->make(RoomMaintenanceQueryService::class)
            ->hasBlockingMaintenance($roomId, '2026-12-10', '2026-12-15');
    }

    public function test_existing_room_without_maintenance_returns_false(): void
    {
        $this->assertFalse($this->check($this->room()->id));
    }

    public static function scheduledIntervals(): array
    {
        return [
            'before' => ['2026-12-01', '2026-12-03', false],
            'ends on check in' => ['2026-12-08', '2026-12-10', false],
            'starts on check out' => ['2026-12-15', '2026-12-18', false],
            'after' => ['2026-12-20', '2026-12-22', false],
            'overlaps beginning' => ['2026-12-09', '2026-12-11', true],
            'overlaps end' => ['2026-12-14', '2026-12-16', true],
            'inside stay' => ['2026-12-11', '2026-12-12', true],
            'contains stay' => ['2026-12-01', '2026-12-20', true],
            'identical' => ['2026-12-10', '2026-12-15', true],
        ];
    }

    #[DataProvider('scheduledIntervals')]
    public function test_real_repository_and_service_check_scheduled_overlap(string $start, string $end, bool $expected): void
    {
        $room = $this->room();
        $this->maintenance($room, 'SCHEDULED', $start, $end);

        $this->assertSame($expected, $this->check($room->id));
    }

    public static function maintenanceStates(): array
    {
        return [
            'in progress overlapping' => ['IN_PROGRESS', '2026-12-10', '2026-12-15', true],
            'in progress overdue' => ['IN_PROGRESS', '2026-01-01', '2026-01-02', true],
            'in progress future' => ['IN_PROGRESS', '2027-01-01', '2027-01-02', true],
            'in progress adjacent' => ['IN_PROGRESS', '2026-12-15', '2026-12-16', true],
            'completed overlapping' => ['COMPLETED', '2026-12-10', '2026-12-15', false],
            'cancelled overlapping' => ['CANCELLED', '2026-12-10', '2026-12-15', false],
            'completed malformed' => ['COMPLETED', '0000-00-00', '2026-02-31', false],
            'cancelled malformed' => ['CANCELLED', '2026-12-15', '2026-12-10', false],
        ];
    }

    #[DataProvider('maintenanceStates')]
    public function test_status_policy_does_not_automatically_expire_unfinished_work(string $status, string $start, string $end, bool $expected): void
    {
        $room = $this->room();
        $this->maintenance($room, $status, $start, $end);

        $this->assertSame($expected, $this->check($room->id));
    }

    public static function malformedSchedules(): array
    {
        return [
            'zero dates' => ['0000-00-00', '0000-00-00'],
            'zero month' => ['2026-00-01', '2026-01-02'],
            'calendar overflow' => ['2026-02-31', '2026-03-02'],
            'equal dates' => ['2026-01-01', '2026-01-01'],
            'reversed dates' => ['2026-01-02', '2026-01-01'],
            'outside mysql date range' => ['0999-01-01', '0999-01-02'],
            'empty date' => ['', '2026-01-02'],
        ];
    }

    #[DataProvider('malformedSchedules')]
    public function test_unresolved_malformed_dates_block_instead_of_being_normalized(string $start, string $end): void
    {
        $room = $this->room();
        $this->maintenance($room, 'SCHEDULED', $start, $end);

        $this->assertTrue($this->check($room->id));
    }

    public function test_repository_preserves_raw_dates_without_exposing_reason_or_loading_models(): void
    {
        $room = $this->room();
        $this->maintenance($room, 'SCHEDULED', '2026-02-31', '2026-03-02');
        $this->maintenance($room, 'COMPLETED');
        $this->maintenance($room, 'CANCELLED');
        $facts = $this->app->make(CatalogRepository::class)->getRoomMaintenanceFacts($room->id);

        $this->assertCount(1, $facts);
        $this->assertInstanceOf(stdClass::class, $facts->first());
        $this->assertSame('2026-02-31', $facts->first()->start_date);
        $this->assertObjectNotHasProperty('reason', $facts->first());
        $this->assertObjectNotHasProperty('room', $facts->first());
    }

    public function test_other_rooms_in_the_same_or_another_property_do_not_block(): void
    {
        $room = $this->room();
        $sibling = $this->room($room->property);
        $other = $this->room();
        $this->maintenance($sibling, 'SCHEDULED');
        $this->maintenance($other, 'IN_PROGRESS', '0000-00-00', '0000-00-00');

        $this->assertFalse($this->check($room->id));
        $this->assertTrue($this->check($sibling->id));
        $this->assertTrue($this->check($other->id));
    }

    public function test_any_blocker_is_enough_and_repeated_reads_do_not_cache_old_results(): void
    {
        $room = $this->room();
        $this->maintenance($room, 'COMPLETED');
        $this->maintenance($room, 'SCHEDULED', '2026-12-15', '2026-12-16');
        $id = $this->maintenance($room, 'SCHEDULED', '2026-12-12', '2026-12-13');
        $this->assertTrue($this->check($room->id));

        // Owned fixture changes are not a Maintenance workflow implementation.
        DB::table('maintenances')->where('id', $id)->update(['status' => 'COMPLETED']);
        $this->assertFalse($this->check($room->id));
        $this->maintenance($room, 'IN_PROGRESS', '2026-01-01', '2026-01-02');
        $this->assertTrue($this->check($room->id));
    }

    public static function invalidInputs(): array
    {
        return [
            'bad room and dates' => [0, null, null],
            'string room' => ['1', '2026-12-10', '2026-12-15'],
            'bad start' => [999999, '2026-02-31', '2026-12-15'],
            'bad end' => [999999, '2026-12-10', true],
            'empty interval' => [999999, '2026-12-10', '2026-12-10'],
            'reversed interval' => [999999, '2026-12-15', '2026-12-10'],
        ];
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_inputs_raise_before_any_room_lookup(mixed $id, mixed $start, mixed $end): void
    {
        $service = $this->app->make(RoomMaintenanceQueryService::class);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->expectException(InvalidArgumentException::class);
        try {
            $service->hasBlockingMaintenance($id, $start, $end);
        } finally {
            $queries = DB::getQueryLog();
            DB::disableQueryLog();
            $this->assertSame([], $queries);
        }
    }

    public function test_missing_room_is_an_explicit_error_instead_of_no_maintenance(): void
    {
        try {
            $this->check(999999);
            $this->fail('Expected missing room error.');
        } catch (ModelNotFoundException $error) {
            $this->assertSame(Room::class, $error->getModel());
            $this->assertSame([999999], $error->getIds());
        }
    }

    public function test_room_and_property_status_are_left_to_the_callers_availability_rules(): void
    {
        foreach ([['ACTIVE', 'ACTIVE'], ['MAINTENANCE', 'INACTIVE'], ['INACTIVE', 'BLOCKED'], ['ACTIVE', 'PENDING']] as [$roomStatus, $propertyStatus]) {
            $room = $this->room(status: $roomStatus);
            $room->property->update(['status' => $propertyStatus]);

            $this->assertFalse($this->check($room->id));
        }
    }

    public function test_query_is_read_only_and_preserves_data_and_transaction_level(): void
    {
        $room = $this->room();
        $this->maintenance($room, 'SCHEDULED');
        $this->maintenance($room, 'IN_PROGRESS', '2026-01-01', '2026-01-02');
        $tables = ['rooms', 'properties', 'room_types', 'maintenances', 'bookings'];
        $snapshot = static function () use ($tables): array {
            $result = [];
            foreach ($tables as $table) {
                $result[$table] = hash('sha256', DB::table($table)->orderBy('id')->get()->toJson());
            }

            return $result;
        };
        $before = $snapshot();
        $level = DB::transactionLevel();
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $this->assertTrue($this->check($room->id));
            $queries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
        }
        $this->assertNotEmpty($queries);
        foreach ($queries as $query) {
            $this->assertMatchesRegularExpression('/\A\s*select\b/i', $query['query']);
        }
        $this->assertSame($level, DB::transactionLevel());
        $this->assertSame($before, $snapshot());
    }

    public function test_database_failure_is_not_silently_returned_as_false(): void
    {
        $failure = new RuntimeException('Fixture repository failure');
        $repository = $this->createMock(CatalogRepository::class);
        $repository->expects($this->once())->method('roomExistsForMaintenanceCheck')->with(7)->willReturn(true);
        $repository->expects($this->once())->method('getRoomMaintenanceFacts')->with(7)->willThrowException($failure);
        $service = new RoomMaintenanceQueryService($repository, new RoomMaintenanceValidator());

        try {
            $service->hasBlockingMaintenance(7, '2026-12-10', '2026-12-15');
            $this->fail('Expected repository error.');
        } catch (RuntimeException $error) {
            $this->assertSame($failure, $error);
        }
    }

    public function test_calendar_overlap_does_not_depend_on_today_or_application_timezone(): void
    {
        $room = $this->room();
        $this->maintenance($room, 'SCHEDULED', '2000-02-28', '2000-03-02');
        $this->travelTo(CarbonImmutable::parse('2030-01-01T00:00:00Z'));
        config(['app.timezone' => 'Asia/Ho_Chi_Minh']);

        $this->assertTrue($this->app->make(RoomMaintenanceQueryService::class)
            ->hasBlockingMaintenance($room->id, '2000-02-29', '2000-03-01'));
    }
}
