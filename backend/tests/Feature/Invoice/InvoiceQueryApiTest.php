<?php

namespace Tests\Feature\Invoice;

use App\Enums\BookingStatus;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Enums\RoomStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Booking;
use App\Models\BookingService;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;

class InvoiceQueryApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $host;
    protected User $otherHost;
    protected User $customer;
    protected User $otherCustomer;
    protected User $admin;

    protected Property $propertyHost;
    protected Property $propertyOtherHost;
    protected Room $roomHost;
    protected Room $roomOtherHost;

    protected Booking $bookingHost;
    protected Booking $bookingOtherHost;
    protected Booking $bookingCustomer2;

    protected Invoice $invoiceHost;
    protected Invoice $invoicePaidHost;
    protected Invoice $invoiceOtherHost;
    protected Invoice $invoiceCustomer2;

    protected function setUp(): void
    {
        $envDriver = $_ENV['DB_CONNECTION'] ?? getenv('DB_CONNECTION');
        $envDatabase = $_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE');
        if ($envDriver !== 'sqlite' || $envDatabase !== ':memory:') {
            $this->fail("AN TOÀN KIỂM THỬ BỊ VI PHẠM: Driver: {$envDriver}, DB: {$envDatabase}");
        }

        parent::setUp();

        $driver = config('database.default');
        $database = config("database.connections.{$driver}.database");
        if ($driver !== 'sqlite' || $database !== ':memory:') {
            $this->fail("AN TOÀN KIỂM THỬ BỊ VI PHẠM: Config không phải sqlite :memory:");
        }

        $this->seedData();
    }

    protected function seedData(): void
    {
        // Users
        $this->host = User::create([
            'name' => 'Host Nguyễn Văn An',
            'email' => 'host@stayhub.vn',
            'password' => bcrypt('password'),
            'phone' => '0911222333',
            'role' => UserRole::HOST,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->otherHost = User::create([
            'name' => 'Host Trần Văn Bình',
            'email' => 'otherhost@stayhub.vn',
            'password' => bcrypt('password'),
            'phone' => '0922333444',
            'role' => UserRole::HOST,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->customer = User::create([
            'name' => 'Khách hàng Lê Thị Cúc',
            'email' => 'customer@stayhub.vn',
            'password' => bcrypt('password'),
            'phone' => '0933444555',
            'role' => UserRole::CUSTOMER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->otherCustomer = User::create([
            'name' => 'Khách hàng Phạm Văn Dũng',
            'email' => 'othercustomer@stayhub.vn',
            'password' => bcrypt('password'),
            'phone' => '0944555666',
            'role' => UserRole::CUSTOMER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->admin = User::create([
            'name' => 'Quản trị viên',
            'email' => 'admin@stayhub.vn',
            'password' => bcrypt('password'),
            'phone' => '0900000000',
            'role' => UserRole::ADMIN,
            'status' => UserStatus::ACTIVE,
        ]);

        // Properties & Rooms
        $this->propertyHost = Property::create([
            'host_id' => $this->host->id,
            'name' => 'StayHub Luxury Đà Nẵng',
            'type' => PropertyType::HOTEL,
            'address' => '123 Võ Nguyên Giáp, Đà Nẵng',
            'phone' => '0236123456',
            'status' => PropertyStatus::ACTIVE,
        ]);

        $this->propertyOtherHost = Property::create([
            'host_id' => $this->otherHost->id,
            'name' => 'StayHub Boutique Sài Gòn',
            'type' => PropertyType::HOTEL,
            'address' => '456 Lê Lợi, TP.HCM',
            'phone' => '0281234567',
            'status' => PropertyStatus::ACTIVE,
        ]);

        $roomType1 = RoomType::create([
            'property_id' => $this->propertyHost->id,
            'name' => 'Deluxe Ocean View',
        ]);

        $roomType2 = RoomType::create([
            'property_id' => $this->propertyOtherHost->id,
            'name' => 'Executive Suite',
        ]);

        $this->roomHost = Room::create([
            'property_id' => $this->propertyHost->id,
            'room_type_id' => $roomType1->id,
            'room_number' => 'DN-101',
            'name' => 'Phòng Deluxe 101',
            'capacity' => 2,
            'price_per_night' => '1000000.00',
            'status' => RoomStatus::ACTIVE,
        ]);

        $this->roomOtherHost = Room::create([
            'property_id' => $this->propertyOtherHost->id,
            'room_type_id' => $roomType2->id,
            'room_number' => 'SG-201',
            'name' => 'Phòng Suite 201',
            'capacity' => 2,
            'price_per_night' => '1500000.00',
            'status' => RoomStatus::ACTIVE,
        ]);

        // Bookings
        $this->bookingHost = Booking::create([
            'booking_code' => 'BK-HOST-1',
            'customer_id' => $this->customer->id,
            'room_id' => $this->roomHost->id,
            'check_in_date' => Carbon::now()->subDays(3)->toDateString(),
            'check_out_date' => Carbon::now()->subDay()->toDateString(),
            'guest_count' => 2,
            'number_of_nights' => 2,
            'price_per_night' => '1000000.00',
            'room_total' => '2000000.00',
            'status' => BookingStatus::COMPLETED,
        ]);

        $this->bookingOtherHost = Booking::create([
            'booking_code' => 'BK-OTHER-1',
            'customer_id' => $this->customer->id, // Customer 1 stays at Other Host
            'room_id' => $this->roomOtherHost->id,
            'check_in_date' => Carbon::now()->subDays(5)->toDateString(),
            'check_out_date' => Carbon::now()->subDays(3)->toDateString(),
            'guest_count' => 2,
            'number_of_nights' => 2,
            'price_per_night' => '1500000.00',
            'room_total' => '3000000.00',
            'status' => BookingStatus::COMPLETED,
        ]);

        $this->bookingCustomer2 = Booking::create([
            'booking_code' => 'BK-CUST2-1',
            'customer_id' => $this->otherCustomer->id, // Customer 2 stays at Host 1
            'room_id' => $this->roomHost->id,
            'check_in_date' => Carbon::now()->subDays(7)->toDateString(),
            'check_out_date' => Carbon::now()->subDays(5)->toDateString(),
            'guest_count' => 1,
            'number_of_nights' => 2,
            'price_per_night' => '1000000.00',
            'room_total' => '2000000.00',
            'status' => BookingStatus::COMPLETED,
        ]);

        // Invoices
        // Invoice 1: Host 1, Customer 1, UNPAID
        $this->invoiceHost = Invoice::create([
            'invoice_code' => 'INV-2026-0001',
            'booking_id' => $this->bookingHost->id,
            'customer_name' => $this->customer->name,
            'customer_email' => $this->customer->email,
            'customer_phone' => $this->customer->phone,
            'property_name' => $this->propertyHost->name,
            'room_name' => $this->roomHost->name,
            'subtotal' => '2000000.00',
            'discount_amount' => '0.00',
            'extra_fee' => '0.00',
            'total_amount' => '2000000.00',
            'payment_status' => 'UNPAID',
        ]);

        InvoiceItem::create([
            'invoice_id' => $this->invoiceHost->id,
            'item_type' => 'ROOM',
            'item_name' => 'Tiền phòng (2 đêm)',
            'quantity' => '2.00',
            'unit_price' => '1000000.00',
            'amount' => '2000000.00',
        ]);

        // Invoice 2: Host 1, Customer 2, PAID
        $this->invoicePaidHost = Invoice::create([
            'invoice_code' => 'INV-2026-0002',
            'booking_id' => $this->bookingCustomer2->id,
            'customer_name' => $this->otherCustomer->name,
            'customer_email' => $this->otherCustomer->email,
            'customer_phone' => $this->otherCustomer->phone,
            'property_name' => $this->propertyHost->name,
            'room_name' => $this->roomHost->name,
            'subtotal' => '2000000.00',
            'discount_amount' => '100000.00',
            'extra_fee' => '50000.00',
            'total_amount' => '1950000.00',
            'payment_status' => 'PAID',
            'paid_at' => Carbon::now()->subDay(),
        ]);

        InvoiceItem::create([
            'invoice_id' => $this->invoicePaidHost->id,
            'item_type' => 'ROOM',
            'item_name' => 'Tiền phòng (2 đêm)',
            'quantity' => '2.00',
            'unit_price' => '1000000.00',
            'amount' => '2000000.00',
        ]);

        // Invoice 3: Other Host, Customer 1, REFUNDED
        $this->invoiceOtherHost = Invoice::create([
            'invoice_code' => 'INV-2026-0003',
            'booking_id' => $this->bookingOtherHost->id,
            'customer_name' => $this->customer->name,
            'customer_email' => $this->customer->email,
            'customer_phone' => $this->customer->phone,
            'property_name' => $this->propertyOtherHost->name,
            'room_name' => $this->roomOtherHost->name,
            'subtotal' => '3000000.00',
            'discount_amount' => '0.00',
            'extra_fee' => '0.00',
            'total_amount' => '3000000.00',
            'payment_status' => 'REFUNDED',
        ]);

        InvoiceItem::create([
            'invoice_id' => $this->invoiceOtherHost->id,
            'item_type' => 'ROOM',
            'item_name' => 'Tiền phòng (2 đêm)',
            'quantity' => '2.00',
            'unit_price' => '1500000.00',
            'amount' => '3000000.00',
        ]);
    }

    /** ================== 1. GUEST AUTHENTICATION TESTS ================== */

    public function test_guest_cannot_access_host_invoice_list(): void
    {
        $response = $this->getJson('/api/host/invoices');
        $response->assertStatus(401);
    }

    public function test_guest_cannot_access_host_invoice_detail(): void
    {
        $response = $this->getJson("/api/host/invoices/{$this->invoiceHost->id}");
        $response->assertStatus(401);
    }

    public function test_guest_cannot_access_customer_invoice_list(): void
    {
        $response = $this->getJson('/api/customer/invoices');
        $response->assertStatus(401);
    }

    public function test_guest_cannot_access_customer_invoice_detail(): void
    {
        $response = $this->getJson("/api/customer/invoices/{$this->invoiceHost->id}");
        $response->assertStatus(401);
    }

    /** ================== 2. ROLE AUTHORIZATION TESTS ================== */

    public function test_customer_cannot_access_host_invoice_list(): void
    {
        $response = $this->actingAs($this->customer)->getJson('/api/host/invoices');
        $response->assertStatus(403);
    }

    public function test_admin_cannot_access_host_invoice_list_in_host_workflow(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/host/invoices');
        $response->assertStatus(403);
    }

    public function test_customer_cannot_access_host_invoice_detail(): void
    {
        $response = $this->actingAs($this->customer)->getJson("/api/host/invoices/{$this->invoiceHost->id}");
        $response->assertStatus(403);
    }

    public function test_host_cannot_access_customer_invoice_list(): void
    {
        $response = $this->actingAs($this->host)->getJson('/api/customer/invoices');
        $response->assertStatus(403);
    }

    public function test_admin_cannot_access_customer_invoice_list_in_customer_workflow(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/customer/invoices');
        $response->assertStatus(403);
    }

    public function test_host_cannot_access_customer_invoice_detail(): void
    {
        $response = $this->actingAs($this->host)->getJson("/api/customer/invoices/{$this->invoiceHost->id}");
        $response->assertStatus(403);
    }

    /** ================== 3. HOST OWNERSHIP & ISOLATION TESTS ================== */

    public function test_host_only_sees_invoices_of_owned_properties(): void
    {
        $response = $this->actingAs($this->host)->getJson('/api/host/invoices');

        $response->assertStatus(200);
        $data = $response->json('data');

        // Host owns property with invoiceHost and invoicePaidHost (2 invoices)
        $this->assertCount(2, $data);
        $codes = collect($data)->pluck('invoice_code')->all();
        $this->assertContains('INV-2026-0001', $codes);
        $this->assertContains('INV-2026-0002', $codes);
        $this->assertNotContains('INV-2026-0003', $codes); // Belongs to Other Host
    }

    public function test_host_cannot_view_detail_of_another_hosts_invoice(): void
    {
        // invoiceOtherHost belongs to Other Host
        $response = $this->actingAs($this->host)->getJson("/api/host/invoices/{$this->invoiceOtherHost->id}");

        // Must return 404 to not leak existence of invoice
        $response->assertStatus(404);
    }

    public function test_host_viewing_non_existent_invoice_returns_404(): void
    {
        $response = $this->actingAs($this->host)->getJson('/api/host/invoices/999999');
        $response->assertStatus(404);
    }

    public function test_host_viewing_invalid_invoice_id_returns_404(): void
    {
        $response = $this->actingAs($this->host)->getJson('/api/host/invoices/abc');
        $response->assertStatus(404);
    }

    public function test_host_can_view_own_invoice_detail_with_items(): void
    {
        $response = $this->actingAs($this->host)->getJson("/api/host/invoices/{$this->invoiceHost->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'id' => $this->invoiceHost->id,
                'invoice_code' => 'INV-2026-0001',
                'booking_id' => $this->bookingHost->id,
                'customer_name' => $this->customer->name,
                'property_name' => 'StayHub Luxury Đà Nẵng',
                'room_name' => 'Phòng Deluxe 101',
                'subtotal' => '2000000.00',
                'discount_amount' => '0.00',
                'extra_fee' => '0.00',
                'total_amount' => '2000000.00',
                'payment_status' => 'UNPAID',
            ],
        ]);

        $this->assertNotEmpty($response->json('data.items'));
        $this->assertEquals('2000000.00', $response->json('data.items.0.amount'));
    }

    /** ================== 4. CUSTOMER OWNERSHIP & ISOLATION TESTS ================== */

    public function test_customer_only_sees_invoices_of_their_own_bookings(): void
    {
        // Customer 1 placed bookingHost (INV-0001) and bookingOtherHost (INV-0003)
        $response = $this->actingAs($this->customer)->getJson('/api/customer/invoices');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(2, $data);
        $codes = collect($data)->pluck('invoice_code')->all();
        $this->assertContains('INV-2026-0001', $codes);
        $this->assertContains('INV-2026-0003', $codes);
        $this->assertNotContains('INV-2026-0002', $codes); // Belongs to Customer 2
    }

    public function test_customer_cannot_view_detail_of_another_customers_invoice(): void
    {
        // invoicePaidHost belongs to Customer 2
        $response = $this->actingAs($this->customer)->getJson("/api/customer/invoices/{$this->invoicePaidHost->id}");

        $response->assertStatus(404);
    }

    public function test_customer_can_view_own_invoice_detail_with_items(): void
    {
        $response = $this->actingAs($this->customer)->getJson("/api/customer/invoices/{$this->invoiceHost->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'id' => $this->invoiceHost->id,
                'invoice_code' => 'INV-2026-0001',
                'booking_id' => $this->bookingHost->id,
                'total_amount' => '2000000.00',
                'payment_status' => 'UNPAID',
            ],
        ]);
        $this->assertNotEmpty($response->json('data.items'));
    }

    /** ================== 5. FILTERING, SEARCH & PAGINATION TESTS ================== */

    public function test_host_filter_by_payment_status(): void
    {
        // Host has 1 UNPAID and 1 PAID
        $responseUnpaid = $this->actingAs($this->host)->getJson('/api/host/invoices?status=UNPAID');
        $responseUnpaid->assertStatus(200);
        $this->assertCount(1, $responseUnpaid->json('data'));
        $this->assertEquals('INV-2026-0001', $responseUnpaid->json('data.0.invoice_code'));

        $responsePaid = $this->actingAs($this->host)->getJson('/api/host/invoices?status=PAID');
        $responsePaid->assertStatus(200);
        $this->assertCount(1, $responsePaid->json('data'));
        $this->assertEquals('INV-2026-0002', $responsePaid->json('data.0.invoice_code'));

        $responseRefunded = $this->actingAs($this->host)->getJson('/api/host/invoices?status=REFUNDED');
        $responseRefunded->assertStatus(200);
        $this->assertCount(0, $responseRefunded->json('data'));
    }

    public function test_host_search_by_invoice_code_and_customer_name(): void
    {
        // Search by code
        $resCode = $this->actingAs($this->host)->getJson('/api/host/invoices?search=0001');
        $resCode->assertStatus(200);
        $this->assertCount(1, $resCode->json('data'));
        $this->assertEquals('INV-2026-0001', $resCode->json('data.0.invoice_code'));

        // Search by customer name
        $resName = $this->actingAs($this->host)->getJson('/api/host/invoices?search=Dũng');
        $resName->assertStatus(200);
        $this->assertCount(1, $resName->json('data'));
        $this->assertEquals('INV-2026-0002', $resName->json('data.0.invoice_code'));

        // Search non-matching
        $resNone = $this->actingAs($this->host)->getJson('/api/host/invoices?search=NonExistent');
        $resNone->assertStatus(200);
        $this->assertCount(0, $resNone->json('data'));
    }

    public function test_customer_search_by_property_name(): void
    {
        // Customer 1 has 1 at Đà Nẵng and 1 at Sài Gòn
        $res = $this->actingAs($this->customer)->getJson('/api/customer/invoices?search=Sài+Gòn');
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data'));
        $this->assertEquals('INV-2026-0003', $res->json('data.0.invoice_code'));
    }

    public function test_pagination_and_per_page_limits(): void
    {
        $response = $this->actingAs($this->host)->getJson('/api/host/invoices?per_page=1');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals(2, $response->json('meta.total'));
        $this->assertEquals(2, $response->json('meta.last_page'));

        // Ensure per_page is clamped
        $responseClamped = $this->actingAs($this->host)->getJson('/api/host/invoices?per_page=999');
        $responseClamped->assertStatus(200);
        $this->assertEquals(50, $responseClamped->json('meta.per_page'));
    }

    public function test_financial_amounts_are_serialized_as_exact_decimal_strings(): void
    {
        $response = $this->actingAs($this->host)->getJson("/api/host/invoices/{$this->invoicePaidHost->id}");

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertSame('2000000.00', $data['subtotal']);
        $this->assertSame('100000.00', $data['discount_amount']);
        $this->assertSame('50000.00', $data['extra_fee']);
        $this->assertSame('1950000.00', $data['total_amount']);

        $item = $data['items'][0];
        $this->assertSame('2.00', $item['quantity']);
        $this->assertSame('1000000.00', $item['unit_price']);
        $this->assertSame('2000000.00', $item['amount']);
    }
}
