<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_scope_overlapping_cases(): void
    {
        // Existing booking: Oct 10 to Oct 15
        $existing = Booking::factory()->create([
            'check_in_date' => '2026-10-10',
            'check_out_date' => '2026-10-15',
            'status' => BookingStatus::CONFIRMED,
        ]);

        // 1. Exact match: Oct 10 to Oct 15 -> SHOULD OVERLAP
        $this->assertCount(1, Booking::overlapping('2026-10-10', '2026-10-15')->get());

        // 2. Partial overlap start: Oct 08 to Oct 12 -> SHOULD OVERLAP
        $this->assertCount(1, Booking::overlapping('2026-10-08', '2026-10-12')->get());

        // 3. Partial overlap end: Oct 13 to Oct 18 -> SHOULD OVERLAP
        $this->assertCount(1, Booking::overlapping('2026-10-13', '2026-10-18')->get());

        // 4. Contained inside: Oct 11 to Oct 14 -> SHOULD OVERLAP
        $this->assertCount(1, Booking::overlapping('2026-10-11', '2026-10-14')->get());

        // 5. Enclosing existing: Oct 08 to Oct 18 -> SHOULD OVERLAP
        $this->assertCount(1, Booking::overlapping('2026-10-08', '2026-10-18')->get());

        // 6. Adjacent before (checkout == checkin): Oct 05 to Oct 10 -> SHOULD NOT OVERLAP
        $this->assertCount(0, Booking::overlapping('2026-10-05', '2026-10-10')->get());

        // 7. Adjacent after (checkin == checkout): Oct 15 to Oct 20 -> SHOULD NOT OVERLAP
        $this->assertCount(0, Booking::overlapping('2026-10-15', '2026-10-20')->get());

        // 8. Completely before: Oct 01 to Oct 05 -> SHOULD NOT OVERLAP
        $this->assertCount(0, Booking::overlapping('2026-10-01', '2026-10-05')->get());

        // 9. Completely after: Oct 20 to Oct 25 -> SHOULD NOT OVERLAP
        $this->assertCount(0, Booking::overlapping('2026-10-20', '2026-10-25')->get());
    }

    public function test_scope_holding_room(): void
    {
        $pending = Booking::factory()->pending()->create();
        $confirmed = Booking::factory()->confirmed()->create();
        $checkedIn = Booking::factory()->checkedIn()->create();
        $completed = Booking::factory()->completed()->create();
        $cancelled = Booking::factory()->cancelled()->create();
        $rejected = Booking::factory()->rejected()->create();

        $holdingBookings = Booking::holdingRoom()->pluck('id')->toArray();

        $this->assertContains($pending->id, $holdingBookings);
        $this->assertContains($confirmed->id, $holdingBookings);
        $this->assertContains($checkedIn->id, $holdingBookings);

        $this->assertNotContains($completed->id, $holdingBookings);
        $this->assertNotContains($cancelled->id, $holdingBookings);
        $this->assertNotContains($rejected->id, $holdingBookings);
    }

    public function test_scope_for_customer_and_for_host(): void
    {
        $customer1 = User::factory()->customer()->create();
        $customer2 = User::factory()->customer()->create();

        $host1 = User::factory()->host()->create();
        $host2 = User::factory()->host()->create();

        $property1 = Property::factory()->create(['host_id' => $host1->id]);
        $property2 = Property::factory()->create(['host_id' => $host2->id]);

        $room1 = Room::factory()->create(['property_id' => $property1->id]);
        $room2 = Room::factory()->create(['property_id' => $property2->id]);

        $booking1 = Booking::factory()->create([
            'customer_id' => $customer1->id,
            'room_id' => $room1->id,
        ]);

        $booking2 = Booking::factory()->create([
            'customer_id' => $customer2->id,
            'room_id' => $room2->id,
        ]);

        $this->assertCount(1, Booking::forCustomer($customer1->id)->get());
        $this->assertEquals($booking1->id, Booking::forCustomer($customer1->id)->first()->id);

        $this->assertCount(1, Booking::forHost($host1->id)->get());
        $this->assertEquals($booking1->id, Booking::forHost($host1->id)->first()->id);

        $this->assertCount(1, Booking::forHost($host2->id)->get());
        $this->assertEquals($booking2->id, Booking::forHost($host2->id)->first()->id);
    }
}
