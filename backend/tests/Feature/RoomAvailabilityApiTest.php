<?php

namespace Tests\Feature;

use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomAvailabilityApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_room_returns_not_found(): void
    {
        $checkIn = Carbon::today()->addDays(2)->toDateString();
        $checkOut = Carbon::today()->addDays(4)->toDateString();

        $this->getJson('/api/public/rooms/999999/availability?' . http_build_query([
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'guest_count' => 1,
        ]))->assertNotFound();
    }

    public function test_available_room_response_keeps_the_existing_shape(): void
    {
        $room = Room::factory()->create(['capacity' => 2]);
        $checkIn = Carbon::today()->addDays(2)->toDateString();
        $checkOut = Carbon::today()->addDays(4)->toDateString();

        $this->getJson('/api/public/rooms/' . $room->id . '/availability?' . http_build_query([
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'guest_count' => 2,
        ]))->assertExactJson([
            'success' => true,
            'message' => 'Kiểm tra phòng trống thành công.',
            'data' => [
                'available' => true,
                'reasons' => [],
                'blocked_ranges' => [],
            ],
        ]);
    }
}
