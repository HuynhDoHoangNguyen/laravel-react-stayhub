<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PropertyStatus;
use App\Enums\RoomStatus;
use App\Models\Booking;
use App\Models\Maintenance;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use App\Services\Booking\AvailabilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
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

    public function test_direct_workflow_rejects_a_customer_actor(): void
    {
        $host = User::factory()->host()->create();
        $customer = User::factory()->customer()->create();
        $property = Property::factory()->create(['host_id' => $host->id, 'status' => PropertyStatus::ACTIVE]);
        $room = Room::factory()->create(['property_id' => $property->id, 'capacity' => 4, 'status' => RoomStatus::ACTIVE]);
        $booking = Booking::factory()->pending()->create(['room_id' => $room->id]);

        $this->actingAs($customer);
        try {
            app(\App\Services\Booking\BookingWorkflowService::class)->confirm($booking, $customer);
            $this->fail('A customer must not invoke a Host workflow directly.');
        } catch (\Illuminate\Auth\Access\AuthorizationException $exception) {
            $this->assertSame(403, $exception->status());
        }
        $this->assertSame(BookingStatus::PENDING, Booking::findOrFail($booking->id)->status);
    }

    public function test_host_booking_policy_keeps_role_before_ownership_results(): void
    {
        $owner = User::factory()->host()->create();
        $otherHost = User::factory()->host()->create();
        $customer = User::factory()->customer()->create();
        $admin = User::factory()->admin()->create();
        $property = Property::factory()->create(['host_id' => $owner->id, 'status' => PropertyStatus::ACTIVE]);
        $room = Room::factory()->create(['property_id' => $property->id, 'capacity' => 4, 'status' => RoomStatus::ACTIVE]);
        $booking = Booking::factory()->pending()->create(['customer_id' => $customer->id, 'room_id' => $room->id]);
        $policy = new \App\Policies\BookingPolicy();

        $this->assertTrue($policy->manageHost($owner, $booking)->allowed());
        $this->assertSame(404, $policy->manageHost($otherHost, $booking)->status());
        $this->assertSame(403, $policy->manageHost($customer, $booking)->status());
        $this->assertSame(403, $policy->manageHost($admin, $booking)->status());
    }

    public function test_host_of_another_property_cannot_invoke_any_workflow_service_method(): void
    {
        $owner = User::factory()->host()->create();
        $otherHost = User::factory()->host()->create();
        $property = Property::factory()->create(['host_id' => $owner->id, 'status' => PropertyStatus::ACTIVE]);
        $room = Room::factory()->create(['property_id' => $property->id, 'capacity' => 4, 'status' => RoomStatus::ACTIVE]);
        $bookings = [
            'confirm' => Booking::factory()->pending()->create(['room_id' => $room->id]),
            'reject' => Booking::factory()->pending()->create(['room_id' => $room->id]),
            'checkIn' => Booking::factory()->confirmed()->create(['room_id' => $room->id]),
            'checkOut' => Booking::factory()->checkedIn()->create(['room_id' => $room->id]),
        ];
        $workflow = app(\App\Services\Booking\BookingWorkflowService::class);

        foreach ($bookings as $method => $booking) {
            $status = $booking->status;
            try {
                if ($method === 'reject') {
                    $workflow->reject($booking, $otherHost, 'reason');
                } else {
                    $workflow->{$method}($booking, $otherHost);
                }
                $this->fail("Expected {$method} to deny a Host from another property.");
            } catch (\Illuminate\Auth\Access\AuthorizationException $exception) {
                $this->assertSame(404, $exception->status());
            }
            $this->assertSame($status, Booking::findOrFail($booking->id)->status);
        }
    }

    public function test_valid_host_actor_can_call_each_workflow_service_method(): void
    {
        $host = User::factory()->host()->create();
        $makeBooking = function (string $status) use ($host): Booking {
            $property = Property::factory()->create(['host_id' => $host->id, 'status' => PropertyStatus::ACTIVE]);
            $room = Room::factory()->create(['property_id' => $property->id, 'capacity' => 4, 'status' => RoomStatus::ACTIVE]);

            return Booking::factory()->create(['room_id' => $room->id, 'status' => $status]);
        };
        $workflow = app(\App\Services\Booking\BookingWorkflowService::class);

        $this->assertSame(BookingStatus::CONFIRMED, $workflow->confirm($makeBooking(BookingStatus::PENDING->value), $host)->status);
        $this->assertSame(BookingStatus::REJECTED, $workflow->reject($makeBooking(BookingStatus::PENDING->value), $host)->status);
        $this->assertSame(BookingStatus::CHECKED_IN, $workflow->checkIn($makeBooking(BookingStatus::CONFIRMED->value), $host)->status);
        $this->assertSame(BookingStatus::COMPLETED, $workflow->checkOut($makeBooking(BookingStatus::CHECKED_IN->value), $host)->status);
    }

    public function test_workflow_authorization_precedes_transition_validation(): void
    {
        $owner = User::factory()->host()->create();
        $otherHost = User::factory()->host()->create();
        $customer = User::factory()->customer()->create();
        $property = Property::factory()->create(['host_id' => $owner->id, 'status' => PropertyStatus::ACTIVE]);
        $room = Room::factory()->create(['property_id' => $property->id, 'capacity' => 4, 'status' => RoomStatus::ACTIVE]);
        $booking = Booking::factory()->pending()->create(['room_id' => $room->id]);
        $workflow = app(\App\Services\Booking\BookingWorkflowService::class);

        foreach ([[$customer, 403], [$otherHost, 404]] as [$actor, $expectedStatus]) {
            try {
                $workflow->checkOut($booking, $actor);
                $this->fail('Authorization must fail before the invalid PENDING -> COMPLETED transition.');
            } catch (\Illuminate\Auth\Access\AuthorizationException $exception) {
                $this->assertSame($expectedStatus, $exception->status());
            }
        }
        $this->assertSame(BookingStatus::PENDING, Booking::findOrFail($booking->id)->status);
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

    public function test_host_list_paginates_filters_and_preserves_order(): void
    {
        $host = User::factory()->host()->create();
        $property = Property::factory()->create(['host_id' => $host->id, 'status' => PropertyStatus::ACTIVE]);
        $room = Room::factory()->create(['property_id' => $property->id, 'capacity' => 4, 'status' => RoomStatus::ACTIVE]);
        $older = Booking::factory()->pending()->create(['room_id' => $room->id]);
        $newer = Booking::factory()->create(['room_id' => $room->id, 'status' => BookingStatus::CONFIRMED]);
        $newer->forceFill(['created_at' => now()->addSecond()])->save();

        $this->actingAs($host)->getJson('/api/host/bookings?per_page=1&page=1&status=CONFIRMED')
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.current_page', 1)->assertJsonPath('data.0.id', $newer->id);
        $this->getJson('/api/host/bookings?per_page=1&page=2')
            ->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('data.0.id', $older->id);
    }

    public function test_host_cannot_view_another_hosts_booking_and_customer_is_forbidden(): void
    {
        $host = User::factory()->host()->create();
        $otherHost = User::factory()->host()->create();
        $property = Property::factory()->create(['host_id' => $otherHost->id, 'status' => PropertyStatus::ACTIVE]);
        $room = Room::factory()->create(['property_id' => $property->id, 'capacity' => 4, 'status' => RoomStatus::ACTIVE]);
        $booking = Booking::factory()->pending()->create(['room_id' => $room->id]);

        $this->actingAs($host)->getJson("/api/host/bookings/{$booking->id}")->assertNotFound();
        $this->actingAs(User::factory()->customer()->create())->getJson("/api/host/bookings/{$booking->id}")->assertForbidden();
    }

    public function test_confirm_succeeds_when_only_the_current_pending_booking_holds_the_room(): void
    {
        $host = User::factory()->host()->create();
        $property = Property::factory()->create(['host_id' => $host->id, 'status' => PropertyStatus::ACTIVE]);
        $room = Room::factory()->create(['property_id' => $property->id, 'capacity' => 4, 'status' => RoomStatus::ACTIVE]);
        $dates = ['check_in_date' => Carbon::now()->addDays(20)->format('Y-m-d'), 'check_out_date' => Carbon::now()->addDays(22)->format('Y-m-d')];
        $booking = Booking::factory()->pending()->create(['room_id' => $room->id, ...$dates]);

        $this->actingAs($host)->postJson("/api/host/bookings/{$booking->id}/confirm")
            ->assertOk()->assertJsonPath('data.status', BookingStatus::CONFIRMED->value);
    }

    #[DataProvider('holdingConflictStatusProvider')]
    public function test_confirm_is_rejected_when_another_holding_booking_overlaps(string $status): void
    {
        $host = User::factory()->host()->create();
        $property = Property::factory()->create(['host_id' => $host->id, 'status' => PropertyStatus::ACTIVE]);
        $room = Room::factory()->create(['property_id' => $property->id, 'capacity' => 4, 'status' => RoomStatus::ACTIVE]);
        $dates = ['check_in_date' => Carbon::now()->addDays(20)->format('Y-m-d'), 'check_out_date' => Carbon::now()->addDays(22)->format('Y-m-d')];
        Booking::factory()->create(['room_id' => $room->id, 'status' => BookingStatus::from($status), ...$dates]);
        $booking = Booking::factory()->pending()->create(['room_id' => $room->id, ...$dates]);

        $this->actingAs($host)->postJson("/api/host/bookings/{$booking->id}/confirm")
            ->assertStatus(409)->assertJsonPath('success', false)
            ->assertJsonPath('errors.reasons.0', 'BOOKING_OVERLAP');
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => BookingStatus::PENDING->value]);
    }

    public static function holdingConflictStatusProvider(): array
    {
        return ['confirmed' => [BookingStatus::CONFIRMED->value], 'checked in' => [BookingStatus::CHECKED_IN->value]];
    }

    public function test_confirm_rechecks_a_conflict_added_after_locking_booking(): void
    {
        $host = User::factory()->host()->create();
        $property = Property::factory()->create(['host_id' => $host->id, 'status' => PropertyStatus::ACTIVE]);
        $room = Room::factory()->create(['property_id' => $property->id, 'capacity' => 4, 'status' => RoomStatus::ACTIVE]);
        $dates = ['check_in_date' => Carbon::now()->addDays(25)->format('Y-m-d'), 'check_out_date' => Carbon::now()->addDays(27)->format('Y-m-d')];
        $booking = Booking::factory()->pending()->create(['room_id' => $room->id, ...$dates]);
        $injected = false;
        $bookingReads = 0;
        DB::listen(static function (QueryExecuted $query) use (&$injected, &$bookingReads, $room, $dates, $booking): void {
            $sql = strtolower($query->sql);
            if (str_contains($sql, 'bookings') && str_contains($sql, 'select')
                && in_array($booking->id, $query->bindings, false)) {
                $bookingReads++;
            }
            if (! $injected && $bookingReads >= 2) {
                $injected = true;
                Booking::factory()->create(['room_id' => $room->id, 'status' => BookingStatus::CONFIRMED, ...$dates]);
            }
        });

        $response = $this->actingAs($host)->postJson("/api/host/bookings/{$booking->id}/confirm");
        $this->assertTrue($injected, 'Expected to inject the conflicting booking after the booking lock query.');
        $response
            ->assertStatus(409)->assertJsonPath('success', false)
            ->assertJsonPath('errors.reasons.0', 'BOOKING_OVERLAP');
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => BookingStatus::PENDING->value]);
    }

    public function test_confirm_is_rejected_when_room_is_under_maintenance(): void
    {
        $host = User::factory()->host()->create();
        $property = Property::factory()->create(['host_id' => $host->id, 'status' => PropertyStatus::ACTIVE]);
        $room = Room::factory()->create(['property_id' => $property->id, 'capacity' => 4, 'status' => RoomStatus::ACTIVE]);
        $checkIn = Carbon::now()->addDays(30)->format('Y-m-d');
        $checkOut = Carbon::now()->addDays(32)->format('Y-m-d');
        $booking = Booking::factory()->pending()->create(['room_id' => $room->id, 'check_in_date' => $checkIn, 'check_out_date' => $checkOut]);

        Maintenance::factory()->create(['room_id' => $room->id, 'start_date' => $checkIn, 'end_date' => $checkOut]);
        $this->actingAs($host)->postJson("/api/host/bookings/{$booking->id}/confirm")
            ->assertStatus(409)->assertJsonPath('success', false)
            ->assertJsonPath('errors.reasons.0', 'MAINTENANCE_OVERLAP');
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => BookingStatus::PENDING->value]);
    }

    public function test_check_out_does_not_create_invoice(): void
    {
        $host = User::factory()->host()->create();
        $property = Property::factory()->create(['host_id' => $host->id, 'status' => PropertyStatus::ACTIVE]);
        $room = Room::factory()->create(['property_id' => $property->id, 'capacity' => 4, 'status' => RoomStatus::ACTIVE]);
        $booking = Booking::factory()->create(['room_id' => $room->id, 'status' => BookingStatus::CHECKED_IN]);
        $before = DB::table('invoices')->count();

        $this->actingAs($host)->postJson("/api/host/bookings/{$booking->id}/check-out")
            ->assertOk()->assertJsonPath('data.status', BookingStatus::COMPLETED->value);
        $this->assertSame($before, DB::table('invoices')->count());
    }

    public function test_failed_host_transition_rolls_back_booking_status(): void
    {
        $host = User::factory()->host()->create();
        $property = Property::factory()->create(['host_id' => $host->id, 'status' => PropertyStatus::ACTIVE]);
        $room = Room::factory()->create(['property_id' => $property->id, 'capacity' => 4, 'status' => RoomStatus::ACTIVE]);
        $booking = Booking::factory()->pending()->create(['room_id' => $room->id]);
        $shouldFail = true;
        DB::listen(static function (QueryExecuted $query) use (&$shouldFail): void {
            if ($shouldFail && str_starts_with(strtolower(ltrim($query->sql)), 'update') && str_contains(strtolower($query->sql), 'bookings')) {
                $shouldFail = false;
                throw new \RuntimeException('Forced host transition failure.');
            }
        });

        $this->withoutExceptionHandling();
        try {
            $this->actingAs($host)->postJson("/api/host/bookings/{$booking->id}/reject");
            $this->fail('Expected the forced transition failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Forced host transition failure.', $exception->getMessage());
        }

        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => BookingStatus::PENDING->value]);
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
