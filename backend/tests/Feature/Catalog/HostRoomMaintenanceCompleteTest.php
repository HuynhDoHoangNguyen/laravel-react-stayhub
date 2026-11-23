<?php

namespace Tests\Feature\Catalog;

use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Services\Catalog\RoomMaintenanceQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

final class HostRoomMaintenanceCompleteTest extends TestCase
{
    use RefreshDatabase;

    private const COMPLETE = ['status' => 'COMPLETED'];

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
            || config('database.connections.sqlite.url')) {
            throw new RuntimeException('Room maintenance completion tests require SQLite in-memory.');
        }

        parent::beforeRefreshingDatabase();
    }

    public function test_completes_overdue_work_preserving_history_and_other_room_blocks(): void
    {
        $this->travelTo(now(config('app.timezone'))->setDate(2026, 12, 22)->setTime(12, 0));
        [$host, $room] = $this->fixture();
        $id = $this->schedule($room, 'IN_PROGRESS', '2026-12-10', '2026-12-12');
        $this->schedule($room, 'SCHEDULED', '2026-12-28', '2026-12-30');
        $uc51 = app(RoomMaintenanceQueryService::class);
        $this->assertTrue($uc51->hasBlockingMaintenance($room->id, '2026-12-25', '2026-12-26'));
        $expected = [
            'id' => $id, 'start_date' => '2026-12-10', 'end_date' => '2026-12-12',
            'reason' => 'Sửa điều hòa', 'status' => 'COMPLETED',
        ];

        $this->actingAs($host)->patchJson($this->endpoint($id), self::COMPLETE)
            ->assertOk()->assertExactJson(['data' => $expected]);
        $this->assertDatabaseHas('maintenances', $expected + ['room_id' => $room->id]);
        $this->assertDatabaseHas('rooms', ['id' => $room->id, 'status' => 'ACTIVE']);
        $this->getJson('/api/host/rooms/'.$room->id.'/maintenances')->assertOk()
            ->assertJsonPath('meta.total', 2)->assertJsonPath('data.1', $expected);
        $this->assertFalse($uc51->hasBlockingMaintenance($room->id, '2026-12-25', '2026-12-26'));
        $this->assertTrue($uc51->hasBlockingMaintenance($room->id, '2026-12-28', '2026-12-29'));

        $updatedAt = DB::table('maintenances')->where('id', $id)->value('updated_at');
        $this->travel(1)->minutes();
        $this->patchJson($this->endpoint($id), self::COMPLETE)->assertOk()->assertExactJson(['data' => $expected]);
        $this->assertDatabaseHas('maintenances', ['id' => $id, 'updated_at' => $updatedAt]);
    }

    public function test_completion_checks_ownership_and_rejects_invalid_transitions_and_targets(): void
    {
        [$host, $room] = $this->fixture();
        $id = $this->schedule($room, 'IN_PROGRESS');
        $url = $this->endpoint($id);
        $this->patchJson($url, self::COMPLETE)->assertUnauthorized();
        $this->actingAs(User::factory()->customer()->create())->patchJson($url, self::COMPLETE)->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'HOST']))->patchJson($url, self::COMPLETE)->assertNotFound();
        $this->actingAs($host)->patchJson($this->endpoint('0'.$id), self::COMPLETE)->assertNotFound();
        foreach (['SCHEDULED', 'CANCELLED', ' COMPLETED ', ['COMPLETED']] as $status) {
            $this->patchJson($url, ['status' => $status])->assertUnprocessable()->assertJsonValidationErrors('status');
        }
        $this->patchJson($url.'?page=2', self::COMPLETE + ['room_id' => $room->id])
            ->assertUnprocessable()->assertJsonValidationErrors(['room_id', 'page']);
        foreach (['SCHEDULED', 'CANCELLED'] as $status) {
            $other = $this->schedule($room, $status);
            $this->patchJson($this->endpoint($other), self::COMPLETE)
                ->assertConflict()->assertJsonPath('code', 'CATALOG_ROOM_MAINTENANCE_STATUS_CONFLICT');
            $this->assertDatabaseHas('maintenances', ['id' => $other, 'status' => $status]);
        }
        $this->assertDatabaseHas('maintenances', ['id' => $id, 'status' => 'IN_PROGRESS']);
    }

    public function test_start_and_complete_through_the_same_status_endpoint(): void
    {
        $this->travelTo(now(config('app.timezone'))->setDate(2026, 12, 22)->setTime(12, 0));
        [$host, $room] = $this->fixture();
        $id = $this->schedule($room, 'SCHEDULED');
        $url = $this->endpoint($id);
        $this->actingAs($host)->patchJson($url, ['status' => 'IN_PROGRESS'])->assertOk()
            ->assertJsonPath('data.status', 'IN_PROGRESS');
        $this->patchJson($url, self::COMPLETE)->assertOk()->assertJsonPath('data.status', 'COMPLETED');
        $this->patchJson($url, ['status' => 'IN_PROGRESS'])->assertConflict()
            ->assertJsonPath('code', 'CATALOG_ROOM_MAINTENANCE_STATUS_CONFLICT');
        $this->assertDatabaseHas('maintenances', ['id' => $id, 'status' => 'COMPLETED']);
    }

    private function fixture(): array
    {
        $host = User::factory()->create(['role' => 'HOST']);
        $property = Property::factory()->create(['host_id' => $host->id]);
        $type = RoomType::factory()->create(['property_id' => $property->id]);

        return [$host, Room::factory()->create(['property_id' => $property->id, 'room_type_id' => $type->id])];
    }

    private function schedule(Room $room, string $status, string $start = '2026-12-22', string $end = '2026-12-24'): int
    {
        return DB::table('maintenances')->insertGetId([
            'room_id' => $room->id, 'status' => $status, 'start_date' => $start, 'end_date' => $end, 'reason' => 'Sửa điều hòa',
        ]);
    }

    private function endpoint(int|string $id): string
    {
        return '/api/host/maintenances/'.$id.'/status';
    }
}
