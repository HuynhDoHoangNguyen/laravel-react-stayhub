<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Amenity;
use App\Models\Maintenance;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Database\Seeders\DemoM2Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Member1StubsTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_expected_data(): void
    {
        $this->seed(DemoM2Seeder::class);

        $this->assertDatabaseHas('users', ['email' => 'admin@stayhub.com', 'role' => 'ADMIN']);
        $this->assertDatabaseHas('users', ['email' => 'host1@stayhub.com', 'role' => 'HOST']);
        $this->assertDatabaseHas('users', ['email' => 'host2@stayhub.com', 'role' => 'HOST']);
        $this->assertDatabaseHas('users', ['email' => 'customer1@stayhub.com', 'role' => 'CUSTOMER']);
        $this->assertDatabaseHas('users', ['email' => 'customer2@stayhub.com', 'role' => 'CUSTOMER']);
        $this->assertDatabaseHas('users', ['email' => 'customer3@stayhub.com', 'role' => 'CUSTOMER']);

        $this->assertCount(3, Property::all());
        $this->assertCount(6, RoomType::all());
        $this->assertCount(9, Room::all());
        $this->assertCount(5, Amenity::all());
    }

    public function test_model_relationships(): void
    {
        $host = User::factory()->host()->create();
        $property = Property::factory()->create(['host_id' => $host->id]);
        $roomType = RoomType::factory()->create(['property_id' => $property->id]);
        $room = Room::factory()->create([
            'property_id' => $property->id,
            'room_type_id' => $roomType->id,
        ]);
        $amenity = Amenity::factory()->create();
        $room->amenities()->attach($amenity->id);

        $maintenance = Maintenance::factory()->create(['room_id' => $room->id]);

        $this->assertEquals($host->id, $property->host->id);
        $this->assertTrue($host->properties->contains($property));
        $this->assertEquals($property->id, $room->property->id);
        $this->assertEquals($roomType->id, $room->roomType->id);
        $this->assertTrue($room->amenities->contains($amenity));
        $this->assertEquals($room->id, $maintenance->room->id);
    }

    public function test_role_middleware_allows_authorized_role(): void
    {
        $customer = User::factory()->customer()->create();

        $response = $this->actingAs($customer)
            ->getJson('/api/me');

        $response->assertStatus(200);
    }

    public function test_role_middleware_blocks_blocked_user(): void
    {
        \Illuminate\Support\Facades\Route::get('/api/test-role', function () {
            return response()->json(['success' => true]);
        })->middleware(['auth:sanctum', 'role:CUSTOMER']);

        $blockedUser = User::factory()->customer()->blocked()->create();

        $response = $this->actingAs($blockedUser)
            ->getJson('/api/test-role');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Tài khoản của bạn đã bị khóa.',
            ]);
    }

    public function test_role_middleware_denies_wrong_role(): void
    {
        \Illuminate\Support\Facades\Route::get('/api/test-role-host', function () {
            return response()->json(['success' => true]);
        })->middleware(['auth:sanctum', 'role:HOST']);

        $customer = User::factory()->customer()->create();

        $response = $this->actingAs($customer)
            ->getJson('/api/test-role-host');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Bạn không có quyền truy cập.',
            ]);
    }
}
