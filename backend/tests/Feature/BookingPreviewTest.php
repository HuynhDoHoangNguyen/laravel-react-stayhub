<?php

namespace Tests\Feature;

use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_preview_calculates_accurate_price_and_ignores_client_fake_price(): void
    {
        $room = Room::factory()->create([
            'price_per_night' => 1000000,
            'capacity' => 4,
        ]);

        $checkIn = \Carbon\Carbon::now()->addDays(2)->format('Y-m-d');
        $checkOut = \Carbon\Carbon::now()->addDays(5)->format('Y-m-d'); // 3 nights

        $payload = [
            'room_id' => $room->id,
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'guest_count' => 2,

            // Fake price/total values sent by client to test if backend ignores them
            'price_per_night' => 10,
            'room_total' => 30,
            'amount' => 30,
        ];

        $response = $this->postJson('/api/public/bookings/preview', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'room_id' => $room->id,
                    'check_in_date' => $checkIn,
                    'check_out_date' => $checkOut,
                    'guest_count' => 2,
                    'number_of_nights' => 3,
                    'price_per_night' => '1000000.00',
                    'room_total' => '3000000.00',
                    'availability' => [
                        'available' => true,
                        'reasons' => [],
                    ],
                ],
            ]);
    }
}
