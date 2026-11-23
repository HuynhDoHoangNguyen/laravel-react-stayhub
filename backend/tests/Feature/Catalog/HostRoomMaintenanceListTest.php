<?php

namespace Tests\Feature\Catalog;

use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

final class HostRoomMaintenanceListTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
            || config('database.connections.sqlite.url')) {
            throw new RuntimeException('Room maintenance list tests require SQLite in-memory.');
        }

        parent::beforeRefreshingDatabase();
    }

    public function test_lists_all_statuses_for_the_owned_room_with_a_limited_projection(): void
    {
        $host = User::factory()->create(['role' => 'HOST']);
        $room = $this->room($host);
        $otherRoom = $this->room($host);
        $statuses = ['SCHEDULED', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED'];
        $expected = [];
        foreach ($statuses as $index => $status) {
            $day = sprintf('2026-12-%02d', $index + 1);
            $reason = $status === 'CANCELLED' ? null : 'Vệ sinh máy lạnh';
            $id = $this->schedule($room, $day, $status, $reason);
            $expected[] = ['id' => $id, 'start_date' => $day, 'end_date' => '2026-12-31', 'reason' => $reason, 'status' => $status];
        }
        $this->schedule($otherRoom, '2026-12-30', 'SCHEDULED');

        $this->actingAs($host)->getJson($this->endpoint($room->id))->assertOk()
            ->assertJsonPath('data', array_reverse($expected))
            ->assertJsonPath('meta.current_page', 1)->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.total', 4)->assertJsonPath('links.next', null);
        $this->assertDatabaseCount('maintenances', 5);
    }

    public function test_paginates_in_date_and_id_order_and_returns_empty_out_of_range_pages(): void
    {
        $host = User::factory()->create(['role' => 'HOST']);
        $room = $this->room($host);
        $ids = [];
        for ($i = 0; $i < 16; $i++) {
            $ids[] = $this->schedule($room, '2026-12-01', 'SCHEDULED');
        }
        $newest = $this->schedule($room, '2026-12-03', 'COMPLETED');
        $url = $this->endpoint($room->id);
        $first = $this->actingAs($host)->getJson($url)->assertOk()->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.total', 17)->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('links.prev', null);
        $this->assertSame([$newest, ...array_slice(array_reverse($ids), 0, 14)], array_column($first->json('data'), 'id'));
        $this->assertStringEndsWith('?page=2', $first->json('links.next'));
        $this->getJson($url.'?page=2')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.current_page', 2)->assertJsonPath('data.0.id', $ids[1])
            ->assertJsonPath('data.1.id', $ids[0])->assertJsonPath('links.next', null);
        $this->getJson($url.'?page=3')->assertOk()->assertJsonPath('data', [])
            ->assertJsonPath('meta.current_page', 3)->assertJsonPath('meta.last_page', 2);
    }

    public function test_empty_room_has_an_empty_page_and_legacy_date_remains_readable(): void
    {
        $host = User::factory()->create(['role' => 'HOST']);
        $room = $this->room($host);
        $url = $this->endpoint($room->id);

        $this->actingAs($host)->getJson($url)->assertOk()->assertJsonPath('data', [])
            ->assertJsonPath('meta.total', 0)->assertJsonPath('meta.last_page', 1);
        $this->schedule($room, '0000-00-00', 'COMPLETED');
        $this->getJson($url)->assertOk()->assertJsonPath('data.0.start_date', '0000-00-00');
        $this->assertDatabaseHas('maintenances', ['room_id' => $room->id, 'start_date' => '0000-00-00']);
    }

    public function test_guards_session_role_account_and_room_ownership(): void
    {
        $owner = User::factory()->create(['role' => 'HOST']);
        $room = $this->room($owner);
        $url = $this->endpoint($room->id);
        $this->schedule($room, '2026-12-02', 'IN_PROGRESS');

        $this->getJson($url)->assertUnauthorized();
        foreach (['CUSTOMER', 'ADMIN'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->getJson($url)->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['role' => 'HOST', 'status' => 'INACTIVE']))
            ->getJson($url)->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'HOST']))->getJson($url)
            ->assertNotFound()->assertExactJson(['message' => 'Không tìm thấy phòng.']);
        $this->actingAs($owner)->getJson($this->endpoint(99999))->assertNotFound();
        $this->getJson($this->endpoint('0'.$room->id))->assertNotFound();
    }

    public function test_host_pending_approval_cannot_list_maintenance_for_an_owned_room(): void
    {
        $host = User::factory()->create(['role' => 'HOST']);
        $room = $this->room($host);
        $this->schedule($room, '2026-12-02', 'SCHEDULED');
        $hash = hash('sha256', 'Fixture terms');
        $agreementId = DB::table('partner_agreements')->insertGetId([
            'version' => 'uc23-approval',
            'title' => 'UC23 fixture agreement',
            'terms_body' => 'Fixture terms',
            'content_sha256' => $hash,
            'effective_at' => now(),
        ]);
        DB::table('host_agreement_acceptances')->insert([
            'host_id' => $host->id,
            'partner_agreement_id' => $agreementId,
            'accepted_content_sha256' => $hash,
            'accepted_at' => now(),
            'method' => 'REGISTRATION_PENDING',
        ]);

        $this->actingAs($host)->getJson($this->endpoint($room->id))->assertForbidden()
            ->assertJsonPath('code', 'HOST_APPROVAL_PENDING')->assertJsonMissingPath('data');
    }

    public function test_rejects_invalid_pages_and_unapproved_query_parameters(): void
    {
        $owner = User::factory()->create(['role' => 'HOST']);
        $url = $this->endpoint($this->room($owner)->id);
        $this->actingAs($owner);

        foreach (['page=0', 'page=-1', 'page=01', 'page=1.0', 'page=', 'page[]=1', 'page=2147483648'] as $query) {
            $this->getJson($url.'?'.$query)->assertUnprocessable()->assertJsonValidationErrors('page');
        }
        $this->getJson($url.'?per_page=50')->assertUnprocessable()->assertJsonValidationErrors('per_page');
    }

    private function endpoint(int|string $roomId): string
    {
        return '/api/host/rooms/'.$roomId.'/maintenances';
    }

    private function room(User $host): Room
    {
        $property = Property::create([
            'host_id' => $host->id, 'name' => 'Maintenance fixture', 'type' => 'HOTEL',
            'address' => 'Fixture address', 'status' => 'ACTIVE',
        ]);
        $type = $property->roomTypes()->create(['name' => 'Standard']);

        return $property->rooms()->create([
            'room_type_id' => $type->id, 'room_number' => '101', 'name' => 'Fixture room',
            'capacity' => 2, 'price_per_night' => '100.00', 'status' => 'ACTIVE',
        ]);
    }

    private function schedule(Room $room, string $start, string $status, ?string $reason = null): int
    {
        return DB::table('maintenances')->insertGetId([
            'room_id' => $room->id, 'start_date' => $start, 'end_date' => '2026-12-31',
            'reason' => $reason, 'status' => $status,
        ]);
    }
}
