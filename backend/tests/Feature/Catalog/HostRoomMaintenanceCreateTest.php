<?php

namespace Tests\Feature\Catalog;

use App\Models\Booking;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

final class HostRoomMaintenanceCreateTest extends TestCase
{
    use RefreshDatabase;

    private const PERIOD = ['start_date' => '2026-12-12', 'end_date' => '2026-12-14'];

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
            || config('database.connections.sqlite.url')) {
            throw new RuntimeException('Room maintenance create tests require SQLite in-memory.');
        }

        parent::beforeRefreshingDatabase();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now(config('app.timezone'))->setDate(2026, 12, 10)->setTime(12, 0));
    }

    public function test_creates_a_scheduled_record_and_get_returns_the_same_projection(): void
    {
        [$host, $room] = $this->fixture();
        $url = $this->endpoint($room->id);
        $response = $this->actingAs($host)->postJson($url, self::PERIOD)->assertCreated();
        $id = $response->json('data.id');
        $expected = ['id' => $id] + self::PERIOD + ['reason' => null, 'status' => 'SCHEDULED'];

        $response->assertExactJson(['data' => $expected]);
        $this->assertDatabaseHas('maintenances', $expected + ['room_id' => $room->id]);
        $this->getJson($url)->assertOk()->assertJsonPath('data.0', $expected)
            ->assertJsonPath('meta.current_page', 1)->assertJsonPath('meta.total', 1);
        $this->assertDatabaseHas('rooms', ['id' => $room->id, 'status' => 'ACTIVE']);
    }

    public function test_conflicts_do_not_create_rows_and_an_adjacent_schedule_can_be_saved(): void
    {
        [$host, $room] = $this->fixture();
        $url = $this->endpoint($room->id);
        Booking::factory()->forRoom($room)->confirmed()->create([
            'check_in_date' => '2026-12-13', 'check_out_date' => '2026-12-14', 'number_of_nights' => 1,
        ]);
        $this->actingAs($host)->postJson($url, self::PERIOD)->assertConflict()
            ->assertJsonPath('code', 'CATALOG_ROOM_BOOKING_CONFLICT');
        $this->assertDatabaseCount('maintenances', 0);

        $adjacent = ['start_date' => '2026-12-14', 'end_date' => '2026-12-15', 'reason' => 'Vệ sinh máy lạnh'];
        $this->postJson($url, $adjacent)->assertCreated()->assertJsonPath('data.reason', $adjacent['reason']);
        $this->postJson($url, $adjacent)->assertConflict()
            ->assertJsonPath('code', 'CATALOG_ROOM_MAINTENANCE_CONFLICT');
        $this->assertDatabaseCount('maintenances', 1);
    }

    public function test_rejects_bad_fields_queries_and_periods_without_writes(): void
    {
        [$host, $room] = $this->fixture();
        $url = $this->endpoint($room->id);
        $this->actingAs($host);
        $this->postJson($url, [])->assertUnprocessable()->assertJsonValidationErrors(['start_date', 'end_date']);
        $this->postJson($url, array_replace(self::PERIOD, ['start_date' => 20261212]))
            ->assertUnprocessable()->assertJsonValidationErrors('start_date');
        $this->postJson($url.'?page=2', self::PERIOD + ['status' => 'COMPLETED', 'room_id' => $room->id, 'reason' => []])
            ->assertUnprocessable()->assertJsonValidationErrors(['page', 'status', 'room_id', 'reason']);
        $this->postJson($url, array_replace(self::PERIOD, ['end_date' => self::PERIOD['start_date']]))
            ->assertUnprocessable()->assertJsonValidationErrors('end_date');
        $this->postJson($url, array_replace(self::PERIOD, ['start_date' => '2026-12-09']))
            ->assertUnprocessable()->assertJsonValidationErrors('start_date');
        $this->assertDatabaseCount('maintenances', 0);
    }

    public function test_guards_guest_role_account_and_room_ownership(): void
    {
        [$host, $room] = $this->fixture();
        $url = $this->endpoint($room->id);
        $this->postJson($url, self::PERIOD)->assertUnauthorized();
        foreach (['CUSTOMER', 'ADMIN'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->postJson($url, self::PERIOD)->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['role' => 'HOST', 'status' => 'INACTIVE']))
            ->postJson($url, self::PERIOD)->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'HOST']))
            ->postJson($url, self::PERIOD)->assertNotFound()->assertExactJson(['message' => 'Không tìm thấy phòng.']);
        $this->actingAs($host)->postJson($this->endpoint('0'.$room->id), self::PERIOD)->assertNotFound();
        $this->postJson($this->endpoint(999999), self::PERIOD)->assertNotFound();
        $this->assertDatabaseCount('maintenances', 0);
    }

    public function test_host_pending_approval_cannot_create_for_an_owned_room(): void
    {
        [$host, $room] = $this->fixture();
        $hash = hash('sha256', 'Fixture terms');
        $agreementId = DB::table('partner_agreements')->insertGetId([
            'version' => 'uc23-create-approval', 'title' => 'Fixture agreement', 'terms_body' => 'Fixture terms',
            'content_sha256' => $hash, 'effective_at' => now(),
        ]);
        DB::table('host_agreement_acceptances')->insert([
            'host_id' => $host->id, 'partner_agreement_id' => $agreementId, 'accepted_content_sha256' => $hash,
            'accepted_at' => now(), 'method' => 'REGISTRATION_PENDING',
        ]);

        $this->actingAs($host)->postJson($this->endpoint($room->id), self::PERIOD)->assertForbidden()
            ->assertJsonPath('code', 'HOST_APPROVAL_PENDING');
        $this->assertDatabaseCount('maintenances', 0);
    }

    private function fixture(): array
    {
        $host = User::factory()->create(['role' => 'HOST']);
        $property = Property::factory()->create(['host_id' => $host->id]);
        $type = RoomType::factory()->create(['property_id' => $property->id]);
        $room = Room::factory()->create(['property_id' => $property->id, 'room_type_id' => $type->id]);

        return [$host, $room];
    }

    private function endpoint(int|string $roomId): string
    {
        return '/api/host/rooms/'.$roomId.'/maintenances';
    }
}
