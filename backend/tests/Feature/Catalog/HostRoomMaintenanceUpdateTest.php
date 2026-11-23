<?php

namespace Tests\Feature\Catalog;

use App\Models\Booking;
use App\Models\Maintenance;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

final class HostRoomMaintenanceUpdateTest extends TestCase
{
    use RefreshDatabase;

    private const PERIOD = ['start_date' => '2026-12-12', 'end_date' => '2026-12-14'];

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
            || config('database.connections.sqlite.url')) {
            throw new RuntimeException('Room maintenance update tests require SQLite in-memory.');
        }

        parent::beforeRefreshingDatabase();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now(config('app.timezone'))->setDate(2026, 12, 10)->setTime(12, 0));
    }

    public function test_updates_same_schedule_ignoring_itself_and_does_not_rewrite_repeated_payload(): void
    {
        [$host, $room] = $this->fixture();
        $schedule = $this->schedule($room);
        $url = $this->endpoint($schedule->id);
        $payload = ['start_date' => '2026-12-13', 'end_date' => '2026-12-15', 'reason' => 'Kiểm tra máy lạnh'];
        $expected = ['id' => $schedule->id] + $payload + ['status' => 'SCHEDULED'];

        $this->actingAs($host)->putJson($url, $payload)->assertOk()->assertExactJson(['data' => $expected]);
        $this->assertDatabaseHas('maintenances', $expected + ['room_id' => $room->id]);
        $this->getJson('/api/host/rooms/'.$room->id.'/maintenances')->assertOk()->assertJsonPath('data.0', $expected);
        $this->assertDatabaseHas('rooms', ['id' => $room->id, 'status' => 'ACTIVE']);
        $updatedAt = $schedule->fresh()->getRawOriginal('updated_at');

        $this->travel(1)->minutes();
        $this->putJson($url, $payload)->assertOk()->assertExactJson(['data' => $expected]);
        $this->assertDatabaseHas('maintenances', ['id' => $schedule->id, 'updated_at' => $updatedAt]);
        $this->assertDatabaseCount('maintenances', 1);
    }

    public function test_conflicts_are_distinct_and_leaving_and_entering_on_the_boundary_is_valid(): void
    {
        [$host, $room] = $this->fixture();
        $target = $this->schedule($room);
        $before = $target->fresh()->getAttributes();
        $url = $this->endpoint($target->id);
        Booking::factory()->forRoom($room)->confirmed()->create([
            'check_in_date' => '2026-12-14', 'check_out_date' => '2026-12-16', 'number_of_nights' => 2,
        ]);
        $this->schedule($room, ['start_date' => '2026-12-18', 'end_date' => '2026-12-20']);

        $this->actingAs($host)->putJson($url, ['start_date' => '2026-12-15', 'end_date' => '2026-12-19'])
            ->assertConflict()->assertJsonPath('code', 'CATALOG_ROOM_BOOKING_CONFLICT');
        $this->putJson($url, ['start_date' => '2026-12-16', 'end_date' => '2026-12-19'])
            ->assertConflict()->assertJsonPath('code', 'CATALOG_ROOM_MAINTENANCE_CONFLICT');
        $this->assertSame($before, $target->fresh()->getAttributes());
        $adjacent = ['start_date' => '2026-12-16', 'end_date' => '2026-12-18'];
        $this->putJson($url, $adjacent + ['reason' => null])->assertOk()
            ->assertJsonPath('data.start_date', $adjacent['start_date'])->assertJsonPath('data.reason', null);
        $this->assertDatabaseHas('maintenances', ['id' => $target->id, 'room_id' => $room->id, 'reason' => null] + $adjacent);
    }

    public function test_validates_dates_and_accepts_correcting_a_legacy_range(): void
    {
        [$host, $room] = $this->fixture();
        $target = $this->schedule($room, ['start_date' => '2026-12-09', 'end_date' => '2026-12-09']);
        $url = $this->endpoint($target->id);
        $this->actingAs($host)->putJson($url, ['start_date' => '2026-12-09', 'end_date' => '2026-12-11'])
            ->assertUnprocessable()->assertJsonValidationErrors('start_date');
        $this->putJson($url, ['start_date' => '2026-12-15', 'end_date' => '2026-12-14'])
            ->assertUnprocessable()->assertJsonValidationErrors('end_date');
        $this->putJson($url.'?page=2', self::PERIOD + ['room_id' => $room->id, 'status' => 'COMPLETED'])
            ->assertUnprocessable()->assertJsonValidationErrors(['page', 'room_id', 'status']);
        $this->assertDatabaseHas('maintenances', ['id' => $target->id, 'start_date' => '2026-12-09', 'end_date' => '2026-12-09']);
        $this->putJson($url, self::PERIOD)->assertOk()->assertJsonPath('data.status', 'SCHEDULED');
    }

    public function test_rejects_any_status_other_than_scheduled(): void
    {
        [$host, $room] = $this->fixture();
        $this->actingAs($host);
        foreach (['IN_PROGRESS', 'COMPLETED', 'CANCELLED'] as $status) {
            $schedule = $this->schedule($room, ['status' => $status]);
            $this->putJson($this->endpoint($schedule->id), self::PERIOD + ['reason' => 'Attempted change'])
                ->assertConflict()->assertJsonPath('code', 'CATALOG_ROOM_MAINTENANCE_STATUS_CONFLICT');
            $this->assertSame($status, $schedule->fresh()->status->value);
            $this->assertDatabaseHas('maintenances', ['id' => $schedule->id, 'reason' => 'Lý do cũ'] + self::PERIOD);
        }
    }

    public function test_guards_authentication_role_account_and_ownership(): void
    {
        [$host, $room] = $this->fixture();
        $target = $this->schedule($room);
        $url = $this->endpoint($target->id);
        $this->putJson($url, self::PERIOD)->assertUnauthorized();
        foreach (['CUSTOMER', 'ADMIN'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->putJson($url, self::PERIOD)->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['role' => 'HOST', 'status' => 'INACTIVE']))
            ->putJson($url, self::PERIOD)->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'HOST']))
            ->putJson($url, self::PERIOD)->assertNotFound()->assertExactJson(['message' => 'Không tìm thấy lịch bảo trì.']);
        $this->actingAs($host)->putJson($this->endpoint('0'.$target->id), self::PERIOD)->assertNotFound();
        $this->putJson($this->endpoint(999999), self::PERIOD)->assertNotFound();
        $this->assertDatabaseHas('maintenances', ['id' => $target->id, 'reason' => 'Lý do cũ'] + self::PERIOD);
    }

    private function fixture(): array
    {
        $host = User::factory()->create(['role' => 'HOST']);
        $property = Property::factory()->create(['host_id' => $host->id]);
        $type = RoomType::factory()->create(['property_id' => $property->id]);
        return [$host, Room::factory()->create(['property_id' => $property->id, 'room_type_id' => $type->id])];
    }

    private function schedule(Room $room, array $overrides = []): Maintenance
    {
        $id = DB::table('maintenances')->insertGetId($overrides + self::PERIOD + [
            'room_id' => $room->id, 'status' => 'SCHEDULED', 'reason' => 'Lý do cũ',
        ]);
        return Maintenance::query()->findOrFail($id);
    }

    private function endpoint(int|string $id): string
    {
        return '/api/host/maintenances/'.$id;
    }
}
