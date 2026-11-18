<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\RoomStatus;
use App\Models\Booking;
use App\Models\BookingService;
use App\Models\Maintenance;
use App\Models\Room;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Ghi chú: Khóa thật (lockForUpdate) chạy trên MySQL, SQLite testrunner chỉ kiểm tra logic tuần tự.
 */
class CustomerBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_customer_routes(): void
    {
        $response = $this->postJson('/api/customer/bookings', []);
        $response->assertStatus(401);
    }

    public function test_user_with_wrong_role_cannot_access_customer_routes(): void
    {
        $host = User::factory()->host()->create();

        $response = $this->actingAs($host)->getJson('/api/customer/bookings');
        $response->assertStatus(403)
            ->assertJson(['message' => 'Bạn không có quyền truy cập.']);
    }

    public function test_booking_creation_success_snapshots_price_and_ignores_fake_client_prices(): void
    {
        $customer = User::factory()->customer()->create();
        $room = Room::factory()->create([
            'price_per_night' => 800000,
            'capacity' => 4,
            'status' => RoomStatus::ACTIVE,
        ]);

        $checkIn = Carbon::now()->addDays(2)->format('Y-m-d');
        $checkOut = Carbon::now()->addDays(5)->format('Y-m-d'); // 3 nights

        $payload = [
            'room_id' => $room->id,
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'guest_count' => 2,
            'note' => 'Cần phòng tầng cao',

            // Fake price inputs from client
            'price_per_night' => 100,
            'room_total' => 300,
        ];

        $response = $this->actingAs($customer)->postJson('/api/customer/bookings', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'customer_id' => $customer->id,
                    'room_id' => $room->id,
                    'guest_count' => 2,
                    'number_of_nights' => 3,
                    'price_per_night' => '800000.00',
                    'room_total' => '2400000.00',
                    'status' => 'PENDING',
                    'status_label' => 'Chờ xác nhận',
                    'note' => 'Cần phòng tầng cao',
                ],
            ]);

        // Changing room price in DB later should NOT affect old booking
        $room->update(['price_per_night' => 1500000]);

        $bookingId = $response->json('data.id');
        $booking = Booking::find($bookingId);

        $this->assertEquals('800000.00', $booking->price_per_night);
        $this->assertEquals('2400000.00', $booking->room_total);
    }

    public function test_booking_creation_validation_errors(): void
    {
        $customer = User::factory()->customer()->create();
        $roomInactive = Room::factory()->create(['capacity' => 2, 'status' => RoomStatus::INACTIVE]);

        // 1. CheckOut <= CheckIn
        $resDate = $this->actingAs($customer)->postJson('/api/customer/bookings', [
            'room_id' => $roomInactive->id,
            'check_in_date' => '2026-10-15',
            'check_out_date' => '2026-10-10',
            'guest_count' => 1,
        ]);
        $resDate->assertStatus(422)->assertJsonValidationErrors(['check_out_date']);

        // 2. Room INACTIVE
        $roomActive = Room::factory()->create(['capacity' => 2, 'status' => RoomStatus::ACTIVE]);
        $resInactive = $this->actingAs($customer)->postJson('/api/customer/bookings', [
            'room_id' => $roomInactive->id,
            'check_in_date' => Carbon::now()->addDays(1)->format('Y-m-d'),
            'check_out_date' => Carbon::now()->addDays(3)->format('Y-m-d'),
            'guest_count' => 1,
        ]);
        $resInactive->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'Phòng không khả dụng trong thời gian yêu cầu.',
                'errors' => [
                    'reasons' => ['ROOM_NOT_ACTIVE'],
                ],
            ]);

        // 3. Capacity exceeded
        $resCap = $this->actingAs($customer)->postJson('/api/customer/bookings', [
            'room_id' => $roomActive->id,
            'check_in_date' => Carbon::now()->addDays(1)->format('Y-m-d'),
            'check_out_date' => Carbon::now()->addDays(3)->format('Y-m-d'),
            'guest_count' => 10,
        ]);
        $resCap->assertStatus(409)
            ->assertJson([
                'success' => false,
                'errors' => [
                    'reasons' => ['CAPACITY_EXCEEDED'],
                ],
            ]);
    }

    public function test_overlapping_booking_returns_409_and_can_rebook_after_cancelled(): void
    {
        $customer1 = User::factory()->customer()->create();
        $customer2 = User::factory()->customer()->create();
        $room = Room::factory()->create(['capacity' => 2, 'status' => RoomStatus::ACTIVE]);

        $checkIn = Carbon::now()->addDays(2)->format('Y-m-d');
        $checkOut = Carbon::now()->addDays(5)->format('Y-m-d');

        // Customer 1 books room
        $res1 = $this->actingAs($customer1)->postJson('/api/customer/bookings', [
            'room_id' => $room->id,
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'guest_count' => 1,
        ]);
        $res1->assertStatus(201);
        $booking1Id = $res1->json('data.id');

        // Customer 2 tries to book overlapping dates -> 409 Conflict
        $res2 = $this->actingAs($customer2)->postJson('/api/customer/bookings', [
            'room_id' => $room->id,
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'guest_count' => 1,
        ]);
        $res2->assertStatus(409)
            ->assertJson([
                'success' => false,
                'errors' => [
                    'reasons' => ['BOOKING_OVERLAP'],
                ],
            ]);

        // Customer 1 cancels booking
        $this->actingAs($customer1)->postJson("/api/customer/bookings/{$booking1Id}/cancel")
            ->assertStatus(200);

        // Now Customer 2 can book the room successfully
        $res3 = $this->actingAs($customer2)->postJson('/api/customer/bookings', [
            'room_id' => $room->id,
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'guest_count' => 1,
        ]);
        $res3->assertStatus(201);
    }

    public function test_booking_conflicting_with_maintenance_returns_409_with_reason(): void
    {
        $customer = User::factory()->customer()->create();
        $room = Room::factory()->create(['capacity' => 2, 'status' => RoomStatus::ACTIVE]);
        $checkIn = Carbon::now()->addDays(10)->format('Y-m-d');
        $checkOut = Carbon::now()->addDays(13)->format('Y-m-d');
        Maintenance::factory()->create([
            'room_id' => $room->id,
            'start_date' => $checkIn,
            'end_date' => $checkOut,
        ]);

        $this->actingAs($customer)->postJson('/api/customer/bookings', [
            'room_id' => $room->id,
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'guest_count' => 1,
        ])->assertConflict()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Phòng không khả dụng trong thời gian yêu cầu.')
            ->assertJsonPath('errors.reasons.0', 'MAINTENANCE_OVERLAP');
    }

    public function test_booking_insert_failure_rolls_back_the_created_row(): void
    {
        $customer = User::factory()->customer()->create();
        $room = Room::factory()->create(['capacity' => 2, 'status' => RoomStatus::ACTIVE]);
        $shouldFail = true;
        DB::listen(static function (QueryExecuted $query) use (&$shouldFail): void {
            if ($shouldFail && str_starts_with(strtolower(ltrim($query->sql)), 'insert')
                && str_contains(strtolower($query->sql), 'bookings')) {
                $shouldFail = false;
                throw new RuntimeException('Forced failure after booking insert.');
            }
        });

        $this->withoutExceptionHandling();
        try {
            $this->actingAs($customer)->postJson('/api/customer/bookings', [
                'room_id' => $room->id,
                'check_in_date' => Carbon::now()->addDays(10)->format('Y-m-d'),
                'check_out_date' => Carbon::now()->addDays(12)->format('Y-m-d'),
                'guest_count' => 1,
            ]);
            $this->fail('Expected the forced post-insert failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Forced failure after booking insert.', $exception->getMessage());
        }

        $this->assertDatabaseMissing('bookings', ['room_id' => $room->id]);
    }

    public function test_ownership_protection(): void
    {
        $customer1 = User::factory()->customer()->create();
        $customer2 = User::factory()->customer()->create();

        $booking = Booking::factory()->create(['customer_id' => $customer1->id]);

        // Customer 2 tries to view Customer 1's booking -> 404 Not Found
        $responseView = $this->actingAs($customer2)->getJson("/api/customer/bookings/{$booking->id}");
        $responseView->assertStatus(404);

        // Customer 2 tries to cancel Customer 1's booking -> 404 Not Found
        $responseCancel = $this->actingAs($customer2)->postJson("/api/customer/bookings/{$booking->id}/cancel");
        $responseCancel->assertStatus(404);
    }

    public function test_cancellation_rules_and_lead_time_limit(): void
    {
        $customer = User::factory()->customer()->create();

        // 1. PENDING: always allowed to cancel
        $pendingBooking = Booking::factory()->pending()->create([
            'customer_id' => $customer->id,
            'check_in_date' => Carbon::now()->addDays(5)->format('Y-m-d'),
        ]);

        $this->actingAs($customer)->postJson("/api/customer/bookings/{$pendingBooking->id}/cancel")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'CANCELLED');

        // 2. CONFIRMED with check_in_date far in future: allowed
        $confirmedFuture = Booking::factory()->confirmed()->create([
            'customer_id' => $customer->id,
            'check_in_date' => Carbon::now()->addDays(3)->format('Y-m-d'),
        ]);

        $this->actingAs($customer)->postJson("/api/customer/bookings/{$confirmedFuture->id}/cancel")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'CANCELLED');

        // 3. CONFIRMED with check_in_date today (past cancel_before_days lead time): disallowed -> 422
        $confirmedToday = Booking::factory()->confirmed()->create([
            'customer_id' => $customer->id,
            'check_in_date' => Carbon::now()->format('Y-m-d'),
        ]);

        $this->actingAs($customer)->postJson("/api/customer/bookings/{$confirmedToday->id}/cancel")
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Đã qua thời hạn hủy phòng cho phép.',
            ]);

        // 4. CHECKED_IN or COMPLETED: disallowed -> 422
        $checkedInBooking = Booking::factory()->checkedIn()->create([
            'customer_id' => $customer->id,
        ]);

        $this->actingAs($customer)->postJson("/api/customer/bookings/{$checkedInBooking->id}/cancel")
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_history_filtering_and_pagination(): void
    {
        $customer = User::factory()->customer()->create();

        $bookingPending = Booking::factory()->pending()->create(['customer_id' => $customer->id]);
        $bookingCompleted = Booking::factory()->completed()->create(['customer_id' => $customer->id]);

        // Filter status = PENDING
        $resFilter = $this->actingAs($customer)->getJson('/api/customer/bookings?status=PENDING');
        $resFilter->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $bookingPending->id);

        // View details
        $resShow = $this->actingAs($customer)->getJson("/api/customer/bookings/{$bookingPending->id}");
        $resShow->assertStatus(200)
            ->assertJsonPath('data.id', $bookingPending->id);
    }

    public function test_customer_list_is_scoped_ordered_filtered_and_paginated_with_real_meta(): void
    {
        $customer = User::factory()->customer()->create();
        $otherCustomer = User::factory()->customer()->create();
        $older = Booking::factory()->pending()->create([
            'customer_id' => $customer->id,
            'created_at' => Carbon::now()->subDays(3),
        ]);
        $middle = Booking::factory()->confirmed()->create([
            'customer_id' => $customer->id,
            'created_at' => Carbon::now()->subDays(2),
        ]);
        $newer = Booking::factory()->pending()->create([
            'customer_id' => $customer->id,
            'created_at' => Carbon::now()->subDay(),
        ]);
        Booking::factory()->pending()->create([
            'customer_id' => $otherCustomer->id,
            'created_at' => Carbon::now(),
        ]);

        $pageOne = $this->actingAs($customer)->getJson('/api/customer/bookings?page=1&per_page=2')
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $middle->id)
            ->assertJsonMissingPath('links');

        $this->assertCount(2, $pageOne->json('data'));
        $this->getJson('/api/customer/bookings?page=2&per_page=2')
            ->assertOk()
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('data.0.id', $older->id);

        $this->getJson('/api/customer/bookings?status=PENDING&page=1&per_page=5')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id);
    }

    public function test_customer_detail_includes_service_snapshots_and_decimal_booking_prices(): void
    {
        $customer = User::factory()->customer()->create();
        $booking = Booking::factory()->pending()->create([
            'customer_id' => $customer->id,
            'price_per_night' => '123456.78',
            'room_total' => '370370.34',
            'number_of_nights' => 3,
        ]);
        $service = Service::factory()->create(['price' => '62500.25']);
        $bookingService = BookingService::factory()->forService($service, '2.00')->create([
            'booking_id' => $booking->id,
            'unit_price' => '62500.25',
            'amount' => '125000.50',
        ]);

        $this->actingAs($customer)->getJson("/api/customer/bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $booking->id)
            ->assertJsonPath('data.price_per_night', '123456.78')
            ->assertJsonPath('data.room_total', '370370.34')
            ->assertJsonPath('data.services_total', '125000.50')
            ->assertJsonPath('data.booking_services.0.id', $bookingService->id)
            ->assertJsonPath('data.booking_services.0.service_name', $bookingService->service_name)
            ->assertJsonPath('data.booking_services.0.quantity', '2.00')
            ->assertJsonPath('data.booking_services.0.unit_price', '62500.25')
            ->assertJsonPath('data.booking_services.0.amount', '125000.50');
    }

    public function test_customer_detail_and_cancel_keep_authentication_and_role_statuses(): void
    {
        $customer = User::factory()->customer()->create();
        $host = User::factory()->host()->create();
        $booking = Booking::factory()->pending()->create(['customer_id' => $customer->id]);

        $this->getJson("/api/customer/bookings/{$booking->id}")->assertUnauthorized();
        $this->postJson("/api/customer/bookings/{$booking->id}/cancel")->assertUnauthorized();

        $this->actingAs($host)->getJson("/api/customer/bookings/{$booking->id}")
            ->assertForbidden()
            ->assertJson(['message' => 'Bạn không có quyền truy cập.']);
        $this->actingAs($host)->postJson("/api/customer/bookings/{$booking->id}/cancel")
            ->assertForbidden()
            ->assertJson(['message' => 'Bạn không có quyền truy cập.']);
    }

    public function test_cancel_rejects_every_non_cancellable_status_with_existing_422_shape(): void
    {
        $customer = User::factory()->customer()->create();
        $labels = [
            BookingStatus::CHECKED_IN->value => 'Đã nhận phòng',
            BookingStatus::COMPLETED->value => 'Đã hoàn thành',
            BookingStatus::CANCELLED->value => 'Đã hủy',
            BookingStatus::REJECTED->value => 'Từ chối',
        ];

        foreach ($labels as $status => $label) {
            $booking = Booking::factory()->create([
                'customer_id' => $customer->id,
                'status' => $status,
            ]);

            $this->actingAs($customer)->postJson("/api/customer/bookings/{$booking->id}/cancel")
                ->assertUnprocessable()
                ->assertJson([
                    'success' => false,
                    'message' => "Không thể chuyển trạng thái đặt phòng từ {$label} sang Đã hủy.",
                    'errors' => [],
                ]);
            $this->assertSame($status, Booking::findOrFail($booking->id)->status->value);
        }
    }

    public function test_confirmed_booking_can_be_cancelled_at_exact_one_day_deadline(): void
    {
        $customer = User::factory()->customer()->create();
        $booking = Booking::factory()->confirmed()->create([
            'customer_id' => $customer->id,
            'check_in_date' => Carbon::now()->addDay()->format('Y-m-d'),
        ]);

        $this->actingAs($customer)->postJson("/api/customer/bookings/{$booking->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'CANCELLED');
    }

    public function test_booking_service_cancel_uses_the_supplied_customer_actor(): void
    {
        $owner = User::factory()->customer()->create();
        $other = User::factory()->customer()->create();
        $booking = Booking::factory()->pending()->create(['customer_id' => $owner->id]);
        $service = app(\App\Services\Booking\BookingService::class);

        try {
            $service->cancel($booking, $other);
            $this->fail('A customer cannot cancel another customer booking through the service.');
        } catch (\Illuminate\Auth\Access\AuthorizationException $exception) {
            $this->assertSame(404, $exception->status());
        }
        $this->assertSame(BookingStatus::PENDING, Booking::findOrFail($booking->id)->status);

        $cancelled = $service->cancel($booking, $owner);
        $this->assertSame(BookingStatus::CANCELLED, $cancelled->status);
    }

    public function test_cancel_failure_after_update_rolls_back_and_repeat_cancel_keeps_valid_state(): void
    {
        $customer = User::factory()->customer()->create();
        $booking = Booking::factory()->pending()->create(['customer_id' => $customer->id]);
        $shouldFail = true;
        DB::listen(static function (QueryExecuted $query) use (&$shouldFail): void {
            if ($shouldFail && str_starts_with(strtolower(ltrim($query->sql)), 'update')
                && str_contains(strtolower($query->sql), 'bookings')) {
                $shouldFail = false;
                throw new RuntimeException('Forced failure after cancellation update.');
            }
        });

        $this->withoutExceptionHandling();
        try {
            $this->actingAs($customer)->postJson("/api/customer/bookings/{$booking->id}/cancel");
            $this->fail('Expected the forced post-update failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Forced failure after cancellation update.', $exception->getMessage());
        }
        $this->assertSame(BookingStatus::PENDING, Booking::findOrFail($booking->id)->status);
        $this->withExceptionHandling();

        $this->postJson("/api/customer/bookings/{$booking->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'CANCELLED');
        $this->postJson("/api/customer/bookings/{$booking->id}/cancel")
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors', []);
        $this->assertSame(BookingStatus::CANCELLED, Booking::findOrFail($booking->id)->status);
    }

    public function test_view_booking_services(): void
    {
        $customer = User::factory()->customer()->create();
        $booking = Booking::factory()->create(['customer_id' => $customer->id]);
        $service = Service::factory()->create(['price' => 100000]);

        $bs = BookingService::factory()->forService($service, 2.00)->create([
            'booking_id' => $booking->id,
        ]);

        $resServices = $this->actingAs($customer)->getJson("/api/customer/bookings/{$booking->id}/services");
        $resServices->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    [
                        'id' => $bs->id,
                        'booking_id' => $booking->id,
                        'quantity' => '2.00',
                        'unit_price' => '100000.00',
                        'amount' => '200000.00',
                    ],
                ],
            ]);
    }
}
