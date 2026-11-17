<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\User;
use App\Enums\RoomStatus;
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

    public function test_preview_and_create_return_the_same_decimal_price_snapshot(): void
    {
        $customer = User::factory()->customer()->create();
        $room = Room::factory()->create([
            'price_per_night' => '123456.78',
            'capacity' => 4,
            'status' => RoomStatus::ACTIVE,
        ]);
        $checkIn = now()->addDays(10)->format('Y-m-d');
        $checkOut = now()->addDays(13)->format('Y-m-d');
        $payload = [
            'room_id' => $room->id,
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'guest_count' => 2,
        ];

        $preview = $this->postJson('/api/public/bookings/preview', $payload)
            ->assertOk()
            ->assertJsonPath('data.price_per_night', '123456.78')
            ->assertJsonPath('data.room_total', '370370.34');

        $created = $this->actingAs($customer)->postJson('/api/customer/bookings', $payload)
            ->assertCreated()
            ->assertJsonPath('data.status', 'PENDING')
            ->assertJsonPath('data.price_per_night', '123456.78')
            ->assertJsonPath('data.room_total', '370370.34');

        $this->assertSame($preview->json('data.price_per_night'), $created->json('data.price_per_night'));
        $this->assertSame($preview->json('data.room_total'), $created->json('data.room_total'));
    }

    public function test_preview_invalid_date_returns_form_request_error_shape(): void
    {
        $room = Room::factory()->create();

        $this->postJson('/api/public/bookings/preview', [
            'room_id' => $room->id,
            'check_in_date' => now()->addDays(3)->format('Y-m-d'),
            'check_out_date' => now()->addDays(2)->format('Y-m-d'),
            'guest_count' => 1,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['check_out_date'])
            ->assertJsonStructure(['message', 'errors' => ['check_out_date']]);
    }
}
