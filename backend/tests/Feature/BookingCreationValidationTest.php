<?php

namespace Tests\Feature;

use App\Enums\PropertyStatus;
use App\Enums\RoomStatus;
use App\Models\Booking;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingCreationValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_creation_and_preview_validation_baseline(): void
    {
        $host = User::factory()->host()->create([
            'email' => 'host_booking_validation@example.com',
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
        $customer = User::factory()->customer()->create([
            'email' => 'customer_booking_validation@example.com',
        ]);

        $payloads = [];
        $today = Carbon::now()->format('Y-m-d');
        $tomorrow = Carbon::now()->addDay()->format('Y-m-d');
        $nextDay = Carbon::now()->addDays(2)->format('Y-m-d');

        // 1. Preview: empty payload
        $res = $this->postJson('/api/public/bookings/preview', []);
        $res->assertStatus(422);
        $payloads['preview_empty'] = $res->json();

        // 2. Preview: non-existent room_id
        $res = $this->postJson('/api/public/bookings/preview', [
            'room_id' => 999999,
            'check_in_date' => $tomorrow,
            'check_out_date' => $nextDay,
            'guest_count' => 2,
        ]);
        $res->assertStatus(422);
        $payloads['preview_non_existent_room'] = $res->json();

        // 3. Preview: past date
        $res = $this->postJson('/api/public/bookings/preview', [
            'room_id' => $room->id,
            'check_in_date' => '2020-01-01',
            'check_out_date' => '2020-01-03',
            'guest_count' => 2,
        ]);
        $res->assertStatus(422);
        $payloads['preview_past_date'] = $res->json();

        // 4. Preview: checkout <= checkin
        $res = $this->postJson('/api/public/bookings/preview', [
            'room_id' => $room->id,
            'check_in_date' => $today,
            'check_out_date' => $today,
            'guest_count' => 2,
        ]);
        $res->assertStatus(422);
        $payloads['preview_checkout_equal_checkin'] = $res->json();

        // 5. Preview: guest_count = 0
        $res = $this->postJson('/api/public/bookings/preview', [
            'room_id' => $room->id,
            'check_in_date' => $tomorrow,
            'check_out_date' => $nextDay,
            'guest_count' => 0,
        ]);
        $res->assertStatus(422);
        $payloads['preview_guest_count_zero'] = $res->json();

        // 6. Preview: guest_count string
        $res = $this->postJson('/api/public/bookings/preview', [
            'room_id' => $room->id,
            'check_in_date' => $tomorrow,
            'check_out_date' => $nextDay,
            'guest_count' => 'abc',
        ]);
        $res->assertStatus(422);
        $payloads['preview_guest_count_string'] = $res->json();

        // 7. Store: empty payload (authenticated customer)
        $res = $this->actingAs($customer)->postJson('/api/customer/bookings', []);
        $res->assertStatus(422);
        $payloads['store_empty'] = $res->json();

        // 8. Store: non-existent room_id
        $res = $this->actingAs($customer)->postJson('/api/customer/bookings', [
            'room_id' => 999999,
            'check_in_date' => $tomorrow,
            'check_out_date' => $nextDay,
            'guest_count' => 2,
        ]);
        $res->assertStatus(422);
        $payloads['store_non_existent_room'] = $res->json();

        // 9. Store: past date
        $res = $this->actingAs($customer)->postJson('/api/customer/bookings', [
            'room_id' => $room->id,
            'check_in_date' => '2020-01-01',
            'check_out_date' => '2020-01-03',
            'guest_count' => 2,
        ]);
        $res->assertStatus(422);
        $payloads['store_past_date'] = $res->json();

        // 10. Store: checkout <= checkin
        $res = $this->actingAs($customer)->postJson('/api/customer/bookings', [
            'room_id' => $room->id,
            'check_in_date' => $today,
            'check_out_date' => $today,
            'guest_count' => 2,
        ]);
        $res->assertStatus(422);
        $payloads['store_checkout_equal_checkin'] = $res->json();

        // 11. Store: note exceeds 1000 characters
        $res = $this->actingAs($customer)->postJson('/api/customer/bookings', [
            'room_id' => $room->id,
            'check_in_date' => $tomorrow,
            'check_out_date' => $nextDay,
            'guest_count' => 2,
            'note' => str_repeat('a', 1001),
        ]);
        $res->assertStatus(422);
        $payloads['store_note_over_limit'] = $res->json();

        $baselineFile = base_path('tests/Fixtures/booking_creation_validation_baseline.json');
        if (! file_exists($baselineFile)) {
            if (! is_dir(dirname($baselineFile))) {
                mkdir(dirname($baselineFile), 0755, true);
            }
            file_put_contents($baselineFile, json_encode($payloads, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        $baseline = json_decode(file_get_contents($baselineFile), true);
        $this->assertSame($baseline, $payloads, 'Booking creation validation errors must match baseline exactly');
    }

    public function test_booking_creation_error_order_hierarchy(): void
    {
        $host = User::factory()->host()->create([
            'email' => 'host_order_hierarchy@example.com',
        ]);
        $property = Property::factory()->create([
            'host_id' => $host->id,
            'status' => PropertyStatus::ACTIVE,
        ]);
        $room = Room::factory()->create([
            'property_id' => $property->id,
            'status' => RoomStatus::ACTIVE,
            'capacity' => 2,
            'price_per_night' => 500000,
        ]);
        $customer = User::factory()->customer()->create([
            'email' => 'customer_order_hierarchy@example.com',
        ]);

        $tomorrow = Carbon::now()->addDay()->format('Y-m-d');
        $nextDay = Carbon::now()->addDays(2)->format('Y-m-d');

        $invalidPayload = [
            'room_id' => 'not-an-integer',
            'check_in_date' => 'invalid-date',
        ];
        $validPayload = [
            'room_id' => $room->id,
            'check_in_date' => $tomorrow,
            'check_out_date' => $nextDay,
            'guest_count' => 2,
        ];

        // 1. Tier 401: Unauthenticated with INVALID payload must return 401 (not 422)
        $res = $this->postJson('/api/customer/bookings', $invalidPayload);
        $res->assertStatus(401);

        // 2. Tier 401: Unauthenticated with VALID payload must return 401
        $res = $this->postJson('/api/customer/bookings', $validPayload);
        $res->assertStatus(401);

        // 3. Tier 403: Authenticated as HOST with INVALID payload must return 403 (not 422)
        $res = $this->actingAs($host)->postJson('/api/customer/bookings', $invalidPayload);
        $res->assertStatus(403);

        // 4. Tier 403: Authenticated as HOST with VALID payload must return 403
        $res = $this->actingAs($host)->postJson('/api/customer/bookings', $validPayload);
        $res->assertStatus(403);

        // 5. Tier 422: Authenticated as CUSTOMER with INVALID payload must return 422
        $res = $this->actingAs($customer)->postJson('/api/customer/bookings', $invalidPayload);
        $res->assertStatus(422);

        // 6. Tier 409: Authenticated as CUSTOMER with VALID schema payload but business conflict (exceeds room capacity)
        $res = $this->actingAs($customer)->postJson('/api/customer/bookings', [
            'room_id' => $room->id,
            'check_in_date' => $tomorrow,
            'check_out_date' => $nextDay,
            'guest_count' => 10, // exceeds room capacity of 2
        ]);
        $res->assertStatus(409);
    }

    public function test_booking_creation_ignores_client_fake_prices(): void
    {
        $host = User::factory()->host()->create([
            'email' => 'host_fake_price@example.com',
        ]);
        $property = Property::factory()->create([
            'host_id' => $host->id,
            'status' => PropertyStatus::ACTIVE,
        ]);
        $room = Room::factory()->create([
            'property_id' => $property->id,
            'status' => RoomStatus::ACTIVE,
            'capacity' => 2,
            'price_per_night' => 500000,
        ]);
        $customer = User::factory()->customer()->create([
            'email' => 'customer_fake_price@example.com',
        ]);

        $tomorrow = Carbon::now()->addDay()->format('Y-m-d');
        $nextDay = Carbon::now()->addDays(3)->format('Y-m-d'); // 2 nights

        // Client attempts to tamper prices
        $res = $this->actingAs($customer)->postJson('/api/customer/bookings', [
            'room_id' => $room->id,
            'check_in_date' => $tomorrow,
            'check_out_date' => $nextDay,
            'guest_count' => 2,
            'price_per_night' => 1000,
            'room_total' => 2000,
            'total_amount' => 2000,
            'number_of_nights' => 1,
            'status' => 'CONFIRMED',
        ]);

        $res->assertStatus(201);
        $data = $res->json('data');

        // Verify values are snapshotted strictly from DB and pricing rules (500000 * 2 = 1000000)
        $this->assertSame('500000.00', (string) $data['price_per_night']);
        $this->assertSame('1000000.00', (string) $data['room_total']);
        $this->assertSame(2, $data['number_of_nights']);
        $this->assertSame('PENDING', $data['status']);

        // Verify persisted record in DB
        $booking = Booking::find($data['id']);
        $this->assertNotNull($booking);
        $this->assertSame('500000.00', (string) $booking->price_per_night);
        $this->assertSame('1000000.00', (string) $booking->room_total);
        $this->assertSame(2, $booking->number_of_nights);
        $this->assertSame('PENDING', $booking->status->value);
    }
}
