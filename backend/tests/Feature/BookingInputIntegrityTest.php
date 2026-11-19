<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PropertyStatus;
use App\Enums\RoomStatus;
use App\Models\Amenity;
use App\Models\Booking;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingInputIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_room_search_min_price_invalid_does_not_throw_500(): void
    {
        // R04: Non-numeric min_price with numeric max_price must return 422, not 500
        $response = $this->getJson('/api/public/rooms/search?' . http_build_query([
            'check_in_date' => now()->addDays(5)->format('Y-m-d'),
            'check_out_date' => now()->addDays(7)->format('Y-m-d'),
            'guest_count' => 2,
            'min_price' => 'abc',
            'max_price' => '1000000',
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['min_price']);
    }

    public function test_room_search_array_min_price_does_not_throw_500(): void
    {
        // R04: Array min_price must return 422, not 500
        $response = $this->getJson('/api/public/rooms/search?' . http_build_query([
            'check_in_date' => now()->addDays(5)->format('Y-m-d'),
            'check_out_date' => now()->addDays(7)->format('Y-m-d'),
            'guest_count' => 2,
            'min_price' => ['invalid'],
            'max_price' => '1000000',
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['min_price']);
    }

    public function test_room_search_min_price_greater_than_max_price_returns_422(): void
    {
        // R04: min_price > max_price returns cross-field validation error
        $response = $this->getJson('/api/public/rooms/search?' . http_build_query([
            'check_in_date' => now()->addDays(5)->format('Y-m-d'),
            'check_out_date' => now()->addDays(7)->format('Y-m-d'),
            'guest_count' => 2,
            'min_price' => '2000000',
            'max_price' => '1000000',
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['max_price']);
    }

    public function test_host_reject_validates_reason_and_rejects_non_string(): void
    {
        // R05: reason as array must return 422, never TypeError / 500
        $host = User::factory()->host()->create();
        $property = Property::factory()->create(['host_id' => $host->id, 'status' => PropertyStatus::ACTIVE]);
        $room = Room::factory()->create(['property_id' => $property->id, 'status' => RoomStatus::ACTIVE]);
        $booking = Booking::factory()->pending()->create(['room_id' => $room->id]);

        $response = $this->actingAs($host)->postJson("/api/host/bookings/{$booking->id}/reject", [
            'reason' => ['invalid_array'],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_host_reject_validates_reason_max_length(): void
    {
        // R05: reason > 500 characters returns 422
        $host = User::factory()->host()->create();
        $property = Property::factory()->create(['host_id' => $host->id, 'status' => PropertyStatus::ACTIVE]);
        $room = Room::factory()->create(['property_id' => $property->id, 'status' => RoomStatus::ACTIVE]);
        $booking = Booking::factory()->pending()->create(['room_id' => $room->id]);

        $response = $this->actingAs($host)->postJson("/api/host/bookings/{$booking->id}/reject", [
            'reason' => str_repeat('a', 501),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_host_reject_accepts_valid_string_and_empty_payload(): void
    {
        // R05: valid reason or empty payload succeeds
        $host = User::factory()->host()->create();
        $property = Property::factory()->create(['host_id' => $host->id, 'status' => PropertyStatus::ACTIVE]);
        $room1 = Room::factory()->create(['property_id' => $property->id, 'status' => RoomStatus::ACTIVE]);
        $room2 = Room::factory()->create(['property_id' => $property->id, 'status' => RoomStatus::ACTIVE]);
        $booking1 = Booking::factory()->pending()->create(['room_id' => $room1->id]);
        $booking2 = Booking::factory()->pending()->create(['room_id' => $room2->id]);

        $response1 = $this->actingAs($host)->postJson("/api/host/bookings/{$booking1->id}/reject", [
            'reason' => 'Khách yêu cầu hủy phòng.',
        ]);
        $response1->assertOk()
            ->assertJsonPath('data.status', BookingStatus::REJECTED->value);

        $response2 = $this->actingAs($host)->postJson("/api/host/bookings/{$booking2->id}/reject", []);
        $response2->assertOk()
            ->assertJsonPath('data.status', BookingStatus::REJECTED->value);
    }

    public function test_host_reject_validation_precedes_ownership(): void
    {
        // R05: Error precedence - invalid payload on another host's booking returns 422 before 404
        $hostA = User::factory()->host()->create();
        $hostB = User::factory()->host()->create();
        $propertyB = Property::factory()->create(['host_id' => $hostB->id, 'status' => PropertyStatus::ACTIVE]);
        $roomB = Room::factory()->create(['property_id' => $propertyB->id, 'status' => RoomStatus::ACTIVE]);
        $bookingB = Booking::factory()->pending()->create(['room_id' => $roomB->id]);

        // Invalid payload sent to another host's booking -> 422
        $this->actingAs($hostA)->postJson("/api/host/bookings/{$bookingB->id}/reject", [
            'reason' => ['not_a_string'],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);

        // Valid payload sent to another host's booking -> 404
        $this->actingAs($hostA)->postJson("/api/host/bookings/{$bookingB->id}/reject", [
            'reason' => 'Some reason',
        ])->assertStatus(404);
    }

    public function test_room_search_deduplicates_amenity_ids_in_and_filter(): void
    {
        // R09: Duplicate amenity_ids in search query must not exclude rooms having that amenity
        $property = Property::factory()->create(['status' => PropertyStatus::ACTIVE]);
        $room = Room::factory()->create([
            'property_id' => $property->id,
            'status' => RoomStatus::ACTIVE,
            'capacity' => 4,
            'price_per_night' => '500000.00',
        ]);
        $amenity = Amenity::factory()->create();
        $room->amenities()->attach($amenity->id);

        $checkIn = now()->addDays(5)->format('Y-m-d');
        $checkOut = now()->addDays(7)->format('Y-m-d');

        // Search with duplicate amenity_ids: [amenity->id, amenity->id]
        $response = $this->getJson("/api/public/rooms/search?" . http_build_query([
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'guest_count' => 2,
            'property_id' => $property->id,
            'amenity_ids' => [$amenity->id, $amenity->id],
        ]));

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame($room->id, $data[0]['id']);
    }

    public function test_r06_booking_preview_and_create_non_existent_room_returns_422(): void
    {
        // R06: Pure validator boundary - Service checks repository and throws ValidationException 422
        $customer = User::factory()->customer()->create();

        $previewResponse = $this->postJson('/api/public/bookings/preview', [
            'room_id' => 999999,
            'check_in_date' => now()->addDays(5)->format('Y-m-d'),
            'check_out_date' => now()->addDays(7)->format('Y-m-d'),
            'guest_count' => 2,
        ]);

        $previewResponse->assertStatus(422)
            ->assertJsonValidationErrors(['room_id']);

        $createResponse = $this->actingAs($customer)->postJson('/api/customer/bookings', [
            'room_id' => 999999,
            'check_in_date' => now()->addDays(5)->format('Y-m-d'),
            'check_out_date' => now()->addDays(7)->format('Y-m-d'),
            'guest_count' => 2,
            'guest_name' => 'Nguyen Van A',
            'guest_phone' => '0901234567',
        ]);

        $createResponse->assertStatus(422)
            ->assertJsonValidationErrors(['room_id']);
    }

    public function test_r06_service_store_non_existent_property_returns_422(): void
    {
        // R06: PropertyServiceManagementService checks repository and throws 422
        $host = User::factory()->host()->create();

        $response = $this->actingAs($host)->postJson('/api/host/services', [
            'property_id' => 999999,
            'name' => 'Dịch vụ giặt ủi',
            'unit' => 'Lượt',
            'price' => '50000',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['property_id']);
    }

    public function test_r06_query_dtos_clamp_per_page_and_safely_handle_query_inputs(): void
    {
        // R06: Customer & Host query endpoints accept typed DTOs and clamp pagination
        $customer = User::factory()->customer()->create();
        $host = User::factory()->host()->create();

        $customerList = $this->actingAs($customer)->getJson('/api/customer/bookings?per_page=999&page=0');
        $customerList->assertOk()
            ->assertJsonPath('meta.per_page', 50)
            ->assertJsonPath('meta.current_page', 1);

        $hostList = $this->actingAs($host)->getJson('/api/host/bookings?per_page=-5&status=PENDING');
        $hostList->assertOk()
            ->assertJsonPath('meta.per_page', 15);

        $serviceList = $this->actingAs($host)->getJson('/api/host/services?per_page=100');
        $serviceList->assertOk()
            ->assertJsonPath('meta.per_page', 50);
    }
}
