<?php

namespace Tests\Feature\Catalog;

use App\DTOs\Catalog\Requests\CreateHostRoomMaintenanceRequest;
use App\Exceptions\AppException;
use App\Models\Maintenance;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use App\Repositories\Catalog\CatalogRepository;
use App\Services\Catalog\HostRoomMaintenanceService;
use App\Services\Catalog\RoomMaintenanceQueryService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

final class HostRoomMaintenanceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
            || config('database.connections.sqlite.url')) {
            throw new RuntimeException('Room maintenance service tests require SQLite in-memory.');
        }

        parent::beforeRefreshingDatabase();
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'UTC']);
        $this->travelTo(CarbonImmutable::parse('2026-12-10T12:00:00Z'));
    }

    public function test_writes_date_only_scheduled_record_seen_by_uc51_and_ignores_facts_on_another_room(): void
    {
        [$host, $room] = $this->fixture();
        [, $other] = $this->fixture();
        $this->booking($other, 'PENDING', '2026-12-12', '2026-12-14');
        $this->maintenance($other, 'IN_PROGRESS', '2026-12-09', '2026-12-11');
        $before = $room->fresh()->getAttributes();
        $bookingBefore = DB::table('bookings')->first();

        $result = app(HostRoomMaintenanceService::class)->create($host, $this->requestFor($room, 'Bảo trì điều hòa'));

        $expected = [
            'id' => $result->id, 'start_date' => '2026-12-12', 'end_date' => '2026-12-14',
            'reason' => 'Bảo trì điều hòa', 'status' => 'SCHEDULED',
        ];
        $this->assertSame($expected, $result->jsonSerialize());
        $this->assertDatabaseHas('maintenances', $expected + ['room_id' => $room->id]);
        $this->assertSame($before, $room->fresh()->getAttributes());
        $this->assertEquals($bookingBefore, DB::table('bookings')->first());
        $uc51 = app(RoomMaintenanceQueryService::class);
        $this->assertTrue($uc51->hasBlockingMaintenance($room->id, '2026-12-12', '2026-12-14'));
        $this->assertFalse($uc51->hasBlockingMaintenance($room->id, '2026-12-14', '2026-12-15'));
    }

    public static function bookingCases(): array
    {
        return [
            'pending overlap' => ['PENDING', '2026-12-11', '2026-12-13', true],
            'confirmed same period' => ['CONFIRMED', '2026-12-12', '2026-12-14', true],
            'confirmed covers the period' => ['CONFIRMED', '2026-12-11', '2026-12-15', true],
            'booking ends at maintenance start' => ['CONFIRMED', '2026-12-10', '2026-12-12', false],
            'booking starts at maintenance end' => ['PENDING', '2026-12-14', '2026-12-16', false],
            'checked-in overlaps' => ['CHECKED_IN', '2026-12-09', '2026-12-13', true],
            'valid checked-in ends at maintenance start' => ['CHECKED_IN', '2026-12-09', '2026-12-12', false],
            'checked-in checkout due today' => ['CHECKED_IN', '2026-12-08', '2026-12-10', true],
            'checked-in overdue even without overlap' => ['CHECKED_IN', '2026-12-07', '2026-12-09', true],
            'holding booking with malformed date' => ['PENDING', '0000-00-00', '2026-12-09', true],
            'holding booking with reversed dates' => ['CONFIRMED', '2026-12-15', '2026-12-10', true],
            'completed with malformed date' => ['COMPLETED', '0000-00-00', '2026-12-10', false],
            'cancelled overlap' => ['CANCELLED', '2026-12-12', '2026-12-14', false],
            'rejected overlap' => ['REJECTED', '2026-12-12', '2026-12-14', false],
        ];
    }

    #[DataProvider('bookingCases')]
    public function test_booking_conflict_matrix(string $status, string $start, string $end, bool $blocked): void
    {
        [$host, $room] = $this->fixture();
        $this->booking($room, $status, $start, $end);
        $before = DB::table('bookings')->first();
        $this->assertOutcome($host, $room, $blocked ? 'CATALOG_ROOM_BOOKING_CONFLICT' : null);
        $this->assertDatabaseCount('maintenances', $blocked ? 0 : 1);
        $this->assertEquals($before, DB::table('bookings')->first());
    }

    public static function maintenanceCases(): array
    {
        return [
            'scheduled overlap' => ['SCHEDULED', '2026-12-11', '2026-12-13', true],
            'scheduled touches start' => ['SCHEDULED', '2026-12-10', '2026-12-12', false],
            'scheduled touches end' => ['SCHEDULED', '2026-12-14', '2026-12-15', false],
            'scheduled invalid date' => ['SCHEDULED', '2026-02-30', '2026-12-07', true],
            'overdue in progress' => ['IN_PROGRESS', '2026-12-04', '2026-12-07', true],
            'completed overlap' => ['COMPLETED', '2026-12-12', '2026-12-14', false],
            'cancelled overlap' => ['CANCELLED', '2026-12-12', '2026-12-14', false],
        ];
    }

    #[DataProvider('maintenanceCases')]
    public function test_maintenance_conflict_matrix(string $status, string $start, string $end, bool $blocked): void
    {
        [$host, $room] = $this->fixture();
        $this->maintenance($room, $status, $start, $end);
        $this->assertOutcome($host, $room, $blocked ? 'CATALOG_ROOM_MAINTENANCE_CONFLICT' : null);
        $this->assertDatabaseCount('maintenances', $blocked ? 1 : 2);
    }

    public function test_booking_conflict_takes_priority_when_both_conflict(): void
    {
        [$host, $room] = $this->fixture();
        $this->booking($room, 'PENDING', '2026-12-12', '2026-12-14');
        $this->maintenance($room, 'SCHEDULED', '2026-12-12', '2026-12-15');
        $this->assertOutcome($host, $room, 'CATALOG_ROOM_BOOKING_CONFLICT');
        $this->assertDatabaseCount('maintenances', 1);
    }

    public function test_only_the_owner_host_can_create(): void
    {
        [$owner, $room] = $this->fixture();
        $service = app(HostRoomMaintenanceService::class);
        $this->assertNull($service->create(User::factory()->create(['role' => 'HOST']), $this->requestFor($room)));
        $this->assertNull($service->create($owner, new CreateHostRoomMaintenanceRequest(99999, '2026-12-12', '2026-12-14')));
        $this->assertNull($service->create($owner, new CreateHostRoomMaintenanceRequest(0, '2026-12-12', '2026-12-14')));
        try {
            $service->create(User::factory()->create(['role' => 'CUSTOMER']), $this->requestFor($room));
            $this->fail('A customer should be denied.');
        } catch (AppException $error) {
            $this->assertSame(403, $error->status);
        }
        $this->assertDatabaseCount('maintenances', 0);
    }

    public static function invalidPeriods(): array
    {
        return [
            'bad start' => ['2026-02-30', '2026-12-14', null, 'start_date'],
            'time on end' => ['2026-12-12', '2026-12-14T00:00:00Z', null, 'end_date'],
            'same dates' => ['2026-12-12', '2026-12-12', null, 'end_date'],
            'reversed' => ['2026-12-14', '2026-12-12', null, 'end_date'],
            'past start' => ['2026-12-09', '2026-12-14', null, 'start_date'],
            'reason above TEXT byte limit' => ['2026-12-12', '2026-12-14', str_repeat('a', 65536), 'reason'],
        ];
    }

    #[DataProvider('invalidPeriods')]
    public function test_rejects_invalid_periods_and_reason_without_writes(string $start, string $end, ?string $reason, string $field): void
    {
        [$host, $room] = $this->fixture();
        try {
            app(HostRoomMaintenanceService::class)->create($host, new CreateHostRoomMaintenanceRequest($room->id, $start, $end, $reason));
            $this->fail('Expected field validation.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey($field, $error->errors());
        }
        $this->assertDatabaseCount('maintenances', 0);
    }

    public function test_today_uses_application_timezone(): void
    {
        [$host, $room] = $this->fixture();
        config(['app.timezone' => 'Asia/Ho_Chi_Minh']);
        $this->travelTo(CarbonImmutable::parse('2026-12-10T18:00:00Z'));
        $service = app(HostRoomMaintenanceService::class);

        try {
            $service->create($host, new CreateHostRoomMaintenanceRequest($room->id, '2026-12-10', '2026-12-12'));
            $this->fail('Expected validation on the previous application day.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('start_date', $error->errors());
        }
        $result = $service->create($host, new CreateHostRoomMaintenanceRequest($room->id, '2026-12-11', '2026-12-12'));
        $this->assertSame('2026-12-11', $result->startDate);
        $this->assertDatabaseCount('maintenances', 1);
    }

    public function test_persistence_failure_after_insert_rolls_back(): void
    {
        [$host, $room] = $this->fixture();
        $this->app->instance(CatalogRepository::class, new class extends CatalogRepository {
            public function createHostRoomMaintenance(Room $room, CreateHostRoomMaintenanceRequest $input): Maintenance
            {
                parent::createHostRoomMaintenance($room, $input);
                throw new RuntimeException('Injected failure after maintenance insert.');
            }
        });
        Log::spy();
        try {
            app(HostRoomMaintenanceService::class)->create($host, $this->requestFor($room));
            $this->fail('Expected persistence failure.');
        } catch (RuntimeException $error) {
            $this->assertSame('Injected failure after maintenance insert.', $error->getMessage());
        }
        $this->assertDatabaseCount('maintenances', 0);
        Log::shouldNotHaveReceived('info');
    }

    #[DataProvider('maintenanceStartCases')]
    public function test_start_checks_retained_bookings_dates_and_other_maintenance(
        string $today,
        ?array $booking,
        ?array $otherSchedule,
        ?string $conflict,
        string $start = '2026-12-12',
        string $end = '2026-12-14',
    ): void {
        $this->travelTo(CarbonImmutable::parse($today.'T12:00:00Z'));
        [$host, $room] = $this->fixture();
        $id = $this->maintenance($room, 'SCHEDULED', $start, $end);
        if ($booking !== null) {
            $this->booking($room, ...$booking);
        }
        if ($otherSchedule !== null) {
            $this->maintenance($room, ...$otherSchedule);
        }

        $service = app(HostRoomMaintenanceService::class);
        if ($conflict !== null) {
            try {
                $service->start($host, $id);
                $this->fail('Expected the current facts to block starting maintenance.');
            } catch (AppException $error) {
                $this->assertSame($conflict, $error->errorCode);
                $this->assertSame(409, $error->status);
            }
            $this->assertDatabaseHas('maintenances', ['id' => $id, 'status' => 'SCHEDULED']);

            return;
        }

        $result = $service->start($host, $id);
        $this->assertSame('IN_PROGRESS', $result->status);
        $this->assertDatabaseHas('maintenances', ['id' => $id, 'status' => 'IN_PROGRESS', 'start_date' => $start, 'end_date' => $end]);
        $this->assertTrue(app(RoomMaintenanceQueryService::class)->hasBlockingMaintenance($room->id, '2026-12-30', '2026-12-31'));
    }

    public static function maintenanceStartCases(): array
    {
        $today = '2026-12-12';
        $status = 'CATALOG_ROOM_MAINTENANCE_STATUS_CONFLICT';
        $booking = 'CATALOG_ROOM_BOOKING_CONFLICT';
        $maintenance = 'CATALOG_ROOM_MAINTENANCE_CONFLICT';

        return [
            'start boundary' => [$today, null, null, null],
            'before start' => ['2026-12-11', null, null, $status],
            'at end' => ['2026-12-14', null, null, $status],
            'malformed target' => [$today, null, null, $status, $today, $today],
            'in house despite overdue checkout' => [$today, ['CHECKED_IN', '2026-12-08', '2026-12-10'], null, $booking],
            'in house despite future booking dates' => [$today, ['CHECKED_IN', '2026-12-20', '2026-12-22'], null, $booking],
            'future pending beyond maintenance' => [$today, ['PENDING', '2026-12-20', '2026-12-22'], null, $booking],
            'future confirmed beyond maintenance' => [$today, ['CONFIRMED', '2026-12-20', '2026-12-22'], null, $booking],
            'confirmed due to check out today' => [$today, ['CONFIRMED', '2026-12-10', $today], null, null],
            'malformed holding booking' => [$today, ['CONFIRMED', '2026-12-10', '2026-12-09'], null, $booking],
            'terminal booking' => [$today, ['CANCELLED', '2026-12-13', '2026-12-14'], null, null],
            'other overdue maintenance' => [$today, null, ['IN_PROGRESS', '2026-12-08', '2026-12-10'], $maintenance],
            'overlapping scheduled maintenance' => [$today, null, ['SCHEDULED', '2026-12-13', '2026-12-16'], $maintenance],
            'adjacent scheduled maintenance' => [$today, null, ['SCHEDULED', '2026-12-14', '2026-12-16'], null],
            'invalid other scheduled maintenance' => [$today, null, ['SCHEDULED', '2026-12-24', '2026-12-24'], $maintenance],
        ];
    }

    public function test_start_rechecks_ownership_and_does_not_write_when_repeated_after_the_window(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-12-12T12:00:00Z'));
        [$host, $room] = $this->fixture();
        [$otherHost] = $this->fixture();
        $id = $this->maintenance($room, 'SCHEDULED', '2026-12-12', '2026-12-14');
        $service = app(HostRoomMaintenanceService::class);
        $this->assertNull($service->start($otherHost, $id));
        $this->assertDatabaseHas('maintenances', ['id' => $id, 'status' => 'SCHEDULED']);
        $first = $service->start($host, $id);
        $updatedAt = DB::table('maintenances')->where('id', $id)->value('updated_at');
        $this->travel(3)->days();
        $this->assertSame($first->jsonSerialize(), $service->start($host, $id)->jsonSerialize());
        $this->assertDatabaseHas('maintenances', ['id' => $id, 'updated_at' => $updatedAt]);
    }

    private function assertOutcome(User $host, Room $room, ?string $conflict): void
    {
        try {
            $result = app(HostRoomMaintenanceService::class)->create($host, $this->requestFor($room));
            $this->assertNull($conflict, 'Expected an AppException for this case.');
            $this->assertSame('SCHEDULED', $result->status);
        } catch (AppException $error) {
            $this->assertSame($conflict, $error->errorCode);
            $this->assertSame(409, $error->status);
        }
    }

    private function requestFor(Room $room, ?string $reason = null): CreateHostRoomMaintenanceRequest
    {
        return new CreateHostRoomMaintenanceRequest($room->id, '2026-12-12', '2026-12-14', $reason);
    }

    /** @return array{User, Room} */
    private function fixture(): array
    {
        $host = User::factory()->create(['role' => 'HOST']);
        $property = Property::create([
            'host_id' => $host->id, 'name' => 'Maintenance fixture', 'type' => 'HOTEL',
            'address' => 'Fixture address', 'status' => 'ACTIVE',
        ]);
        $type = $property->roomTypes()->create(['name' => 'Standard']);
        $room = $property->rooms()->create([
            'room_type_id' => $type->id, 'room_number' => '101', 'name' => 'Fixture room',
            'capacity' => 2, 'price_per_night' => '100.00', 'status' => 'ACTIVE',
        ]);

        return [$host, $room];
    }

    private function booking(Room $room, string $status, string $start, string $end): void
    {
        DB::table('bookings')->insert([
            'booking_code' => 'UC23-'.Str::uuid(), 'customer_id' => User::factory()->create(['role' => 'CUSTOMER'])->id,
            'room_id' => $room->id, 'check_in_date' => $start, 'check_out_date' => $end,
            'guest_count' => 2, 'number_of_nights' => 2, 'price_per_night' => '100.00', 'room_total' => '200.00',
            'status' => $status,
        ]);
    }

    private function maintenance(Room $room, string $status, string $start, string $end): int
    {
        return DB::table('maintenances')->insertGetId([
            'room_id' => $room->id, 'start_date' => $start, 'end_date' => $end, 'status' => $status,
        ]);
    }
}
