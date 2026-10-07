<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PropertyStatus;
use App\Enums\RoomStatus;
use App\Models\Booking;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use App\Services\AvailabilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HostBookingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_host_booking_routes(): void
    {
        $this->getJson('/api/host/bookings')->assertStatus(401);
    }

    public function test_customer_cannot_access_host_booking_routes(): void
    {
        $customer = User::factory()->customer()->create();
        $this->actingAs($customer)->getJson('/api/host/bookings')->assertStatus(403);
    }

    public function test_host_index_only_returns_own_properties_bookings(): void
    {
        $host1 = User::factory()->host()->create();
        $host2 = User::factory()->host()->create();

        $prop1 = Property::factory()->create(['host_id' => $host1->id, 'status' => PropertyStatus::ACTIVE]);
        $prop2 = Property::factory()->create(['host_id' => $host2->id, 'status' => PropertyStatus::ACTIVE]);

        $room1 = Room::factory()->create(['property_id' => $prop1->id, 'capacity' => 4, 'status' => RoomStatus::ACTIVE]);
        $room2 = Room::factory()->create(['property_id' => $prop2->id, 'capacity' => 4, 'status' => RoomStatus::ACTIVE]);

        $booking1 = Booking::factory()->create(['room_id' => $room1->id, 'guest_count' => 2]);
        $booking2 = Booking::factory()->create(['room_id' => $room2->id, 'guest_count' => 2]);

        $res1 = $this->actingAs($host1)->getJson('/api/host/bookings');
        $res1->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $booking1->id);

        $res2 = $this->actingAs($host2)->getJson('/api/host/bookings');
        $res2->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $booking2->id);
    }

    public function test_host_a_cannot_manage_host_b_booking(): void
    {
        $host1 = User::factory()->host()->create();
        $host2 = User::factory()->host()->create();

        $prop1 = Property::factory()->create(['host_id' => $host1->id, 'status' => PropertyStatus::ACTIVE]);
        $room1 = Room::factory()->create(['property_id' => $prop1->id, 'capacity' => 4, 'status' => RoomStatus::ACTIVE]);
        $booking = Booking::factory()->pending()->create(['room_id' => $room1->id, 'guest_count' => 2]);

        // Host 2 tries to confirm Host 1's booking -> 404
        $this->actingAs($host2)->postJson("/api/host/bookings/{$booking->id}/confirm")
            ->assertStatus(404);
    }

    public function test_confirming_twice_returns_422(): void
    {
        $host = User::factory()->host()->create();
        $prop = Property::factory()->create(['host_id' => $host->id, 'status' => PropertyStatus::ACTIVE]);
        $room = Room::factory()->create(['property_id' => $prop->id, 'capacity' => 4, 'status' => RoomStatus::ACTIVE]);
        $booking = Booking::factory()->pending()->create(['room_id' => $room->id, 'guest_count' => 2]);

        // First confirm -> 200 OK
        $this->actingAs($host)->postJson("/api/host/bookings/{$booking->id}/confirm")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'CONFIRMED');

        // Second confirm -> 422 Invalid Transition
        $this->actingAs($host)->postJson("/api/host/bookings/{$booking->id}/confirm")
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_full_successful_booking_lifecycle(): void
    {
        $host = User::factory()->host()->create();
        $prop = Property::factory()->create(['host_id' => $host->id, 'status' => PropertyStatus::ACTIVE]);
        $room = Room::factory()->create(['property_id' => $prop->id, 'capacity' => 4, 'status' => RoomStatus::ACTIVE]);
        $booking = Booking::factory()->pending()->create(['room_id' => $room->id, 'guest_count' => 2]);

        // 1. PENDING -> CONFIRMED
        $this->actingAs($host)->postJson("/api/host/bookings/{$booking->id}/confirm")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'CONFIRMED')
            ->assertJsonPath('data.status_label', 'Đã xác nhận');

        // 2. CONFIRMED -> CHECKED_IN
        $this->actingAs($host)->postJson("/api/host/bookings/{$booking->id}/check-in")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'CHECKED_IN')
            ->assertJsonPath('data.status_label', 'Đã nhận phòng');

        // 3. CHECKED_IN -> COMPLETED
        $this->actingAs($host)->postJson("/api/host/bookings/{$booking->id}/check-out")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'COMPLETED')
            ->assertJsonPath('data.status_label', 'Đã hoàn thành');
    }

    public function test_room_freed_after_completed_cancelled_or_rejected(): void
    {
        $availabilityService = app(AvailabilityService::class);
        $prop = Property::factory()->create(['status' => PropertyStatus::ACTIVE]);
        $room = Room::factory()->create(['property_id' => $prop->id, 'capacity' => 4, 'status' => RoomStatus::ACTIVE]);

        $checkIn = Carbon::now()->addDays(2)->format('Y-m-d');
        $checkOut = Carbon::now()->addDays(5)->format('Y-m-d');

        // PENDING -> holds room
        $booking = Booking::factory()->pending()->create([
            'room_id' => $room->id,
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'guest_count' => 2,
        ]);
        $this->assertFalse($availabilityService->check($room, $checkIn, $checkOut, 1)['available']);

        // REJECTED -> room freed
        $booking->update(['status' => BookingStatus::REJECTED]);
        $this->assertTrue($availabilityService->check($room, $checkIn, $checkOut, 1)['available']);

        // CANCELLED -> room freed
        $booking->update(['status' => BookingStatus::CANCELLED]);
        $this->assertTrue($availabilityService->check($room, $checkIn, $checkOut, 1)['available']);

        // COMPLETED -> room freed
        $booking->update(['status' => BookingStatus::COMPLETED]);
        $this->assertTrue($availabilityService->check($room, $checkIn, $checkOut, 1)['available']);
    }

    #[DataProvider('transitionMatrixProvider')]
    public function test_transition_matrix(string $fromStatus, string $toStatus, bool $shouldSucceed, ?string $endpoint = null): void
    {
        $host = User::factory()->host()->create();
        $prop = Property::factory()->create(['host_id' => $host->id, 'status' => PropertyStatus::ACTIVE]);
        $room = Room::factory()->create(['property_id' => $prop->id, 'capacity' => 4, 'status' => RoomStatus::ACTIVE]);

        $booking = Booking::factory()->create([
            'room_id' => $room->id,
            'status' => BookingStatus::from($fromStatus),
            'check_in_date' => Carbon::now()->addDays(5)->format('Y-m-d'),
            'check_out_date' => Carbon::now()->addDays(8)->format('Y-m-d'),
            'guest_count' => 2,
        ]);

        if (! $endpoint) {
            $endpoint = match ($toStatus) {
                'CONFIRMED' => 'confirm',
                'REJECTED' => 'reject',
                'CHECKED_IN' => 'check-in',
                'COMPLETED' => 'check-out',
                default => null,
            };
        }

        if (! $endpoint) {
            $this->markTestSkipped("No endpoint mapped for status {$toStatus}");
        }

        $response = $this->actingAs($host)->postJson("/api/host/bookings/{$booking->id}/{$endpoint}");

        if ($shouldSucceed) {
            $response->assertStatus(200)
                ->assertJsonPath('data.status', $toStatus);
        } else {
            $response->assertStatus(422)
                ->assertJson(['success' => false]);
        }
    }

    public static function transitionMatrixProvider(): array
    {
        $statuses = ['PENDING', 'CONFIRMED', 'CHECKED_IN', 'COMPLETED', 'CANCELLED', 'REJECTED'];
        $allowed = [
            'PENDING' => ['CONFIRMED', 'REJECTED'],
            'CONFIRMED' => ['CHECKED_IN'],
            'CHECKED_IN' => ['COMPLETED'],
            'COMPLETED' => [],
            'CANCELLED' => [],
            'REJECTED' => [],
        ];

        $matrix = [];
        foreach ($statuses as $from) {
            foreach (['CONFIRMED', 'REJECTED', 'CHECKED_IN', 'COMPLETED'] as $to) {
                $shouldSucceed = in_array($to, $allowed[$from], true);
                $matrix["{$from} -> {$to}"] = [$from, $to, $shouldSucceed];
            }
        }

        return $matrix;
    }
}
