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
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BookingQueryCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Model::preventLazyLoading(false);
        parent::tearDown();
    }

    private static int $fixtureCounter = 0;

    /**
     * Helper to create a complete Host fixture.
     */
    private function createHostFixture(): array
    {
        self::$fixtureCounter++;
        $idx = self::$fixtureCounter;
        $host = User::factory()->host()->create([
            'name' => "Host Owner {$idx}",
            'email' => "host_owner_{$idx}@example.com",
            'phone' => '0901234567',
        ]);
        $property = Property::factory()->create([
            'host_id' => $host->id,
            'name' => "Host Villa {$idx}",
            'address' => '123 Nguyen Trai, Ha Noi',
            'phone' => '0901234567',
            'status' => PropertyStatus::ACTIVE,
        ]);
        $room = Room::factory()->create([
            'property_id' => $property->id,
            'room_number' => '10' . ($idx % 100),
            'name' => 'Deluxe Suite',
            'price_per_night' => 1000000,
            'capacity' => 4,
            'status' => RoomStatus::ACTIVE,
        ]);

        return compact('host', 'property', 'room');
    }

    /**
     * Helper to create a Customer fixture.
     */
    private function createCustomerFixture(): User
    {
        self::$fixtureCounter++;
        $idx = self::$fixtureCounter;
        return User::factory()->customer()->create([
            'name' => "Nguyen Van {$idx}",
            'email' => "customer_{$idx}@example.com",
            'phone' => '0912345678',
        ]);
    }

    private function normalizeDynamicFields(array $data): array
    {
        $normalized = [];
        foreach ($data as $key => $val) {
            if (in_array($key, ['id', 'booking_code', 'created_at', 'updated_at', 'room_id', 'customer_id', 'property_id', 'service_id', 'booking_id'], true)) {
                $normalized[$key] = '<DYNAMIC>';
            } elseif (is_array($val)) {
                $normalized[$key] = $this->normalizeDynamicFields($val);
            } else {
                $normalized[$key] = $val;
            }
        }
        return $normalized;
    }

    public function test_json_baseline_comparison(): void
    {
        self::$fixtureCounter = 0;
        $fixture = $this->createHostFixture();
        $host = $fixture['host'];
        $room = $fixture['room'];
        $customer = $this->createCustomerFixture();

        // 1. Customer store
        $storeRes = $this->actingAs($customer)->postJson('/api/customer/bookings', [
            'room_id' => $room->id,
            'check_in_date' => '2026-12-10',
            'check_out_date' => '2026-12-12',
            'guest_count' => 2,
            'note' => 'Fixed note',
        ]);
        $storeData = $this->normalizeDynamicFields($storeRes->json());
        $bookingId = $storeRes->json('data.id');

        // 2. Customer index
        $custIndexRes = $this->actingAs($customer)->getJson('/api/customer/bookings');
        $custIndexData = $this->normalizeDynamicFields($custIndexRes->json());

        // 3. Customer show
        $custShowRes = $this->actingAs($customer)->getJson("/api/customer/bookings/{$bookingId}");
        $custShowData = $this->normalizeDynamicFields($custShowRes->json());

        // 4. Host index
        $hostIndexRes = $this->actingAs($host)->getJson('/api/host/bookings');
        $hostIndexData = $this->normalizeDynamicFields($hostIndexRes->json());

        // 5. Host show
        $hostShowRes = $this->actingAs($host)->getJson("/api/host/bookings/{$bookingId}");
        $hostShowData = $this->normalizeDynamicFields($hostShowRes->json());

        // 6. Host confirm
        $confirmRes = $this->actingAs($host)->postJson("/api/host/bookings/{$bookingId}/confirm");
        $confirmData = $this->normalizeDynamicFields($confirmRes->json());

        // 7. Customer cancel (on a confirmed booking)
        $cancelBooking = Booking::factory()->confirmed()->create([
            'customer_id' => $customer->id,
            'room_id' => $room->id,
            'check_in_date' => '2026-12-20',
            'check_out_date' => '2026-12-22',
            'guest_count' => 2,
            'number_of_nights' => 2,
            'price_per_night' => '1000000.00',
            'room_total' => '2000000.00',
            'note' => 'To be cancelled',
        ]);
        $cancelRes = $this->actingAs($customer)->postJson("/api/customer/bookings/{$cancelBooking->id}/cancel");
        $cancelData = $this->normalizeDynamicFields($cancelRes->json());

        // 8. Host checkIn
        $this->travelTo(now(config('app.timezone'))->setDate(2026, 12, 10)->setTime(12, 0));
        $checkInRes = $this->actingAs($host)->postJson("/api/host/bookings/{$bookingId}/check-in");
        $checkInData = $this->normalizeDynamicFields($checkInRes->json());

        // Add service to booking
        $service = Service::factory()->create([
            'property_id' => $fixture['property']->id,
            'name' => 'An sang buffet',
            'unit' => 'phan',
            'price' => 150000,
        ]);
        BookingService::create([
            'booking_id' => $bookingId,
            'service_id' => $service->id,
            'service_name' => 'An sang buffet',
            'unit' => 'phan',
            'quantity' => '2.00',
            'unit_price' => '150000.00',
            'amount' => '300000.00',
        ]);

        // 9. Customer services
        $custServicesRes = $this->actingAs($customer)->getJson("/api/customer/bookings/{$bookingId}/services");
        $custServicesData = $this->normalizeDynamicFields($custServicesRes->json());

        // 10. Host checkOut
        $checkOutRes = $this->actingAs($host)->postJson("/api/host/bookings/{$bookingId}/check-out");
        $checkOutData = $this->normalizeDynamicFields($checkOutRes->json());

        // 11. Host reject (on another pending booking)
        $rejectBooking = Booking::factory()->pending()->create([
            'customer_id' => $customer->id,
            'room_id' => $room->id,
            'check_in_date' => '2026-12-25',
            'check_out_date' => '2026-12-27',
            'guest_count' => 2,
            'number_of_nights' => 2,
            'price_per_night' => '1000000.00',
            'room_total' => '2000000.00',
            'note' => 'To be rejected',
        ]);
        $rejectRes = $this->actingAs($host)->postJson("/api/host/bookings/{$rejectBooking->id}/reject", [
            'reason' => 'Bao tri dot xuat',
        ]);
        $rejectData = $this->normalizeDynamicFields($rejectRes->json());

        $currentPayloads = [
            'customer_store' => $storeData,
            'customer_index' => $custIndexData,
            'customer_show' => $custShowData,
            'customer_cancel' => $cancelData,
            'customer_services' => $custServicesData,
            'host_index' => $hostIndexData,
            'host_show' => $hostShowData,
            'host_confirm' => $confirmData,
            'host_check_in' => $checkInData,
            'host_check_out' => $checkOutData,
            'host_reject' => $rejectData,
        ];

        $baselineFile = base_path('tests/Fixtures/booking_query_baseline.json');
        if (! file_exists($baselineFile)) {
            if (! is_dir(dirname($baselineFile))) {
                mkdir(dirname($baselineFile), 0755, true);
            }
            file_put_contents($baselineFile, json_encode($currentPayloads, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        $baseline = json_decode(file_get_contents($baselineFile), true);
        $this->assertSame($baseline, $currentPayloads, 'Current API responses must match baseline JSON exactly');
    }

    public function test_customer_and_host_response_shapes_and_services_total_presence(): void
    {
        $fixture = $this->createHostFixture();
        $host = $fixture['host'];
        $room = $fixture['room'];
        $customer = $this->createCustomerFixture();

        // 1. Customer store
        $storeRes = $this->actingAs($customer)->postJson('/api/customer/bookings', [
            'room_id' => $room->id,
            'check_in_date' => Carbon::now()->addDays(2)->format('Y-m-d'),
            'check_out_date' => Carbon::now()->addDays(4)->format('Y-m-d'),
            'guest_count' => 2,
            'note' => 'Store note',
        ]);
        $storeRes->assertStatus(201);
        $storeData = $storeRes->json('data');
        $this->assertArrayNotHasKey('services_total', $storeData, 'Customer store must NOT have services_total');
        $this->assertArrayHasKey('room', $storeData);
        $this->assertArrayNotHasKey('booking_services', $storeData, 'Customer store currently does not load booking_services');

        $pendingBookingId = $storeData['id'];
        $pendingBooking = Booking::findOrFail($pendingBookingId);

        // 2. Customer index
        $custIndexRes = $this->actingAs($customer)->getJson('/api/customer/bookings');
        $custIndexRes->assertStatus(200);
        $custIndexData = $custIndexRes->json('data');
        $this->assertNotEmpty($custIndexData);
        $this->assertArrayNotHasKey('services_total', $custIndexData[0], 'Customer index items must NOT have services_total');
        $this->assertArrayHasKey('meta', $custIndexRes->json());
        $this->assertSame(1, $custIndexRes->json('meta.current_page'));

        // 3. Customer show
        $custShowRes = $this->actingAs($customer)->getJson("/api/customer/bookings/{$pendingBookingId}");
        $custShowRes->assertStatus(200);
        $custShowData = $custShowRes->json('data');
        $this->assertArrayHasKey('services_total', $custShowData, 'Customer show MUST have services_total');
        $this->assertSame('0.00', $custShowData['services_total']);

        // 4. Host index
        $hostIndexRes = $this->actingAs($host)->getJson('/api/host/bookings');
        $hostIndexRes->assertStatus(200);
        $hostIndexData = $hostIndexRes->json('data');
        $this->assertNotEmpty($hostIndexData);
        $this->assertArrayNotHasKey('services_total', $hostIndexData[0], 'Host index items must NOT have services_total');
        $this->assertArrayHasKey('meta', $hostIndexRes->json());

        // 5. Host show
        $hostShowRes = $this->actingAs($host)->getJson("/api/host/bookings/{$pendingBookingId}");
        $hostShowRes->assertStatus(200);
        $hostShowData = $hostShowRes->json('data');
        $this->assertArrayHasKey('services_total', $hostShowData, 'Host show MUST have services_total');
        $this->assertSame('0.00', $hostShowData['services_total']);

        // 6. Host confirm
        $confirmRes = $this->actingAs($host)->postJson("/api/host/bookings/{$pendingBookingId}/confirm");
        $confirmRes->assertStatus(200);
        $confirmData = $confirmRes->json('data');
        $this->assertSame('CONFIRMED', $confirmData['status']);
        $this->assertArrayHasKey('services_total', $confirmData, 'Host confirm MUST have services_total');
        $this->assertSame('0.00', $confirmData['services_total']);

        // 7. Customer cancel (on another confirmed booking)
        $cancelBooking = Booking::factory()->confirmed()->create([
            'customer_id' => $customer->id,
            'room_id' => $room->id,
            'check_in_date' => Carbon::now()->addDays(10)->format('Y-m-d'),
            'check_out_date' => Carbon::now()->addDays(12)->format('Y-m-d'),
        ]);
        $cancelRes = $this->actingAs($customer)->postJson("/api/customer/bookings/{$cancelBooking->id}/cancel");
        $cancelRes->assertStatus(200);
        $cancelData = $cancelRes->json('data');
        $this->assertSame('CANCELLED', $cancelData['status']);
        $this->assertArrayNotHasKey('services_total', $cancelData, 'Customer cancel must NOT have services_total');

        // 8. Host checkIn
        $this->travelTo($pendingBooking->check_in_date->copy()->setTime(12, 0));
        $checkInRes = $this->actingAs($host)->postJson("/api/host/bookings/{$pendingBookingId}/check-in");
        $checkInRes->assertStatus(200);
        $checkInData = $checkInRes->json('data');
        $this->assertSame('CHECKED_IN', $checkInData['status']);
        $this->assertArrayHasKey('services_total', $checkInData, 'Host checkIn MUST have services_total');
        $this->assertSame('0.00', $checkInData['services_total']);

        // Add service to checked-in booking
        $service = Service::factory()->create([
            'property_id' => $fixture['property']->id,
            'name' => 'Giat la',
            'price' => 50000,
        ]);
        BookingService::create([
            'booking_id' => $pendingBookingId,
            'service_id' => $service->id,
            'service_name' => 'Giat la',
            'unit' => 'kg',
            'quantity' => '2.00',
            'unit_price' => '50000.00',
            'amount' => '100000.00',
        ]);

        // 9. Customer services
        $custServicesRes = $this->actingAs($customer)->getJson("/api/customer/bookings/{$pendingBookingId}/services");
        $custServicesRes->assertStatus(200);
        $this->assertCount(1, $custServicesRes->json('data'));
        $this->assertSame('100000.00', $custServicesRes->json('data.0.amount'));

        // Host show after service added
        $hostShowWithServiceRes = $this->actingAs($host)->getJson("/api/host/bookings/{$pendingBookingId}");
        $this->assertSame('100000.00', $hostShowWithServiceRes->json('data.services_total'));

        // 10. Host checkOut
        $checkOutRes = $this->actingAs($host)->postJson("/api/host/bookings/{$pendingBookingId}/check-out");
        $checkOutRes->assertStatus(200);
        $checkOutData = $checkOutRes->json('data');
        $this->assertSame('COMPLETED', $checkOutData['status']);
        $this->assertArrayHasKey('services_total', $checkOutData, 'Host checkOut MUST have services_total');
        $this->assertSame('100000.00', $checkOutData['services_total']);

        // 11. Host reject (on another pending booking)
        $rejectBooking = Booking::factory()->pending()->create([
            'customer_id' => $customer->id,
            'room_id' => $room->id,
            'check_in_date' => Carbon::now()->addDays(20)->format('Y-m-d'),
            'check_out_date' => Carbon::now()->addDays(22)->format('Y-m-d'),
        ]);
        $rejectRes = $this->actingAs($host)->postJson("/api/host/bookings/{$rejectBooking->id}/reject", [
            'reason' => 'Bao tri dot xuat',
        ]);
        $rejectRes->assertStatus(200);
        $rejectData = $rejectRes->json('data');
        $this->assertSame('REJECTED', $rejectData['status']);
        $this->assertArrayHasKey('services_total', $rejectData, 'Host reject MUST have services_total');
        $this->assertSame('0.00', $rejectData['services_total']);
    }

    public function test_host_list_per_page_edge_cases_and_pagination_meta(): void
    {
        $fixture = $this->createHostFixture();
        $host = $fixture['host'];
        $customer = $this->createCustomerFixture();

        // Create 3 bookings
        for ($i = 0; $i < 3; $i++) {
            Booking::factory()->create([
                'customer_id' => $customer->id,
                'room_id' => $fixture['room']->id,
                'check_in_date' => Carbon::now()->addDays(10 + $i * 3)->format('Y-m-d'),
                'check_out_date' => Carbon::now()->addDays(12 + $i * 3)->format('Y-m-d'),
            ]);
        }

        // per_page = 0 -> default 15
        $res0 = $this->actingAs($host)->getJson('/api/host/bookings?per_page=0');
        $res0->assertStatus(200);
        $this->assertSame(15, $res0->json('meta.per_page'));

        // per_page = -5 -> default 15
        $resNeg = $this->actingAs($host)->getJson('/api/host/bookings?per_page=-5');
        $resNeg->assertStatus(200);
        $this->assertSame(15, $resNeg->json('meta.per_page'));

        // per_page = abc -> default 15
        $resAbc = $this->actingAs($host)->getJson('/api/host/bookings?per_page=abc');
        $resAbc->assertStatus(200);
        $this->assertSame(15, $resAbc->json('meta.per_page'));

        // per_page = 100 -> clamped to 50
        $res100 = $this->actingAs($host)->getJson('/api/host/bookings?per_page=100');
        $res100->assertStatus(200);
        $this->assertSame(50, $res100->json('meta.per_page'));

        // per_page = 2 -> 2 items on page 1, total 3, last_page 2
        $res2 = $this->actingAs($host)->getJson('/api/host/bookings?per_page=2');
        $res2->assertStatus(200);
        $this->assertSame(2, $res2->json('meta.per_page'));
        $this->assertSame(1, $res2->json('meta.current_page'));
        $this->assertSame(2, $res2->json('meta.last_page'));
        $this->assertSame(3, $res2->json('meta.total'));
        $this->assertCount(2, $res2->json('data'));

        // page 999 -> empty data, current_page 999
        $res999 = $this->actingAs($host)->getJson('/api/host/bookings?per_page=2&page=999');
        $res999->assertStatus(200);
        $this->assertEmpty($res999->json('data'));
        $this->assertSame(999, $res999->json('meta.current_page'));
        $this->assertSame(3, $res999->json('meta.total'));
    }

    public function test_customer_list_per_page_edge_cases_and_pagination_meta(): void
    {
        $fixture = $this->createHostFixture();
        $customer = $this->createCustomerFixture();

        // Create 2 bookings for customer
        for ($i = 0; $i < 2; $i++) {
            Booking::factory()->create([
                'customer_id' => $customer->id,
                'room_id' => $fixture['room']->id,
                'check_in_date' => Carbon::now()->addDays(10 + $i * 3)->format('Y-m-d'),
                'check_out_date' => Carbon::now()->addDays(12 + $i * 3)->format('Y-m-d'),
            ]);
        }

        // per_page = 0 -> default 15
        $res0 = $this->actingAs($customer)->getJson('/api/customer/bookings?per_page=0');
        $res0->assertStatus(200);
        $this->assertSame(15, $res0->json('meta.per_page'));

        // per_page = -10 -> default 15
        $resNeg = $this->actingAs($customer)->getJson('/api/customer/bookings?per_page=-10');
        $resNeg->assertStatus(200);
        $this->assertSame(15, $resNeg->json('meta.per_page'));

        // per_page = 99 -> clamped to 50
        $res99 = $this->actingAs($customer)->getJson('/api/customer/bookings?per_page=99');
        $res99->assertStatus(200);
        $this->assertSame(50, $res99->json('meta.per_page'));

        // page 999 -> empty data
        $res999 = $this->actingAs($customer)->getJson('/api/customer/bookings?page=999');
        $res999->assertStatus(200);
        $this->assertEmpty($res999->json('data'));
        $this->assertSame(999, $res999->json('meta.current_page'));
    }

    public function test_host_list_filtering_and_scope(): void
    {
        $fixture1 = $this->createHostFixture();
        $host1 = $fixture1['host'];

        $fixture2 = $this->createHostFixture();
        $host2 = $fixture2['host'];

        $customer = $this->createCustomerFixture();

        // Host 1 bookings
        $b1 = Booking::factory()->pending()->create([
            'customer_id' => $customer->id,
            'room_id' => $fixture1['room']->id,
            'booking_code' => 'BKHOST1A',
            'check_in_date' => '2026-12-01',
            'check_out_date' => '2026-12-03',
        ]);
        $b2 = Booking::factory()->confirmed()->create([
            'customer_id' => $customer->id,
            'room_id' => $fixture1['room']->id,
            'booking_code' => 'BKHOST1B',
            'check_in_date' => '2026-12-05',
            'check_out_date' => '2026-12-07',
        ]);

        // Host 2 booking (must NOT appear in Host 1 list)
        Booking::factory()->pending()->create([
            'customer_id' => $customer->id,
            'room_id' => $fixture2['room']->id,
            'booking_code' => 'BKHOST2A',
        ]);

        // 1. Host 1 sees exactly 2 bookings
        $res = $this->actingAs($host1)->getJson('/api/host/bookings');
        $res->assertStatus(200);
        $this->assertSame(2, $res->json('meta.total'));

        // 2. Filter status PENDING
        $resPending = $this->actingAs($host1)->getJson('/api/host/bookings?status=PENDING');
        $resPending->assertStatus(200);
        $this->assertSame(1, $resPending->json('meta.total'));
        $this->assertSame('BKHOST1A', $resPending->json('data.0.booking_code'));

        // 3. Filter property_id
        $resProp = $this->actingAs($host1)->getJson("/api/host/bookings?property_id={$fixture1['property']->id}");
        $resProp->assertStatus(200);
        $this->assertSame(2, $resProp->json('meta.total'));

        // 4. Filter keyword
        $resKeyword = $this->actingAs($host1)->getJson('/api/host/bookings?keyword=BKHOST1B');
        $resKeyword->assertStatus(200);
        $this->assertSame(1, $resKeyword->json('meta.total'));
        $this->assertSame('BKHOST1B', $resKeyword->json('data.0.booking_code'));
    }

    public function test_customer_and_host_privacy_404(): void
    {
        $fixture1 = $this->createHostFixture();
        $host1 = $fixture1['host'];
        $fixture2 = $this->createHostFixture();
        $host2 = $fixture2['host'];

        $custA = User::factory()->customer()->create();
        $custB = User::factory()->customer()->create();

        $bookingA = Booking::factory()->pending()->create([
            'customer_id' => $custA->id,
            'room_id' => $fixture1['room']->id,
        ]);

        // Cust B cannot view Cust A's booking
        $this->actingAs($custB)->getJson("/api/customer/bookings/{$bookingA->id}")->assertStatus(404);
        $this->actingAs($custB)->postJson("/api/customer/bookings/{$bookingA->id}/cancel")->assertStatus(404);
        $this->actingAs($custB)->getJson("/api/customer/bookings/{$bookingA->id}/services")->assertStatus(404);

        // Host 2 cannot view or manage Host 1's booking
        $this->actingAs($host2)->getJson("/api/host/bookings/{$bookingA->id}")->assertStatus(404);
        $this->actingAs($host2)->postJson("/api/host/bookings/{$bookingA->id}/confirm")->assertStatus(404);
        $this->actingAs($host2)->postJson("/api/host/bookings/{$bookingA->id}/reject")->assertStatus(404);
        $this->actingAs($host2)->postJson("/api/host/bookings/{$bookingA->id}/check-in")->assertStatus(404);
        $this->actingAs($host2)->postJson("/api/host/bookings/{$bookingA->id}/check-out")->assertStatus(404);
    }

    public function test_prevent_lazy_loading_and_query_count_on_list_and_detail(): void
    {
        $fixture = $this->createHostFixture();
        $host = $fixture['host'];
        $customer = $this->createCustomerFixture();

        // Create 2 bookings for list with >= 2 items
        $b1 = Booking::factory()->create([
            'customer_id' => $customer->id,
            'room_id' => $fixture['room']->id,
            'check_in_date' => Carbon::now()->addDays(5)->format('Y-m-d'),
            'check_out_date' => Carbon::now()->addDays(7)->format('Y-m-d'),
        ]);
        $b2 = Booking::factory()->create([
            'customer_id' => $customer->id,
            'room_id' => $fixture['room']->id,
            'check_in_date' => Carbon::now()->addDays(8)->format('Y-m-d'),
            'check_out_date' => Carbon::now()->addDays(10)->format('Y-m-d'),
        ]);

        // Enable preventLazyLoading
        Model::preventLazyLoading(true);

        // 1. Host list
        $hostListRes = $this->actingAs($host)->getJson('/api/host/bookings');
        $hostListRes->assertStatus(200);
        $this->assertCount(2, $hostListRes->json('data'));

        // 2. Customer list
        $custListRes = $this->actingAs($customer)->getJson('/api/customer/bookings');
        $custListRes->assertStatus(200);
        $this->assertCount(2, $custListRes->json('data'));

        // 3. Detail query count test: verify query count is constant regardless of services count
        DB::enableQueryLog();
        DB::flushQueryLog();

        $detailRes1 = $this->actingAs($host)->getJson("/api/host/bookings/{$b1->id}");
        $detailRes1->assertStatus(200);

        $queriesZeroServices = count(DB::getQueryLog());

        // Add 3 services to booking b2
        for ($s = 1; $s <= 3; $s++) {
            $service = Service::factory()->create([
                'property_id' => $fixture['property']->id,
                'name' => "Dich vu {$s}",
                'price' => 10000 * $s,
            ]);
            BookingService::create([
                'booking_id' => $b2->id,
                'service_id' => $service->id,
                'service_name' => "Dich vu {$s}",
                'unit' => 'lan',
                'quantity' => '1.00',
                'unit_price' => (string) (10000 * $s) . '.00',
                'amount' => (string) (10000 * $s) . '.00',
            ]);
        }

        DB::flushQueryLog();
        $detailRes2 = $this->actingAs($host)->getJson("/api/host/bookings/{$b2->id}");
        $detailRes2->assertStatus(200);

        $queriesThreeServices = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(
            $queriesZeroServices,
            $queriesThreeServices,
            "Query count must NOT increase with number of services (expected {$queriesZeroServices}, got {$queriesThreeServices})"
        );
    }
}
