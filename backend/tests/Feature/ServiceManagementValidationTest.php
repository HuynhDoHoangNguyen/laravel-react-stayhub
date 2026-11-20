<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PropertyStatus;
use App\Enums\RoomStatus;
use App\Models\Booking;
use App\Models\BookingService as BookingServiceModel;
use App\Models\Property;
use App\Models\Room;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceManagementValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_management_validation_baseline(): void
    {
        $host = User::factory()->host()->create([
            'email' => 'host_service_baseline@example.com',
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
            'email' => 'customer_service_baseline@example.com',
        ]);

        $service = Service::factory()->create([
            'property_id' => $property->id,
            'name' => 'Dịch vụ giặt ủi',
            'unit' => 'kg',
            'price' => '50000.00',
            'status' => true,
        ]);

        $booking = Booking::factory()->create([
            'room_id' => $room->id,
            'customer_id' => $customer->id,
            'check_in_date' => Carbon::now()->subDays(2)->format('Y-m-d'),
            'check_out_date' => Carbon::now()->addDays(2)->format('Y-m-d'),
            'guest_count' => 2,
            'status' => BookingStatus::CHECKED_IN,
        ]);

        $bookingService = BookingServiceModel::factory()->create([
            'booking_id' => $booking->id,
            'service_id' => $service->id,
            'service_name' => $service->name,
            'unit' => $service->unit,
            'quantity' => '2.00',
            'unit_price' => '50000.00',
            'amount' => '100000.00',
        ]);

        $payloads = [];

        // 1. StoreService: empty payload
        $res = $this->actingAs($host)->postJson('/api/host/services', []);
        $res->assertStatus(422);
        $payloads['store_service_empty'] = $res->json();

        // 2. StoreService: non-existent property_id
        $res = $this->actingAs($host)->postJson('/api/host/services', [
            'property_id' => 999999,
            'name' => 'Bữa sáng',
            'unit' => 'suất',
            'price' => 50000,
        ]);
        $res->assertStatus(422);
        $payloads['store_service_non_existent_property'] = $res->json();

        // 3. StoreService: missing name and unit
        $res = $this->actingAs($host)->postJson('/api/host/services', [
            'property_id' => $property->id,
            'price' => 50000,
        ]);
        $res->assertStatus(422);
        $payloads['store_service_missing_name_unit'] = $res->json();

        // 4. StoreService: negative price
        $res = $this->actingAs($host)->postJson('/api/host/services', [
            'property_id' => $property->id,
            'name' => 'Bữa sáng',
            'unit' => 'suất',
            'price' => -100,
        ]);
        $res->assertStatus(422);
        $payloads['store_service_negative_price'] = $res->json();

        // 5. StoreService: price exceeds 100000000
        $res = $this->actingAs($host)->postJson('/api/host/services', [
            'property_id' => $property->id,
            'name' => 'Bữa sáng',
            'unit' => 'suất',
            'price' => 100000001,
        ]);
        $res->assertStatus(422);
        $payloads['store_service_price_over_max'] = $res->json();

        // 6. StoreService: price not numeric
        $res = $this->actingAs($host)->postJson('/api/host/services', [
            'property_id' => $property->id,
            'name' => 'Bữa sáng',
            'unit' => 'suất',
            'price' => 'not-numeric',
        ]);
        $res->assertStatus(422);
        $payloads['store_service_non_numeric_price'] = $res->json();

        // 7. StoreService: status not boolean
        $res = $this->actingAs($host)->postJson('/api/host/services', [
            'property_id' => $property->id,
            'name' => 'Bữa sáng',
            'unit' => 'suất',
            'price' => 50000,
            'status' => 'not-a-bool',
        ]);
        $res->assertStatus(422);
        $payloads['store_service_non_boolean_status'] = $res->json();

        // 8. UpdateService: empty payload
        $res = $this->actingAs($host)->putJson("/api/host/services/{$service->id}", []);
        $res->assertStatus(422);
        $payloads['update_service_empty'] = $res->json();

        // 9. UpdateService: negative price
        $res = $this->actingAs($host)->putJson("/api/host/services/{$service->id}", [
            'name' => 'Giặt nhanh',
            'unit' => 'kg',
            'price' => -50,
        ]);
        $res->assertStatus(422);
        $payloads['update_service_negative_price'] = $res->json();

        // 10. UpdateService: price over max
        $res = $this->actingAs($host)->putJson("/api/host/services/{$service->id}", [
            'name' => 'Giặt nhanh',
            'unit' => 'kg',
            'price' => 100000001,
        ]);
        $res->assertStatus(422);
        $payloads['update_service_price_over_max'] = $res->json();

        // 11. UpdateService: price not numeric
        $res = $this->actingAs($host)->putJson("/api/host/services/{$service->id}", [
            'name' => 'Giặt nhanh',
            'unit' => 'kg',
            'price' => 'abc',
        ]);
        $res->assertStatus(422);
        $payloads['update_service_non_numeric_price'] = $res->json();

        // 12. SetServiceStatus: empty payload
        $res = $this->actingAs($host)->patchJson("/api/host/services/{$service->id}/status", []);
        $res->assertStatus(422);
        $payloads['set_status_empty'] = $res->json();

        // 13. SetServiceStatus: status not boolean
        $res = $this->actingAs($host)->patchJson("/api/host/services/{$service->id}/status", [
            'status' => 'invalid-bool',
        ]);
        $res->assertStatus(422);
        $payloads['set_status_non_boolean'] = $res->json();

        // 14. AddBookingService: empty payload
        $res = $this->actingAs($host)->postJson("/api/host/bookings/{$booking->id}/services", []);
        $res->assertStatus(422);
        $payloads['add_booking_service_empty'] = $res->json();

        // 15. AddBookingService: non-existent service_id
        $res = $this->actingAs($host)->postJson("/api/host/bookings/{$booking->id}/services", [
            'service_id' => 999999,
            'quantity' => 1,
        ]);
        $res->assertStatus(422);
        $payloads['add_booking_service_non_existent_service'] = $res->json();

        // 16. AddBookingService: quantity 0
        $res = $this->actingAs($host)->postJson("/api/host/bookings/{$booking->id}/services", [
            'service_id' => $service->id,
            'quantity' => 0,
        ]);
        $res->assertStatus(422);
        $payloads['add_booking_service_quantity_zero'] = $res->json();

        // 17. AddBookingService: quantity negative
        $res = $this->actingAs($host)->postJson("/api/host/bookings/{$booking->id}/services", [
            'service_id' => $service->id,
            'quantity' => -1,
        ]);
        $res->assertStatus(422);
        $payloads['add_booking_service_quantity_negative'] = $res->json();

        // 18. AddBookingService: quantity over max (10000)
        $res = $this->actingAs($host)->postJson("/api/host/bookings/{$booking->id}/services", [
            'service_id' => $service->id,
            'quantity' => 10000,
        ]);
        $res->assertStatus(422);
        $payloads['add_booking_service_quantity_over_max'] = $res->json();

        // 19. AddBookingService: quantity below min (0.001)
        $res = $this->actingAs($host)->postJson("/api/host/bookings/{$booking->id}/services", [
            'service_id' => $service->id,
            'quantity' => 0.001,
        ]);
        $res->assertStatus(422);
        $payloads['add_booking_service_quantity_below_min'] = $res->json();

        // 20. UpdateBookingService: empty payload
        $res = $this->actingAs($host)->putJson("/api/host/bookings/{$booking->id}/services/{$bookingService->id}", []);
        $res->assertStatus(422);
        $payloads['update_booking_service_empty'] = $res->json();

        // 21. UpdateBookingService: quantity 0
        $res = $this->actingAs($host)->putJson("/api/host/bookings/{$booking->id}/services/{$bookingService->id}", [
            'quantity' => 0,
        ]);
        $res->assertStatus(422);
        $payloads['update_booking_service_quantity_zero'] = $res->json();

        // 22. UpdateBookingService: quantity negative
        $res = $this->actingAs($host)->putJson("/api/host/bookings/{$booking->id}/services/{$bookingService->id}", [
            'quantity' => -1,
        ]);
        $res->assertStatus(422);
        $payloads['update_booking_service_quantity_negative'] = $res->json();

        // 23. UpdateBookingService: quantity over max (10000)
        $res = $this->actingAs($host)->putJson("/api/host/bookings/{$booking->id}/services/{$bookingService->id}", [
            'quantity' => 10000,
        ]);
        $res->assertStatus(422);
        $payloads['update_booking_service_quantity_over_max'] = $res->json();

        // 24. UpdateBookingService: quantity below min (0.001)
        $res = $this->actingAs($host)->putJson("/api/host/bookings/{$booking->id}/services/{$bookingService->id}", [
            'quantity' => 0.001,
        ]);
        $res->assertStatus(422);
        $payloads['update_booking_service_quantity_below_min'] = $res->json();

        $baselineFile = base_path('tests/Fixtures/service_management_validation_baseline.json');
        if (! file_exists($baselineFile)) {
            if (! is_dir(dirname($baselineFile))) {
                mkdir(dirname($baselineFile), 0755, true);
            }
            file_put_contents($baselineFile, json_encode($payloads, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        $baseline = json_decode(file_get_contents($baselineFile), true);
        $this->assertSame($baseline, $payloads, 'Service management validation errors must match baseline exactly');
    }

    public function test_service_management_error_order_hierarchy(): void
    {
        $hostA = User::factory()->host()->create([
            'email' => 'host_a_order@example.com',
        ]);
        $propertyA = Property::factory()->create([
            'host_id' => $hostA->id,
            'status' => PropertyStatus::ACTIVE,
        ]);
        $roomA = Room::factory()->create([
            'property_id' => $propertyA->id,
            'status' => RoomStatus::ACTIVE,
            'capacity' => 2,
            'price_per_night' => 500000,
        ]);
        $serviceA = Service::factory()->create([
            'property_id' => $propertyA->id,
            'name' => 'Dịch vụ A',
            'unit' => 'lần',
            'price' => '20000.00',
            'status' => true,
        ]);
        $bookingA = Booking::factory()->create([
            'room_id' => $roomA->id,
            'customer_id' => User::factory()->customer()->create()->id,
            'check_in_date' => Carbon::now()->subDays(2)->format('Y-m-d'),
            'check_out_date' => Carbon::now()->addDays(2)->format('Y-m-d'),
            'guest_count' => 2,
            'status' => BookingStatus::CHECKED_IN,
        ]);
        $bookingServiceA = BookingServiceModel::factory()->create([
            'booking_id' => $bookingA->id,
            'service_id' => $serviceA->id,
            'service_name' => $serviceA->name,
            'unit' => $serviceA->unit,
            'quantity' => '1.00',
            'unit_price' => '20000.00',
            'amount' => '20000.00',
        ]);

        $hostB = User::factory()->host()->create([
            'email' => 'host_b_order@example.com',
        ]);
        $propertyB = Property::factory()->create([
            'host_id' => $hostB->id,
            'status' => PropertyStatus::ACTIVE,
        ]);
        $roomB = Room::factory()->create([
            'property_id' => $propertyB->id,
            'status' => RoomStatus::ACTIVE,
            'capacity' => 2,
            'price_per_night' => 600000,
        ]);
        $serviceB = Service::factory()->create([
            'property_id' => $propertyB->id,
            'name' => 'Dịch vụ B',
            'unit' => 'lần',
            'price' => '30000.00',
            'status' => true,
        ]);
        $bookingB = Booking::factory()->create([
            'room_id' => $roomB->id,
            'customer_id' => User::factory()->customer()->create()->id,
            'check_in_date' => Carbon::now()->subDays(2)->format('Y-m-d'),
            'check_out_date' => Carbon::now()->addDays(2)->format('Y-m-d'),
            'guest_count' => 2,
            'status' => BookingStatus::CHECKED_IN,
        ]);
        $bookingServiceB = BookingServiceModel::factory()->create([
            'booking_id' => $bookingB->id,
            'service_id' => $serviceB->id,
            'service_name' => $serviceB->name,
            'unit' => $serviceB->unit,
            'quantity' => '1.00',
            'unit_price' => '30000.00',
            'amount' => '30000.00',
        ]);

        // (a) payload sai + tài nguyên của host khác -> 422
        // UpdateService: Host A calls PUT /api/host/services/{serviceB} with INVALID payload
        $res = $this->actingAs($hostA)->putJson("/api/host/services/{$serviceB->id}", [
            'price' => -10,
        ]);
        $res->assertStatus(422);

        // SetServiceStatus: Host A calls PATCH /api/host/services/{serviceB}/status with INVALID payload
        $res = $this->actingAs($hostA)->patchJson("/api/host/services/{$serviceB->id}/status", [
            'status' => 'not-bool',
        ]);
        $res->assertStatus(422);

        // AddBookingService: Host A calls POST /api/host/bookings/{bookingB}/services with INVALID payload
        $res = $this->actingAs($hostA)->postJson("/api/host/bookings/{$bookingB->id}/services", [
            'quantity' => -1,
        ]);
        $res->assertStatus(422);

        // UpdateBookingService: Host A calls PUT /api/host/bookings/{bookingB}/services/{bookingServiceB} with INVALID payload
        $res = $this->actingAs($hostA)->putJson("/api/host/bookings/{$bookingB->id}/services/{$bookingServiceB->id}", [
            'quantity' => -1,
        ]);
        $res->assertStatus(422);

        // (b) payload hợp lệ + tài nguyên của host khác -> 404
        // StoreService: Host A creates service with property_id belonging to Host B
        $res = $this->actingAs($hostA)->postJson('/api/host/services', [
            'property_id' => $propertyB->id,
            'name' => 'Dịch vụ chui',
            'unit' => 'lần',
            'price' => 10000,
        ]);
        $res->assertStatus(404);

        // UpdateService: Host A calls PUT /api/host/services/{serviceB} with VALID payload
        $res = $this->actingAs($hostA)->putJson("/api/host/services/{$serviceB->id}", [
            'name' => 'Dịch vụ hợp lệ',
            'unit' => 'lần',
            'price' => 10000,
        ]);
        $res->assertStatus(404);

        // SetServiceStatus: Host A calls PATCH /api/host/services/{serviceB}/status with VALID payload
        $res = $this->actingAs($hostA)->patchJson("/api/host/services/{$serviceB->id}/status", [
            'status' => true,
        ]);
        $res->assertStatus(404);

        // AddBookingService: Host A calls POST /api/host/bookings/{bookingB}/services with VALID payload
        $res = $this->actingAs($hostA)->postJson("/api/host/bookings/{$bookingB->id}/services", [
            'service_id' => $serviceA->id,
            'quantity' => 1,
        ]);
        $res->assertStatus(404);

        // UpdateBookingService: Host A calls PUT /api/host/bookings/{bookingB}/services/{bookingServiceB} with VALID payload
        $res = $this->actingAs($hostA)->putJson("/api/host/bookings/{$bookingB->id}/services/{$bookingServiceB->id}", [
            'quantity' => 2,
        ]);
        $res->assertStatus(404);

        // (c) payload sai + tài nguyên của chính host -> 422
        $res = $this->actingAs($hostA)->postJson('/api/host/services', [
            'property_id' => $propertyA->id,
            'price' => -100,
        ]);
        $res->assertStatus(422);

        $res = $this->actingAs($hostA)->putJson("/api/host/services/{$serviceA->id}", [
            'price' => -100,
        ]);
        $res->assertStatus(422);

        $res = $this->actingAs($hostA)->patchJson("/api/host/services/{$serviceA->id}/status", [
            'status' => 'not-bool',
        ]);
        $res->assertStatus(422);

        $res = $this->actingAs($hostA)->postJson("/api/host/bookings/{$bookingA->id}/services", [
            'quantity' => -1,
        ]);
        $res->assertStatus(422);

        $res = $this->actingAs($hostA)->putJson("/api/host/bookings/{$bookingA->id}/services/{$bookingServiceA->id}", [
            'quantity' => -1,
        ]);
        $res->assertStatus(422);

        // (d) route lồng sai quan hệ (booking A với booking_service của B) -> 404 (with valid payload)
        $res = $this->actingAs($hostA)->putJson("/api/host/bookings/{$bookingA->id}/services/{$bookingServiceB->id}", [
            'quantity' => 2,
        ]);
        $res->assertStatus(404);

        // And if payload is invalid on mismatched nested route -> currently 422 because FormRequest runs first!
        $res = $this->actingAs($hostA)->putJson("/api/host/bookings/{$bookingA->id}/services/{$bookingServiceB->id}", [
            'quantity' => -1,
        ]);
        $res->assertStatus(422);
    }

    public function test_service_boolean_status_variants(): void
    {
        $host = User::factory()->host()->create([
            'email' => 'host_boolean_variants@example.com',
        ]);
        $property = Property::factory()->create([
            'host_id' => $host->id,
            'status' => PropertyStatus::ACTIVE,
        ]);

        // 1. StoreService with null/omitted status -> default true
        $res = $this->actingAs($host)->postJson('/api/host/services', [
            'property_id' => $property->id,
            'name' => 'Dịch vụ mặc định',
            'unit' => 'lần',
            'price' => 50000,
        ]);
        $res->assertStatus(201);
        $this->assertTrue($res->json('data.status'));

        // 2. StoreService with explicit boolean variants: true, false, 1, 0, "1", "0"
        $variants = [
            ['input' => true, 'expected' => true],
            ['input' => false, 'expected' => false],
            ['input' => 1, 'expected' => true],
            ['input' => 0, 'expected' => false],
            ['input' => '1', 'expected' => true],
            ['input' => '0', 'expected' => false],
        ];

        foreach ($variants as $idx => $v) {
            $res = $this->actingAs($host)->postJson('/api/host/services', [
                'property_id' => $property->id,
                'name' => "Dịch vụ {$idx}",
                'unit' => 'lần',
                'price' => 50000,
                'status' => $v['input'],
            ]);
            $res->assertStatus(201);
            $this->assertSame($v['expected'], (bool) $res->json('data.status'));
        }

        $service = Service::where('property_id', $property->id)->first();

        // 3. SetServiceStatus with explicit boolean variants: true, false, 1, 0, "1", "0"
        foreach ($variants as $v) {
            $res = $this->actingAs($host)->patchJson("/api/host/services/{$service->id}/status", [
                'status' => $v['input'],
            ]);
            $res->assertStatus(200);
            $this->assertSame($v['expected'], (bool) $res->json('data.status'));
        }
    }
}
