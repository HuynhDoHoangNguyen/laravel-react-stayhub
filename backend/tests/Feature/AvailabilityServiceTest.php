<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\MaintenanceStatus;
use App\Enums\PropertyStatus;
use App\Enums\RoomStatus;
use App\Models\Booking;
use App\Models\Maintenance;
use App\Models\Property;
use App\Models\Room;
use App\Services\AvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AvailabilityService $availabilityService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->availabilityService = new AvailabilityService();
    }

    public function test_room_not_active(): void
    {
        $room = Room::factory()->create(['status' => RoomStatus::INACTIVE]);

        $res = $this->availabilityService->check($room, '2026-10-10', '2026-10-12', 1);

        $this->assertFalse($res['available']);
        $this->assertContains('ROOM_NOT_ACTIVE', $res['reasons']);
    }

    public function test_property_not_active(): void
    {
        $property = Property::factory()->create(['status' => PropertyStatus::INACTIVE]);
        $room = Room::factory()->create(['property_id' => $property->id, 'status' => RoomStatus::ACTIVE]);

        $res = $this->availabilityService->check($room, '2026-10-10', '2026-10-12', 1);

        $this->assertFalse($res['available']);
        $this->assertContains('PROPERTY_NOT_ACTIVE', $res['reasons']);
    }

    public function test_capacity_exceeded(): void
    {
        $room = Room::factory()->create(['capacity' => 2, 'status' => RoomStatus::ACTIVE]);

        $res = $this->availabilityService->check($room, '2026-10-10', '2026-10-12', 5);

        $this->assertFalse($res['available']);
        $this->assertContains('CAPACITY_EXCEEDED', $res['reasons']);
    }

    public function test_holding_booking_overlaps(): void
    {
        $room = Room::factory()->create(['capacity' => 2, 'status' => RoomStatus::ACTIVE]);

        Booking::factory()->create([
            'room_id' => $room->id,
            'check_in_date' => '2026-10-10',
            'check_out_date' => '2026-10-15',
            'status' => BookingStatus::CONFIRMED,
        ]);

        $res = $this->availabilityService->check($room, '2026-10-12', '2026-10-14', 2);

        $this->assertFalse($res['available']);
        $this->assertContains('BOOKING_OVERLAP', $res['reasons']);
    }

    public function test_non_holding_booking_does_not_block(): void
    {
        $room = Room::factory()->create(['capacity' => 2, 'status' => RoomStatus::ACTIVE]);

        Booking::factory()->create([
            'room_id' => $room->id,
            'check_in_date' => '2026-10-10',
            'check_out_date' => '2026-10-15',
            'status' => BookingStatus::CANCELLED,
        ]);

        Booking::factory()->create([
            'room_id' => $room->id,
            'check_in_date' => '2026-10-10',
            'check_out_date' => '2026-10-15',
            'status' => BookingStatus::REJECTED,
        ]);

        Booking::factory()->create([
            'room_id' => $room->id,
            'check_in_date' => '2026-10-10',
            'check_out_date' => '2026-10-15',
            'status' => BookingStatus::COMPLETED,
        ]);

        $res = $this->availabilityService->check($room, '2026-10-10', '2026-10-15', 2);

        $this->assertTrue($res['available']);
        $this->assertEmpty($res['reasons']);
    }

    public function test_adjacent_dates_are_available(): void
    {
        $room = Room::factory()->create(['capacity' => 2, 'status' => RoomStatus::ACTIVE]);

        Booking::factory()->create([
            'room_id' => $room->id,
            'check_in_date' => '2026-10-10',
            'check_out_date' => '2026-10-15',
            'status' => BookingStatus::CONFIRMED,
        ]);

        // Checking 05-10 (checkout on 10)
        $resBefore = $this->availabilityService->check($room, '2026-10-05', '2026-10-10', 2);
        $this->assertTrue($resBefore['available']);

        // Checking 15-20 (checkin on 15)
        $resAfter = $this->availabilityService->check($room, '2026-10-15', '2026-10-20', 2);
        $this->assertTrue($resAfter['available']);
    }

    public function test_maintenance_overlap_blocks_room(): void
    {
        $room = Room::factory()->create(['capacity' => 2, 'status' => RoomStatus::ACTIVE]);

        Maintenance::factory()->create([
            'room_id' => $room->id,
            'start_date' => '2026-10-10',
            'end_date' => '2026-10-15',
            'status' => MaintenanceStatus::SCHEDULED,
        ]);

        $res = $this->availabilityService->check($room, '2026-10-12', '2026-10-14', 2);
        $this->assertFalse($res['available']);
        $this->assertContains('MAINTENANCE_OVERLAP', $res['reasons']);
    }

    public function test_cancelled_or_completed_maintenance_does_not_block(): void
    {
        $room = Room::factory()->create(['capacity' => 2, 'status' => RoomStatus::ACTIVE]);

        Maintenance::factory()->create([
            'room_id' => $room->id,
            'start_date' => '2026-10-10',
            'end_date' => '2026-10-15',
            'status' => MaintenanceStatus::CANCELLED,
        ]);

        Maintenance::factory()->create([
            'room_id' => $room->id,
            'start_date' => '2026-10-10',
            'end_date' => '2026-10-15',
            'status' => MaintenanceStatus::COMPLETED,
        ]);

        $res = $this->availabilityService->check($room, '2026-10-10', '2026-10-15', 2);
        $this->assertTrue($res['available']);
    }
}
