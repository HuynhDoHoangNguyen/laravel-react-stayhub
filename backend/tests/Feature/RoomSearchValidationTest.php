<?php

namespace Tests\Feature;

use App\Enums\PropertyStatus;
use App\Enums\RoomStatus;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomSearchValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_room_search_and_availability_validation_baseline(): void
    {
        $host = User::factory()->host()->create([
            'email' => 'host_search_validation@example.com',
        ]);
        $property = Property::factory()->create([
            'host_id' => $host->id,
            'status' => PropertyStatus::ACTIVE,
        ]);
        $room = Room::factory()->create([
            'property_id' => $property->id,
            'status' => RoomStatus::ACTIVE,
            'capacity' => 4,
            'price_per_night' => 500000,
        ]);

        $payloads = [];

        // 1. SearchRooms: empty query
        $res = $this->getJson('/api/public/rooms/search');
        $res->assertStatus(422);
        $payloads['search_empty'] = $res->json();

        // 2. SearchRooms: past dates
        $res = $this->getJson('/api/public/rooms/search?check_in_date=2020-01-01&check_out_date=2020-01-03&guest_count=2');
        $res->assertStatus(422);
        $payloads['search_past_date'] = $res->json();

        // 3. SearchRooms: checkout <= checkin
        $today = Carbon::now()->format('Y-m-d');
        $res = $this->getJson("/api/public/rooms/search?check_in_date={$today}&check_out_date={$today}&guest_count=2");
        $res->assertStatus(422);
        $payloads['search_checkout_equal_checkin'] = $res->json();

        // 4. SearchRooms: guest_count = 0
        $tomorrow = Carbon::now()->addDay()->format('Y-m-d');
        $nextDay = Carbon::now()->addDays(2)->format('Y-m-d');
        $res = $this->getJson("/api/public/rooms/search?check_in_date={$tomorrow}&check_out_date={$nextDay}&guest_count=0");
        $res->assertStatus(422);
        $payloads['search_guest_count_zero'] = $res->json();

        // 5. SearchRooms: guest_count = string
        $res = $this->getJson("/api/public/rooms/search?check_in_date={$tomorrow}&check_out_date={$nextDay}&guest_count=abc");
        $res->assertStatus(422);
        $payloads['search_guest_count_string'] = $res->json();

        // 6. SearchRooms: min_price negative
        $res = $this->getJson("/api/public/rooms/search?check_in_date={$tomorrow}&check_out_date={$nextDay}&guest_count=2&min_price=-10");
        $res->assertStatus(422);
        $payloads['search_min_price_negative'] = $res->json();

        // 7. SearchRooms: max_price < min_price
        $res = $this->getJson("/api/public/rooms/search?check_in_date={$tomorrow}&check_out_date={$nextDay}&guest_count=2&min_price=500000&max_price=200000");
        $res->assertStatus(422);
        $payloads['search_max_price_less_than_min'] = $res->json();

        // 8. SearchRooms: invalid sort
        $res = $this->getJson("/api/public/rooms/search?check_in_date={$tomorrow}&check_out_date={$nextDay}&guest_count=2&sort=invalid_sort");
        $res->assertStatus(422);
        $payloads['search_invalid_sort'] = $res->json();

        // 9. SearchRooms: per_page > 50
        $res = $this->getJson("/api/public/rooms/search?check_in_date={$tomorrow}&check_out_date={$nextDay}&guest_count=2&per_page=100");
        $res->assertStatus(422);
        $payloads['search_per_page_over_limit'] = $res->json();

        // 10. SearchRooms: non-existent property_id
        $res = $this->getJson("/api/public/rooms/search?check_in_date={$tomorrow}&check_out_date={$nextDay}&guest_count=2&property_id=999999");
        $res->assertStatus(422);
        $payloads['search_non_existent_property'] = $res->json();

        // 11. CheckAvailability: empty query on existing room
        $res = $this->getJson("/api/public/rooms/{$room->id}/availability");
        $res->assertStatus(422);
        $payloads['availability_empty'] = $res->json();

        // 12. CheckAvailability: checkout <= checkin
        $res = $this->getJson("/api/public/rooms/{$room->id}/availability?check_in_date={$today}&check_out_date={$today}&guest_count=2");
        $res->assertStatus(422);
        $payloads['availability_checkout_equal_checkin'] = $res->json();

        // 13. CheckAvailability: non-existent room (404 precedes validation 422)
        $res = $this->getJson('/api/public/rooms/999999/availability');
        $res->assertStatus(404);

        $baselineFile = base_path('tests/Fixtures/room_search_validation_baseline.json');
        if (! file_exists($baselineFile)) {
            if (! is_dir(dirname($baselineFile))) {
                mkdir(dirname($baselineFile), 0755, true);
            }
            file_put_contents($baselineFile, json_encode($payloads, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        $baseline = json_decode(file_get_contents($baselineFile), true);
        $this->assertSame($baseline, $payloads, 'Room search validation errors must match baseline exactly');
    }
}
