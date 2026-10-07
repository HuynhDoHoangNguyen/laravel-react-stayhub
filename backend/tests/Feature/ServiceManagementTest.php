<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PropertyStatus;
use App\Enums\RoomStatus;
use App\Models\Booking;
use App\Models\BookingService;
use App\Models\Property;
use App\Models\Room;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_cannot_call_host_service_api(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer)->getJson('/api/host/services')->assertStatus(403);
        $this->actingAs($customer)->postJson('/api/host/services', [])->assertStatus(403);
    }

    public function test_host_property_service_crud_and_ownership(): void
    {
        $host1 = User::factory()->host()->create();
        $host2 = User::factory()->host()->create();

        $prop1 = Property::factory()->create(['host_id' => $host1->id]);
        $prop2 = Property::factory()->create(['host_id' => $host2->id]);

        // Host 1 creates service for Prop 1 -> 201 Created
        $resStore = $this->actingAs($host1)->postJson('/api/host/services', [
            'property_id' => $prop1->id,
            'name' => 'Bữa sáng Buffet',
            'unit' => 'suất',
            'price' => 150000,
        ]);

        $resStore->assertStatus(201)
            ->assertJsonPath('data.name', 'Bữa sáng Buffet')
            ->assertJsonPath('data.price', '150000.00');

        $service1Id = $resStore->json('data.id');

        // Host 2 tries to create service for Prop 1 -> 404
        $this->actingAs($host2)->postJson('/api/host/services', [
            'property_id' => $prop1->id,
            'name' => 'Giặt ủi',
            'unit' => 'kg',
            'price' => 50000,
        ])->assertStatus(404);

        // Host 2 tries to update Host 1's service -> 404
        $this->actingAs($host2)->putJson("/api/host/services/{$service1Id}", [
            'name' => 'Giặt ủi Hack',
            'unit' => 'kg',
            'price' => 1000,
        ])->assertStatus(404);

        // Host 1 updates service -> 200
        $this->actingAs($host1)->putJson("/api/host/services/{$service1Id}", [
            'name' => 'Bữa sáng Đắc Biệt',
            'description' => 'Món Âu và Á',
            'unit' => 'suất',
            'price' => 180000,
        ])->assertStatus(200)
            ->assertJsonPath('data.name', 'Bữa sáng Đắc Biệt')
            ->assertJsonPath('data.price', '180000.00');

        // Host 1 disables service -> 200
        $this->actingAs($host1)->patchJson("/api/host/services/{$service1Id}/status", [
            'status' => false,
        ])->assertStatus(200)
            ->assertJsonPath('data.status', false);
    }

    public function test_add_service_to_checked_in_booking_snapshots_and_ignores_client_fake_price(): void
    {
        $host = User::factory()->host()->create();
        $prop = Property::factory()->create(['host_id' => $host->id, 'status' => PropertyStatus::ACTIVE]);
        $room = Room::factory()->create(['property_id' => $prop->id, 'capacity' => 4, 'status' => RoomStatus::ACTIVE]);
        $booking = Booking::factory()->checkedIn()->create(['room_id' => $room->id]);

        $service = Service::factory()->create([
            'property_id' => $prop->id,
            'name' => 'Giặt ủi',
            'unit' => 'kg',
            'price' => 50000,
            'status' => true,
        ]);

        $payload = [
            'service_id' => $service->id,
            'quantity' => 3.5,
            // Fake price inputs
            'unit_price' => 1,
            'amount' => 1,
        ];

        $res = $this->actingAs($host)->postJson("/api/host/bookings/{$booking->id}/services", $payload);

        $res->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'booking_id' => $booking->id,
                    'service_id' => $service->id,
                    'service_name' => 'Giặt ủi',
                    'unit' => 'kg',
                    'quantity' => '3.50',
                    'unit_price' => '50000.00',
                    'amount' => '175000.00', // 3.5 * 50000
                ],
            ]);

        // Price change on Service model in DB later does NOT affect existing booking_service snapshot
        $service->update(['price' => 999999]);

        $bsId = $res->json('data.id');
        $bsModel = BookingService::find($bsId);
        $this->assertEquals('50000.00', $bsModel->unit_price);
        $this->assertEquals('175000.00', $bsModel->amount);
    }

    public function test_cannot_add_disabled_service_or_service_from_another_property(): void
    {
        $host = User::factory()->host()->create();
        $prop1 = Property::factory()->create(['host_id' => $host->id, 'status' => PropertyStatus::ACTIVE]);
        $prop2 = Property::factory()->create(['host_id' => $host->id, 'status' => PropertyStatus::ACTIVE]);

        $room = Room::factory()->create(['property_id' => $prop1->id, 'capacity' => 4, 'status' => RoomStatus::ACTIVE]);
        $booking = Booking::factory()->checkedIn()->create(['room_id' => $room->id]);

        // Disabled service on prop1
        $disabledService = Service::factory()->inactive()->create(['property_id' => $prop1->id]);

        $this->actingAs($host)->postJson("/api/host/bookings/{$booking->id}/services", [
            'service_id' => $disabledService->id,
            'quantity' => 1,
        ])->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Dịch vụ này hiện đang tạm ngưng cung cấp.',
            ]);

        // Service from prop2
        $otherPropService = Service::factory()->create(['property_id' => $prop2->id, 'status' => true]);

        $this->actingAs($host)->postJson("/api/host/bookings/{$booking->id}/services", [
            'service_id' => $otherPropService->id,
            'quantity' => 1,
        ])->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Dịch vụ không thuộc cơ sở lưu trú của phòng này.',
            ]);
    }

    public function test_cannot_add_service_to_non_checked_in_booking(): void
    {
        $host = User::factory()->host()->create();
        $prop = Property::factory()->create(['host_id' => $host->id, 'status' => PropertyStatus::ACTIVE]);
        $room = Room::factory()->create(['property_id' => $prop->id, 'capacity' => 4, 'status' => RoomStatus::ACTIVE]);
        $service = Service::factory()->create(['property_id' => $prop->id, 'status' => true]);

        // PENDING booking
        $pendingBooking = Booking::factory()->pending()->create(['room_id' => $room->id]);

        $this->actingAs($host)->postJson("/api/host/bookings/{$pendingBooking->id}/services", [
            'service_id' => $service->id,
            'quantity' => 1,
        ])->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Chỉ được thêm dịch vụ khi đặt phòng ở trạng thái đang lưu trú (CHECKED_IN).',
            ]);
    }

    public function test_update_quantity_recalculates_amount_keeping_snapshot_price(): void
    {
        $host = User::factory()->host()->create();
        $prop = Property::factory()->create(['host_id' => $host->id, 'status' => PropertyStatus::ACTIVE]);
        $room = Room::factory()->create(['property_id' => $prop->id, 'capacity' => 4, 'status' => RoomStatus::ACTIVE]);
        $booking = Booking::factory()->checkedIn()->create(['room_id' => $room->id]);
        $service = Service::factory()->create(['property_id' => $prop->id, 'price' => 100000, 'status' => true]);

        $bs = BookingService::factory()->forService($service, 2.00)->create([
            'booking_id' => $booking->id,
        ]);

        // Service price changes in DB
        $service->update(['price' => 500000]);

        // Host updates quantity to 5
        $this->actingAs($host)->putJson("/api/host/bookings/{$booking->id}/services/{$bs->id}", [
            'quantity' => 5.00,
        ])->assertStatus(200)
            ->assertJsonPath('data.quantity', '5.00')
            ->assertJsonPath('data.unit_price', '100000.00') // Keeps original snapshot!
            ->assertJsonPath('data.amount', '500000.00');    // 5 * 100000
    }

    public function test_idor_protection_on_booking_services(): void
    {
        $host = User::factory()->host()->create();
        $prop = Property::factory()->create(['host_id' => $host->id, 'status' => PropertyStatus::ACTIVE]);
        $room = Room::factory()->create(['property_id' => $prop->id, 'capacity' => 4, 'status' => RoomStatus::ACTIVE]);

        $booking1 = Booking::factory()->checkedIn()->create(['room_id' => $room->id]);
        $booking2 = Booking::factory()->checkedIn()->create(['room_id' => $room->id]);

        $bs1 = BookingService::factory()->create(['booking_id' => $booking1->id]);

        // Trying to update bs1 under booking2 URL -> 404 IDOR
        $this->actingAs($host)->putJson("/api/host/bookings/{$booking2->id}/services/{$bs1->id}", [
            'quantity' => 10,
        ])->assertStatus(404);

        // Trying to delete bs1 under booking2 URL -> 404 IDOR
        $this->actingAs($host)->deleteJson("/api/host/bookings/{$booking2->id}/services/{$bs1->id}")
            ->assertStatus(404);
    }

    public function test_customer_uc14_and_services_total_with_fractional_prices(): void
    {
        $customer1 = User::factory()->customer()->create();
        $customer2 = User::factory()->customer()->create();

        $booking = Booking::factory()->create(['customer_id' => $customer1->id]);
        $service1 = Service::factory()->create(['price' => 120500.50]);
        $service2 = Service::factory()->create(['price' => 45000.25]);

        BookingService::factory()->forService($service1, 2.00)->create(['booking_id' => $booking->id]); // 241001.00
        BookingService::factory()->forService($service2, 1.00)->create(['booking_id' => $booking->id]); // 45000.25

        // Customer 1 views own services -> 200 OK
        $resCust = $this->actingAs($customer1)->getJson("/api/customer/bookings/{$booking->id}/services");
        $resCust->assertStatus(200)
            ->assertJsonCount(2, 'data');

        // Customer 1 views own booking show -> services_total matches 286001.25
        $resShow = $this->actingAs($customer1)->getJson("/api/customer/bookings/{$booking->id}");
        $resShow->assertStatus(200)
            ->assertJsonPath('data.services_total', '286001.25');

        // Customer 2 views Customer 1's services -> 404
        $this->actingAs($customer2)->getJson("/api/customer/bookings/{$booking->id}/services")
            ->assertStatus(404);
    }
}
