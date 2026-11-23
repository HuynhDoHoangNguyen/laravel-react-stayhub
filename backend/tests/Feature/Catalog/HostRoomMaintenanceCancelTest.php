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

final class HostRoomMaintenanceCancelTest extends TestCase
{
    use RefreshDatabase;

    private const PERIOD = ['start_date' => '2026-12-12', 'end_date' => '2026-12-14'];

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
            || config('database.connections.sqlite.url')) {
            throw new RuntimeException('Room maintenance cancel tests require SQLite in-memory.');
        }

        parent::beforeRefreshingDatabase();
    }

    public function test_cancel_keeps_history_and_only_removes_its_own_uc51_block(): void
    {
        [$host, $room] = $this->fixture();
        $target = $this->schedule($room);
        $remaining = $this->schedule($room, ['start_date' => '2026-12-13', 'end_date' => '2026-12-16']);
        $uc51 = app(RoomMaintenanceQueryService::class);
        $this->assertTrue($uc51->hasBlockingMaintenance($room->id, '2026-12-12', '2026-12-13'));
        $this->assertTrue($uc51->hasBlockingMaintenance($room->id, '2026-12-13', '2026-12-14'));
        $expected = ['id' => $target] + self::PERIOD + ['reason' => 'Vệ sinh máy lạnh', 'status' => 'CANCELLED'];
        $url = $this->endpoint($target);

        $this->actingAs($host)->deleteJson($url)->assertOk()->assertExactJson(['data' => $expected]);
        $this->assertDatabaseHas('maintenances', $expected + ['room_id' => $room->id, 'created_at' => '2026-12-01 10:00:00']);
        $this->assertDatabaseHas('maintenances', ['id' => $remaining, 'status' => 'SCHEDULED']);
        $this->assertDatabaseHas('rooms', ['id' => $room->id, 'status' => 'ACTIVE']);
        $this->assertFalse($uc51->hasBlockingMaintenance($room->id, '2026-12-12', '2026-12-13'));
        $this->assertTrue($uc51->hasBlockingMaintenance($room->id, '2026-12-13', '2026-12-14'));
        $this->getJson('/api/host/rooms/'.$room->id.'/maintenances')->assertOk()
            ->assertJsonPath('meta.total', 2)->assertJsonPath('data.1', $expected);

        $updatedAt = DB::table('maintenances')->where('id', $target)->value('updated_at');
        $this->travel(1)->minutes();
        $this->deleteJson($url)->assertOk()->assertExactJson(['data' => $expected]);
        $this->assertDatabaseHas('maintenances', ['id' => $target, 'updated_at' => $updatedAt]);
        $this->assertDatabaseCount('maintenances', 2);
    }

    public function test_cancel_accepts_overdue_and_malformed_scheduled_ranges(): void
    {
        $this->travelTo(now(config('app.timezone'))->setDate(2026, 12, 22));
        [$host, $room] = $this->fixture();
        $this->actingAs($host);

        foreach (['2026-12-14', '0000-00-00'] as $end) {
            $target = $this->schedule($room, ['end_date' => $end]);
            $this->deleteJson($this->endpoint($target))->assertOk()
                ->assertJsonPath('data.end_date', $end)->assertJsonPath('data.status', 'CANCELLED');
            $this->assertDatabaseHas('maintenances', ['id' => $target, 'end_date' => $end, 'status' => 'CANCELLED']);
        }
    }

    public function test_cancel_rejects_non_scheduled_workflow_states(): void
    {
        [$host, $room] = $this->fixture();
        $this->actingAs($host);
        foreach (['IN_PROGRESS', 'COMPLETED'] as $state) {
            $target = $this->schedule($room, ['status' => $state]);
            $this->deleteJson($this->endpoint($target))->assertConflict()
                ->assertJsonPath('code', 'CATALOG_ROOM_MAINTENANCE_STATUS_CONFLICT');
            $this->assertDatabaseHas('maintenances', ['id' => $target, 'status' => $state]);
        }
    }

    public function test_cancel_enforces_access_and_rejects_extra_input(): void
    {
        [$host, $room] = $this->fixture();
        $target = $this->schedule($room);
        $url = $this->endpoint($target);
        $this->deleteJson($url)->assertUnauthorized();
        foreach (['CUSTOMER', 'ADMIN'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->deleteJson($url)->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['role' => 'HOST', 'status' => 'INACTIVE']))->deleteJson($url)->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'HOST']))->deleteJson($url)->assertNotFound()
            ->assertExactJson(['message' => 'Không tìm thấy lịch bảo trì.']);
        $this->actingAs($host)->deleteJson($this->endpoint('0'.$target))->assertNotFound();
        $this->deleteJson($this->endpoint(999999))->assertNotFound();
        $this->deleteJson($url.'?room_id='.$room->id, ['status' => 'COMPLETED'])
            ->assertUnprocessable()->assertJsonValidationErrors(['room_id', 'status']);

        $hash = hash('sha256', 'Fixture terms');
        $agreementId = DB::table('partner_agreements')->insertGetId([
            'version' => 'uc23-cancel-approval', 'title' => 'Fixture agreement', 'terms_body' => 'Fixture terms',
            'content_sha256' => $hash, 'effective_at' => now(),
        ]);
        DB::table('host_agreement_acceptances')->insert([
            'host_id' => $host->id, 'partner_agreement_id' => $agreementId, 'accepted_content_sha256' => $hash,
            'accepted_at' => now(), 'method' => 'REGISTRATION_PENDING',
        ]);
        $this->deleteJson($url)->assertForbidden()->assertJsonPath('code', 'HOST_APPROVAL_PENDING');
        $this->assertDatabaseHas('maintenances', ['id' => $target, 'status' => 'SCHEDULED']);
    }

    private function fixture(): array
    {
        $host = User::factory()->create(['role' => 'HOST']);
        $property = Property::factory()->create(['host_id' => $host->id]);
        $type = RoomType::factory()->create(['property_id' => $property->id]);
        return [$host, Room::factory()->create(['property_id' => $property->id, 'room_type_id' => $type->id])];
    }

    private function schedule(Room $room, array $overrides = []): int
    {
        return DB::table('maintenances')->insertGetId($overrides + self::PERIOD + [
            'room_id' => $room->id,
            'status' => 'SCHEDULED',
            'reason' => 'Vệ sinh máy lạnh',
            'created_at' => '2026-12-01 10:00:00',
        ]);
    }

    private function endpoint(int|string $id): string
    {
        return '/api/host/maintenances/'.$id;
    }
}
