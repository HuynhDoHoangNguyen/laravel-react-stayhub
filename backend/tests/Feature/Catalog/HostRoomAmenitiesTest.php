<?php

namespace Tests\Feature\Catalog;

use App\Models\Amenity;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

final class HostRoomAmenitiesTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
            || config('database.connections.sqlite.url')) {
            throw new RuntimeException('Room amenity tests require SQLite in-memory.');
        }

        parent::beforeRefreshingDatabase();
    }

    public function test_empty_catalog_returns_two_empty_lists(): void
    {
        $host = User::factory()->create(['role' => 'HOST']);
        $room = $this->room($host, '101');

        $this->actingAs($host)->getJson($this->endpoint($room->id))->assertOk()->assertExactJson([
            'success' => true, 'message' => 'Danh sách tiện nghi phòng.',
            'data' => ['assigned' => [], 'available' => []],
        ]);
    }

    public function test_lists_assigned_amenities_and_active_unassigned_options_only(): void
    {
        $host = User::factory()->create(['role' => 'HOST']);
        $room = $this->room($host, '101');
        $air = Amenity::factory()->create(['name' => 'A air', 'status' => true]);
        $wifi = Amenity::factory()->create(['name' => 'B wifi', 'status' => true]);
        $legacy = Amenity::factory()->create(['name' => 'C legacy', 'status' => false]);
        $bath = Amenity::factory()->create(['name' => 'D bath', 'status' => true]);
        Amenity::factory()->create(['name' => 'E disabled', 'status' => false]);
        $room->amenities()->attach([$legacy->id, $wifi->id]);

        $this->actingAs($host)->getJson($this->endpoint($room->id))->assertOk()->assertExactJson([
            'success' => true,
            'message' => 'Danh sách tiện nghi phòng.',
            'data' => [
                'assigned' => [
                    ['id' => $wifi->id, 'name' => 'B wifi', 'status' => true],
                    ['id' => $legacy->id, 'name' => 'C legacy', 'status' => false],
                ],
                'available' => [
                    ['id' => $air->id, 'name' => 'A air', 'status' => true],
                    ['id' => $bath->id, 'name' => 'D bath', 'status' => true],
                ],
            ],
        ]);
    }

    public function test_assigns_an_active_amenity_idempotently_and_preserves_other_rooms(): void
    {
        $host = User::factory()->create(['role' => 'HOST']);
        $room = $this->room($host, '101');
        $otherRoom = $this->room($host, '102');
        $pool = Amenity::factory()->create(['name' => 'Pool', 'status' => true]);
        $legacy = Amenity::factory()->create(['name' => 'Legacy', 'status' => false]);
        $room->amenities()->attach($legacy->id);
        $otherRoom->amenities()->attach($legacy->id);
        $poolItem = ['id' => $pool->id, 'name' => 'Pool', 'status' => true];
        $expected = ['assigned' => [['id' => $legacy->id, 'name' => 'Legacy', 'status' => false], $poolItem], 'available' => []];
        $before = $room->fresh()->getAttributes();

        $this->getJson('/api/public/rooms/filter-options')->assertOk()->assertJsonCount(0, 'data.amenities');
        $this->actingAs($host);
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->putJson($this->endpoint($room->id).'/'.$pool->id)->assertOk()->assertExactJson([
                'success' => true, 'message' => 'Đã gán tiện nghi cho phòng.', 'data' => $expected,
            ]);
        }

        $this->assertDatabaseCount('room_amenities', 3);
        $this->assertDatabaseCount('amenities', 2);
        $this->assertSame($before, $room->fresh()->getAttributes());
        $this->assertDatabaseMissing('room_amenities', ['room_id' => $otherRoom->id, 'amenity_id' => $pool->id]);
        $this->getJson($this->endpoint($room->id))->assertOk()->assertJsonPath('data', $expected);
        $this->getJson('/api/host/rooms/'.$room->id)->assertOk()->assertJsonPath('data.amenities', $expected['assigned']);
        $this->getJson('/api/public/rooms/filter-options')->assertOk()
            ->assertJsonPath('data.amenities', [['id' => $pool->id, 'name' => 'Pool']]);
        $this->getJson('/api/public/rooms?amenity_ids[]='.$pool->id)->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $room->id);
    }

    public function test_rejects_missing_and_disabled_amenities_without_touching_existing_links(): void
    {
        $host = User::factory()->create(['role' => 'HOST']);
        $room = $this->room($host, '101');
        $disabled = Amenity::factory()->create(['status' => false]);
        $legacy = Amenity::factory()->create(['status' => false]);
        $room->amenities()->attach($legacy->id);

        $this->actingAs($host);
        foreach ([$disabled->id, $legacy->id, 99999] as $id) {
            $this->putJson($this->endpoint($room->id).'/'.$id)->assertUnprocessable()
                ->assertJsonValidationErrors('amenity_id');
        }
        $this->assertDatabaseCount('room_amenities', 1);
        $this->assertDatabaseHas('room_amenities', ['room_id' => $room->id, 'amenity_id' => $legacy->id]);
    }

    public function test_detach_active_and_inactive_amenities_only_for_the_target_room(): void
    {
        $host = User::factory()->create(['role' => 'HOST']);
        $room = $this->room($host, '101');
        $otherRoom = $this->room($host, '102');
        $legacy = Amenity::factory()->create(['name' => 'Legacy', 'status' => false]);
        $pool = Amenity::factory()->create(['name' => 'Pool', 'status' => true]);
        $room->amenities()->attach([$legacy->id, $pool->id]);
        $otherRoom->amenities()->attach([$legacy->id, $pool->id]);
        $before = $room->fresh()->getAttributes();
        $poolItem = ['id' => $pool->id, 'name' => 'Pool', 'status' => true];
        $legacyItem = ['id' => $legacy->id, 'name' => 'Legacy', 'status' => false];
        $url = $this->endpoint($room->id);

        $this->actingAs($host)->deleteJson($url.'/'.$pool->id)->assertOk()->assertExactJson([
            'success' => true, 'message' => 'Đã gỡ tiện nghi khỏi phòng.',
            'data' => ['assigned' => [$legacyItem], 'available' => [$poolItem]],
        ]);
        $expected = ['assigned' => [], 'available' => [$poolItem]];
        foreach ([$legacy->id, $legacy->id, $pool->id, 99999] as $amenityId) {
            $this->deleteJson($url.'/'.$amenityId)->assertOk()->assertJsonPath('data', $expected);
        }

        $this->getJson($url)->assertOk()->assertJsonPath('data', $expected);
        $this->getJson('/api/host/rooms/'.$room->id)->assertOk()->assertJsonPath('data.amenities', []);
        $this->assertSame($before, $room->fresh()->getAttributes());
        $this->assertDatabaseCount('amenities', 2);
        $this->assertDatabaseCount('room_amenities', 2);
        foreach ([$legacy->id, $pool->id] as $amenityId) {
            $this->assertDatabaseHas('room_amenities', ['room_id' => $otherRoom->id, 'amenity_id' => $amenityId]);
        }

        $this->putJson($url.'/'.$pool->id)->assertOk()
            ->assertJsonPath('data', ['assigned' => [$poolItem], 'available' => []]);
    }

    public function test_public_filter_stops_matching_only_the_room_that_removed_the_amenity(): void
    {
        $host = User::factory()->create(['role' => 'HOST']);
        $room = $this->room($host, '101');
        $otherRoom = $this->room($host, '102');
        $wifi = Amenity::factory()->create(['name' => 'Wi-Fi', 'status' => true]);
        $room->amenities()->attach($wifi->id);
        $otherRoom->amenities()->attach($wifi->id);
        $search = '/api/public/rooms?amenity_ids[]='.$wifi->id;

        $this->getJson($search)->assertOk()->assertJsonCount(2, 'data');
        $this->actingAs($host)->deleteJson($this->endpoint($room->id).'/'.$wifi->id)->assertOk();
        $this->getJson($search)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $otherRoom->id);
        $this->getJson('/api/public/rooms/filter-options')->assertOk()
            ->assertJsonPath('data.amenities', [['id' => $wifi->id, 'name' => 'Wi-Fi']]);
    }

    public function test_room_scope_role_and_account_guards_protect_both_endpoints(): void
    {
        $owner = User::factory()->create(['role' => 'HOST']);
        $otherHost = User::factory()->create(['role' => 'HOST']);
        $customer = User::factory()->create(['role' => 'CUSTOMER']);
        $inactive = User::factory()->create(['role' => 'HOST', 'status' => 'INACTIVE']);
        $room = $this->room($owner, '101');
        $pool = Amenity::factory()->create(['status' => true]);
        $room->amenities()->attach($pool->id);
        $url = $this->endpoint($room->id);

        $this->getJson($url)->assertUnauthorized();
        $this->putJson($url.'/'.$pool->id)->assertUnauthorized();
        $this->deleteJson($url.'/'.$pool->id)->assertUnauthorized();
        foreach ([$customer, $inactive] as $actor) {
            $this->actingAs($actor)->getJson($url)->assertForbidden();
            $this->actingAs($actor)->putJson($url.'/'.$pool->id)->assertForbidden();
            $this->actingAs($actor)->deleteJson($url.'/'.$pool->id)->assertForbidden();
        }
        $this->actingAs($otherHost)->getJson($url)->assertNotFound();
        $this->putJson($url.'/'.$pool->id)->assertNotFound();
        $this->putJson($url.'/99999')->assertNotFound();
        $this->deleteJson($url.'/'.$pool->id)->assertNotFound();
        $this->actingAs($owner)->getJson($this->endpoint(99999))->assertNotFound();
        $this->putJson($this->endpoint(99999).'/'.$pool->id)->assertNotFound();
        $this->deleteJson($this->endpoint(99999).'/'.$pool->id)->assertNotFound();
        $this->assertDatabaseCount('room_amenities', 1);
        $this->assertDatabaseHas('room_amenities', ['room_id' => $room->id, 'amenity_id' => $pool->id]);
    }

    public function test_rejects_noncanonical_ids_and_unexpected_inputs(): void
    {
        $host = User::factory()->create(['role' => 'HOST']);
        $room = $this->room($host, '101');
        $amenity = Amenity::factory()->create(['status' => true]);
        $url = $this->endpoint($room->id);

        $this->actingAs($host)->getJson($this->endpoint('0'.$room->id))->assertNotFound();
        $this->putJson($url.'/0'.$amenity->id)->assertNotFound();
        $this->deleteJson($url.'/0'.$amenity->id)->assertNotFound();
        $this->getJson($url.'?include=other')->assertUnprocessable()->assertJsonValidationErrors('include');
        $this->putJson($url.'/'.$amenity->id, ['status' => false])->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->deleteJson($url.'/'.$amenity->id, ['status' => false])->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->deleteJson($url.'/'.$amenity->id.'?include=others')->assertUnprocessable()->assertJsonValidationErrors('include');
        $this->assertDatabaseCount('room_amenities', 0);
    }

    private function endpoint(int|string $roomId): string
    {
        return '/api/host/rooms/'.$roomId.'/amenities';
    }

    private function room(User $host, string $number): Room
    {
        $property = Property::create([
            'host_id' => $host->id, 'name' => 'Amenity test property', 'type' => 'HOTEL',
            'address' => 'Fixture address', 'status' => 'ACTIVE',
        ]);
        $type = $property->roomTypes()->create(['name' => 'Standard']);

        return $property->rooms()->create([
            'room_type_id' => $type->id, 'room_number' => $number, 'name' => 'Amenity test room',
            'capacity' => 2, 'price_per_night' => '100.00', 'status' => 'ACTIVE',
        ]);
    }
}
