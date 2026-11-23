<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PropertyStatus;
use App\Enums\RoomStatus;
use App\Models\Amenity;
use App\Models\Booking;
use App\Models\Property;
use App\Models\Review;
use App\Models\Room;
use App\Models\RoomType;
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

    public function test_room_search_excludes_inactive_amenities_from_filters_and_results(): void
    {
        $property = Property::factory()->create(['status' => PropertyStatus::ACTIVE]);
        $type = RoomType::factory()->create(['property_id' => $property->id]);
        $room = Room::factory()->create([
            'property_id' => $property->id,
            'room_type_id' => $type->id,
            'status' => RoomStatus::ACTIVE,
            'capacity' => 4,
        ]);
        $amenity = Amenity::factory()->create(['status' => false]);
        $room->amenities()->attach($amenity);
        $search = [
            'check_in_date' => now()->addDays(5)->toDateString(),
            'check_out_date' => now()->addDays(7)->toDateString(),
            'guest_count' => 2,
            'property_id' => $property->id,
        ];
        $path = '/api/public/rooms/search?';
        $this->getJson($path.http_build_query($search + ['amenity_ids' => [$amenity->id]]))
            ->assertUnprocessable()->assertJsonValidationErrors('amenity_ids.0');
        $this->getJson($path.http_build_query($search))->assertOk()->assertJsonPath('data.0.id', $room->id)
            ->assertJsonPath('data.0.amenities', []);

        $amenity->update(['status' => true]);
        $this->getJson($path.http_build_query($search + ['amenity_ids' => [$amenity->id]]))
            ->assertOk()->assertJsonPath('data.0.amenities.0.id', $amenity->id);
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

    public function test_pr10_03_room_search_validates_referenced_entities_via_service_repository(): void
    {
        $checkIn = now()->addDays(5)->format('Y-m-d');
        $checkOut = now()->addDays(7)->format('Y-m-d');

        // 1. Non-existent property_id returns 422 with validation error
        $resProp = $this->getJson('/api/public/rooms/search?' . http_build_query([
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'guest_count' => 2,
            'property_id' => 999999,
        ]));
        $resProp->assertStatus(422)
            ->assertJsonValidationErrors(['property_id'])
            ->assertJsonPath('errors.property_id.0', 'The selected property id is invalid.');

        // 2. Non-existent room_type_id returns 422 with validation error
        $resRoomType = $this->getJson('/api/public/rooms/search?' . http_build_query([
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'guest_count' => 2,
            'room_type_id' => 888888,
        ]));
        $resRoomType->assertStatus(422)
            ->assertJsonValidationErrors(['room_type_id'])
            ->assertJsonPath('errors.room_type_id.0', 'The selected room type id is invalid.');

        // 3. Non-existent amenity_ids returns 422 with validation error
        $resAmenity = $this->getJson('/api/public/rooms/search?' . http_build_query([
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'guest_count' => 2,
            'amenity_ids' => [777777],
        ]));
        $resAmenity->assertStatus(422)
            ->assertJsonValidationErrors(['amenity_ids.0']);
        $this->assertSame(
            'The selected amenity_ids.0 is invalid.',
            $resAmenity->json('errors')['amenity_ids.0'][0]
        );

        // 4. Combined non-existent reference IDs return all 422 validation errors together
        $resAll = $this->getJson('/api/public/rooms/search?' . http_build_query([
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'guest_count' => 2,
            'property_id' => 999999,
            'room_type_id' => 888888,
            'amenity_ids' => [777777],
        ]));
        $resAll->assertStatus(422)
            ->assertJsonValidationErrors(['property_id', 'room_type_id', 'amenity_ids.0']);

        // 5. Existing valid entities return 200 OK
        $property = Property::factory()->create(['status' => PropertyStatus::ACTIVE]);
        $roomType = RoomType::factory()->create(['property_id' => $property->id]);
        $amenity = Amenity::factory()->create();
        $room = Room::factory()->create([
            'property_id' => $property->id,
            'room_type_id' => $roomType->id,
            'status' => RoomStatus::ACTIVE,
            'capacity' => 4,
            'price_per_night' => '600000.00',
        ]);
        $room->amenities()->attach($amenity->id);

        $resValid = $this->getJson('/api/public/rooms/search?' . http_build_query([
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'guest_count' => 2,
            'property_id' => $property->id,
            'room_type_id' => $roomType->id,
            'amenity_ids' => [$amenity->id],
        ]));
        $resValid->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $room->id);
    }

    public function test_pr12_08_search_scientific_notation_returns_422_not_500(): void
    {
        $checkIn = now()->addDays(5)->format('Y-m-d');
        $checkOut = now()->addDays(7)->format('Y-m-d');

        // min_price=1e101 should return 422, not 500
        $responseMin = $this->getJson('/api/public/rooms/search?' . http_build_query([
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'guest_count' => 2,
            'min_price' => '1e101',
        ]));
        $responseMin->assertStatus(422)
            ->assertJsonValidationErrors(['min_price']);

        // max_price=1e101 should return 422, not 500
        $responseMax = $this->getJson('/api/public/rooms/search?' . http_build_query([
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'guest_count' => 2,
            'max_price' => '1e101',
        ]));
        $responseMax->assertStatus(422)
            ->assertJsonValidationErrors(['max_price']);
    }

    public function test_pr12_08_booking_preview_overflow_amount_returns_422_not_500(): void
    {
        $property = Property::factory()->create(['status' => PropertyStatus::ACTIVE]);
        $room = Room::factory()->create([
            'property_id' => $property->id,
            'status' => RoomStatus::ACTIVE,
            'capacity' => 4,
            'price_per_night' => '100000000.00', // 100 million VND / night
        ]);

        // 101 nights * 100,000,000.00 = 10,100,000,000.00 > MAX_MONEY (9,999,999,999.99)
        $checkIn = now()->addDays(1)->format('Y-m-d');
        $checkOut = now()->addDays(102)->format('Y-m-d'); // 101 nights

        $response = $this->postJson('/api/public/bookings/preview', [
            'room_id' => $room->id,
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'guest_count' => 2,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['check_out_date']);
    }

    public function test_pr12_03_search_supports_uc07_room_type_ids_and_min_rating(): void
    {
        $propertyA = Property::factory()->create(['status' => PropertyStatus::ACTIVE]);
        $propertyB = Property::factory()->create(['status' => PropertyStatus::ACTIVE]);

        $customer = User::factory()->customer()->create();

        $bookingA = Booking::factory()->create([
            'customer_id' => $customer->id,
            'status' => BookingStatus::COMPLETED,
        ]);
        $bookingB = Booking::factory()->create([
            'customer_id' => $customer->id,
            'status' => BookingStatus::COMPLETED,
        ]);

        Review::query()->create([
            'booking_id' => $bookingA->id,
            'customer_id' => $customer->id,
            'property_id' => $propertyA->id,
            'rating' => 5,
            'comment' => 'Tuyệt vời',
        ]);
        Review::query()->create([
            'booking_id' => $bookingB->id,
            'customer_id' => $customer->id,
            'property_id' => $propertyB->id,
            'rating' => 3,
            'comment' => 'Bình thường',
        ]);

        $type1 = RoomType::factory()->create(['property_id' => $propertyA->id]);
        $type2 = RoomType::factory()->create(['property_id' => $propertyA->id]);
        $type3 = RoomType::factory()->create(['property_id' => $propertyB->id]);

        $room1 = Room::factory()->create([
            'property_id' => $propertyA->id,
            'room_type_id' => $type1->id,
            'status' => RoomStatus::ACTIVE,
            'capacity' => 4,
            'price_per_night' => '500000.00',
        ]);
        $room2 = Room::factory()->create([
            'property_id' => $propertyA->id,
            'room_type_id' => $type2->id,
            'status' => RoomStatus::ACTIVE,
            'capacity' => 4,
            'price_per_night' => '600000.00',
        ]);
        $room3 = Room::factory()->create([
            'property_id' => $propertyB->id,
            'room_type_id' => $type3->id,
            'status' => RoomStatus::ACTIVE,
            'capacity' => 4,
            'price_per_night' => '400000.00',
        ]);

        $checkIn = now()->addDays(5)->format('Y-m-d');
        $checkOut = now()->addDays(7)->format('Y-m-d');

        // 1. Filter by room_type_ids array [type1, type2] -> rooms 1 and 2 returned
        $resTypes = $this->getJson('/api/public/rooms/search?' . http_build_query([
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'guest_count' => 2,
            'room_type_ids' => [$type1->id, $type2->id],
        ]));
        $resTypes->assertOk()
            ->assertJsonPath('meta.total', 2);

        // 2. Filter by min_rating 4.0 -> only propertyA's rooms (rating 4.8 >= 4.0), propertyB (3.5) excluded
        $resRating = $this->getJson('/api/public/rooms/search?' . http_build_query([
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'guest_count' => 2,
            'min_rating' => 4.0,
        ]));
        $resRating->assertOk()
            ->assertJsonPath('meta.total', 2);

        // 3. Filter combination: room_type_ids = [type1, type3] and min_rating = 4.0 -> only room1 (room3 excluded due to rating)
        $resCombo = $this->getJson('/api/public/rooms/search?' . http_build_query([
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'guest_count' => 2,
            'room_type_ids' => [$type1->id, $type3->id],
            'min_rating' => 4.0,
        ]));
        $resCombo->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $room1->id);

        // 4. Non-existent room_type_id in room_type_ids returns 422
        $resInvalid = $this->getJson('/api/public/rooms/search?' . http_build_query([
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'guest_count' => 2,
            'room_type_ids' => [999999],
        ]));
        $resInvalid->assertStatus(422)
            ->assertJsonValidationErrors(['room_type_ids.0']);
    }
}
