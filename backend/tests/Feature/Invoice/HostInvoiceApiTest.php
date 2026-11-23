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
use App\Models\PartnerAgreement;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\PartnerAgreementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;

class HostInvoiceApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $host;
    protected User $otherHost;
    protected User $admin;
    protected User $customer;
    protected Property $property;
    protected RoomType $roomType;
    protected Room $room;
    protected Service $breakfastService;
    protected Service $airportService;
    protected Booking $booking;

    public function test_host_can_read_context_before_and_after_invoice_creation(): void
    {
        $this->actingAs($this->host);
        $url = "/api/host/bookings/{$this->booking->id}/invoice-context";
        $expected = [
            'booking_id' => $this->booking->id,
            'booking_code' => 'BK-TEST-001',
            'customer_name' => 'Trần Thị Thu Thảo',
            'property_name' => 'StayHub Luxury Đà Nẵng',
            'room_name' => 'Phòng Suite Hướng Biển 501',
            'check_in_date' => $this->booking->check_in_date->toDateString(),
            'check_out_date' => $this->booking->check_out_date->toDateString(),
            'booking_status' => 'COMPLETED',
            'can_create' => true,
            'invoice' => null,
        ];
        $this->getJson($url)->assertOk()->assertExactJson(['data' => $expected]);
        $this->assertDatabaseCount('invoices', 0);

        $invoice = $this->postJson("/api/host/bookings/{$this->booking->id}/invoice")->assertCreated()->json('data');
        $expected['can_create'] = false;
        $expected['invoice'] = ['id' => $invoice['id'], 'invoice_code' => $invoice['invoice_code']];
        $this->getJson($url)->assertOk()->assertExactJson(['data' => $expected]);
        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_unfinished_booking_is_readable_without_create_action(): void
    {
        $this->booking->update(['status' => BookingStatus::CONFIRMED]);
        $this->actingAs($this->host)->getJson("/api/host/bookings/{$this->booking->id}/invoice-context")
            ->assertOk()->assertJsonPath('data.booking_status', 'CONFIRMED')
            ->assertJsonPath('data.can_create', false)->assertJsonPath('data.invoice', null);
    }

    public function test_invoice_context_checks_role_then_id_without_disclosing_foreign_booking(): void
    {
        $url = "/api/host/bookings/{$this->booking->id}/invoice-context";
        $invalid = '/api/host/bookings/01/invoice-context';
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs($this->customer)->getJson($invalid)->assertForbidden();
        $this->actingAs($this->admin)->getJson($url)->assertForbidden();
        $this->actingAs($this->host)->getJson($invalid)->assertUnprocessable()->assertJsonValidationErrors('booking_id');

        $this->actingAs($this->otherHost);
        $foreign = $this->getJson($url)->assertNotFound()->json();
        $this->getJson('/api/host/bookings/999999999/invoice-context')->assertNotFound()->assertExactJson($foreign);
    }

    public function test_blocked_host_cannot_read_invoice_context(): void
    {
        $this->host->update(['status' => UserStatus::BLOCKED]);
        $this->actingAs($this->host)->getJson("/api/host/bookings/{$this->booking->id}/invoice-context")
            ->assertForbidden()->assertJsonPath('code', 'ACCOUNT_BLOCKED');
    }

    public function test_host_pending_approval_cannot_read_invoice_context_or_create_invoice(): void
    {
        $this->seed(PartnerAgreementSeeder::class);
        $agreement = PartnerAgreement::query()->sole();
        $agreement->acceptances()->create([
            'host_id' => $this->host->id,
            'accepted_content_sha256' => $agreement->content_sha256,
            'accepted_at' => now(),
            'method' => 'REGISTRATION_PENDING',
        ]);

        $this->actingAs($this->host);
        $url = "/api/host/bookings/{$this->booking->id}/invoice";
        $this->getJson($url.'-context')->assertForbidden()->assertJsonPath('code', 'HOST_APPROVAL_PENDING');
        $this->postJson($url)->assertForbidden()->assertJsonPath('code', 'HOST_APPROVAL_PENDING');
        $this->getJson('/api/host/invoices/eligible-bookings')->assertForbidden()->assertJsonPath('code', 'HOST_APPROVAL_PENDING');
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_picker_lists_only_completed_owned_bookings_without_an_invoice(): void
    {
        $url = '/api/host/invoices/eligible-bookings';
        $this->booking->update([
            'check_out_date' => $this->booking->check_in_date->copy()->addDays(2),
            'number_of_nights' => 2,
            'price_per_night' => '1100000.00',
            'room_total' => '2200000.00',
        ]);
        $this->booking->bookingServices()->delete();
        $this->booking->replicate()->fill(['booking_code' => 'BK-UNFINISHED', 'status' => BookingStatus::CONFIRMED])->save();
        $this->actingAs($this->host);
        $expected = [[
            'booking_id' => $this->booking->id,
            'booking_code' => 'BK-TEST-001',
            'customer_name' => 'Trần Thị Thu Thảo',
            'property_name' => 'StayHub Luxury Đà Nẵng',
            'room_name' => 'Phòng Suite Hướng Biển 501',
            'check_in_date' => $this->booking->check_in_date->toDateString(),
            'check_out_date' => $this->booking->check_out_date->toDateString(),
        ]];
        $page = $this->getJson($url)->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('meta.per_page', 10);
        $this->assertSame($expected, $page->json('data'));
        $this->getJson($url.'?'.http_build_query(['search' => '  Thị Thu  ']))
            ->assertOk()->assertJsonPath('data.0.booking_id', $this->booking->id);

        $this->actingAs($this->otherHost)->getJson($url.'?search=BK-TEST-001')
            ->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.total', 0);
        $this->actingAs($this->host)->postJson("/api/host/bookings/{$this->booking->id}/invoice")
            ->assertCreated()->assertJsonPath('data.total_amount', '2200000.00')
            ->assertJsonPath('data.payment_status', 'UNPAID')->assertJsonCount(1, 'data.items');
        $this->getJson($url)->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.total', 0);
    }

    public function test_picker_filters_before_pagination_with_a_stable_order_and_literal_search(): void
    {
        $older = $this->booking->replicate()->fill([
            'booking_code' => 'BK-SORT-OLDER', 'check_out_date' => $this->booking->check_out_date->subDay(),
        ]);
        $older->save();
        $tied = $this->booking->replicate()->fill(['booking_code' => 'BK-SORT-NEW%_!']);
        $tied->save();
        $url = '/api/host/invoices/eligible-bookings';
        $this->actingAs($this->host);

        $first = $this->getJson($url.'?search=BK-SORT&per_page=1')->assertOk()
            ->assertJsonPath('meta.total', 2)->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('data.0.booking_id', $tied->id);
        $this->assertStringContainsString('search=BK-SORT', $first->json('links.next'));
        $this->getJson($url.'?search=BK-SORT&per_page=1&page=2')->assertOk()
            ->assertJsonPath('meta.current_page', 2)->assertJsonPath('data.0.booking_id', $older->id);
        $this->getJson($url.'?per_page=1&page=2')->assertOk()
            ->assertJsonPath('data.0.booking_id', $this->booking->id);
        $this->getJson($url.'?'.http_build_query(['search' => '%_!']))->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.booking_id', $tied->id);
        $this->getJson($url.'?search=NO-MATCH')->assertOk()
            ->assertJsonPath('meta.total', 0)->assertJsonPath('data', []);
    }

    public function test_picker_checks_permissions_before_validating_filters(): void
    {
        $url = '/api/host/invoices/eligible-bookings';
        $invalid = $url.'?page=0&per_page=51&search%5B%5D=bad';
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs($this->customer)->getJson($invalid)->assertForbidden();
        $this->actingAs($this->admin)->getJson($url)->assertForbidden();
        $this->actingAs($this->host)->getJson($invalid)->assertUnprocessable()
            ->assertJsonValidationErrors(['page', 'per_page', 'search']);
    }

    protected function setUp(): void
    {
        // 1. Kiểm tra an toàn trước khi boot app: Đảm bảo biến môi trường là sqlite :memory:
        $envDriver = $_ENV['DB_CONNECTION'] ?? getenv('DB_CONNECTION');
        $envDatabase = $_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE');
        if ($envDriver !== 'sqlite' || $envDatabase !== ':memory:') {
            $this->fail("AN TOÀN KIỂM THỬ BỊ VI PHẠM: Biến môi trường không phải SQLite in-memory. Driver: {$envDriver}, DB: {$envDatabase}");
        }

        parent::setUp();

        // 2. Kiểm tra an toàn sau khi boot app: Đảm bảo config Laravel đang trỏ tới SQLite in-memory
        $driver = config('database.default');
        $database = config("database.connections.{$driver}.database");
        if ($driver !== 'sqlite' || $database !== ':memory:') {
            $this->fail("AN TOÀN KIỂM THỬ BỊ VI PHẠM: Config Laravel không trỏ tới SQLite in-memory. Driver: {$driver}, DB: {$database}");
        }

        $this->seedTestData();
    }

    /**
     * Khởi tạo dữ liệu kiểm thử cô lập trong SQLite in-memory
     */
    protected function seedTestData(): void
    {
        $this->host = User::create([
            'name' => 'Host Chủ Khách Sạn',
            'email' => 'host@stayhub.vn',
            'password' => bcrypt('secret123'),
            'phone' => '0911222333',
            'role' => UserRole::HOST,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->otherHost = User::create([
            'name' => 'Host Đối Tác Khác',
            'email' => 'otherhost@stayhub.vn',
            'password' => bcrypt('secret123'),
            'phone' => '0944555666',
            'role' => UserRole::HOST,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->admin = User::create([
            'name' => 'Quản Trị Viên Hệ Thống',
            'email' => 'admin@stayhub.vn',
            'password' => bcrypt('secret123'),
            'phone' => '0900000000',
            'role' => UserRole::ADMIN,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->customer = User::create([
            'name' => 'Trần Thị Thu Thảo',
            'email' => 'khachhang@gmail.com',
            'password' => bcrypt('secret123'),
            'phone' => '0988777666',
            'role' => UserRole::CUSTOMER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->property = Property::create([
            'host_id' => $this->host->id,
            'name' => 'StayHub Luxury Đà Nẵng',
            'type' => PropertyType::HOTEL,
            'address' => '123 Võ Nguyên Giáp, Đà Nẵng',
            'phone' => '0236123456',
            'status' => PropertyStatus::ACTIVE,
        ]);

        $this->roomType = RoomType::create([
            'property_id' => $this->property->id,
            'name' => 'Ocean Suite',
            'description' => 'Phòng view biển cao cấp',
        ]);

        $this->room = Room::create([
            'property_id' => $this->property->id,
            'room_type_id' => $this->roomType->id,
            'room_number' => 'OS-501',
            'name' => 'Phòng Suite Hướng Biển 501',
            'capacity' => 2,
            'price_per_night' => '1200000.00',
            'status' => RoomStatus::ACTIVE,
        ]);

        $this->breakfastService = Service::create([
            'property_id' => $this->property->id,
            'name' => 'Buffet sáng quốc tế',
            'unit' => 'suất',
            'price' => '150000.00',
            'status' => true,
        ]);

        $this->airportService = Service::create([
            'property_id' => $this->property->id,
            'name' => 'Đưa đón sân bay',
            'unit' => 'lượt',
            'price' => '250000.00',
            'status' => true,
        ]);

        // Tạo Booking đã hoàn tất (COMPLETED): 3 đêm × 1.200.000 = 3.600.000
        $this->booking = Booking::create([
            'booking_code' => 'BK-TEST-001',
            'customer_id' => $this->customer->id,
            'room_id' => $this->room->id,
            'check_in_date' => Carbon::now()->subDays(4),
            'check_out_date' => Carbon::now()->subDays(1),
            'guest_count' => 2,
            'number_of_nights' => 3,
            'price_per_night' => '1200000.00',
            'room_total' => '3600000.00',
            'status' => BookingStatus::COMPLETED,
            'note' => 'Khách hoàn thành kỳ nghỉ',
        ]);

        // Dịch vụ 1: 2 suất buffet × 150.000 = 300.000
        BookingService::create([
            'booking_id' => $this->booking->id,
            'service_id' => $this->breakfastService->id,
            'service_name' => 'Buffet sáng quốc tế',
            'unit' => 'suất',
            'quantity' => '2.00',
            'unit_price' => '150000.00',
            'amount' => '300000.00',
        ]);

        // Dịch vụ 2: 1 lượt đưa đón × 250.000 = 250.000
        BookingService::create([
            'booking_id' => $this->booking->id,
            'service_id' => $this->airportService->id,
            'service_name' => 'Đưa đón sân bay',
            'unit' => 'lượt',
            'quantity' => '1.00',
            'unit_price' => '250000.00',
            'amount' => '250000.00',
        ]);
    }

    /**
     * Kịch bản 1: Host hợp lệ gọi API tạo hóa đơn thành công (HTTP 201 Created)
     */
    public function test_authenticated_host_can_create_invoice_successfully(): void
    {
        $this->actingAs($this->host);

        $response = $this->postJson("/api/host/bookings/{$this->booking->id}/invoice");

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'invoice_code',
                'booking_id',
                'customer_name',
                'customer_email',
                'customer_phone',
                'property_name',
                'room_name',
                'subtotal',
                'discount_amount',
                'extra_fee',
                'total_amount',
                'payment_status',
                'paid_at',
                'created_at',
                'items' => [
                    '*' => [
                        'id',
                        'invoice_id',
                        'item_type',
                        'item_name',
                        'description',
                        'quantity',
                        'unit_price',
                        'amount',
                    ],
                ],
            ],
        ]);

        $data = $response->json('data');
        $this->assertSame($this->booking->id, $data['booking_id']);
        $this->assertSame('Trần Thị Thu Thảo', $data['customer_name']);
        $this->assertSame('khachhang@gmail.com', $data['customer_email']);
        $this->assertSame('0988777666', $data['customer_phone']);
        $this->assertSame('StayHub Luxury Đà Nẵng', $data['property_name']);
        $this->assertSame('Phòng Suite Hướng Biển 501', $data['room_name']);
        $this->assertSame('4150000.00', $data['subtotal']);
        $this->assertSame('0.00', $data['discount_amount']);
        $this->assertSame('0.00', $data['extra_fee']);
        $this->assertSame('4150000.00', $data['total_amount']);
        $this->assertSame('UNPAID', $data['payment_status']);
        $this->assertNull($data['paid_at']);
        $this->assertCount(3, $data['items']);

        // Xác thực trong Database
        $this->assertDatabaseHas('invoices', [
            'booking_id' => $this->booking->id,
            'total_amount' => '4150000.00',
            'payment_status' => 'UNPAID',
        ]);
    }

    /**
     * Kịch bản 2: Khách vãng lai (Guest) chưa đăng nhập bị từ chối (HTTP 401 Unauthorized)
     */
    public function test_unauthenticated_guest_cannot_create_invoice(): void
    {
        $response = $this->postJson("/api/host/bookings/{$this->booking->id}/invoice");

        $response->assertStatus(401);
    }

    /**
     * Kịch bản 3: Khách hàng (Customer) không được phép tạo hóa đơn (HTTP 403 Forbidden)
     */
    public function test_customer_cannot_create_invoice(): void
    {
        $this->actingAs($this->customer);

        $response = $this->postJson("/api/host/bookings/{$this->booking->id}/invoice");

        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'Chỉ tài khoản đối tác (HOST) sở hữu cơ sở lưu trú mới có quyền tạo hóa đơn.',
        ]);
    }

    /**
     * Kịch bản 4: Quản trị viên (Admin) không được tạo hóa đơn trong workflow của Host (HTTP 403 Forbidden)
     */
    public function test_admin_cannot_create_invoice_in_host_workflow(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson("/api/host/bookings/{$this->booking->id}/invoice");

        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'Chỉ tài khoản đối tác (HOST) sở hữu cơ sở lưu trú mới có quyền tạo hóa đơn.',
        ]);
    }

    /**
     * Kịch bản 5: Host khác không sở hữu cơ sở lưu trú bị từ chối (HTTP 403 Forbidden)
     */
    public function test_other_host_cannot_create_invoice_for_unowned_property(): void
    {
        $this->actingAs($this->otherHost);

        $response = $this->postJson("/api/host/bookings/{$this->booking->id}/invoice");

        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'Bạn không có quyền tạo hóa đơn cho cơ sở lưu trú của đối tác khác.',
        ]);
    }

    /**
     * Kịch bản 6: Từ chối tạo hóa đơn cho Booking chưa COMPLETED (HTTP 422 Unprocessable Entity)
     */
    public function test_cannot_create_invoice_for_non_completed_booking(): void
    {
        $this->booking->update(['status' => BookingStatus::CHECKED_IN]);
        $this->actingAs($this->host);

        $response = $this->postJson("/api/host/bookings/{$this->booking->id}/invoice");

        $response->assertStatus(422);
        $response->assertJson([
            'message' => "Chỉ có thể tạo hóa đơn cho đơn đặt phòng đã hoàn tất (COMPLETED). Trạng thái hiện tại: 'CHECKED_IN'.",
        ]);
    }

    /**
     * Kịch bản 7: Chống trùng lặp - Không cho phép tạo hóa đơn thứ hai cho cùng Booking (HTTP 409 Conflict)
     */
    public function test_cannot_create_invoice_when_booking_already_has_invoice(): void
    {
        $this->actingAs($this->host);

        // Lần 1: Thành công
        $firstResponse = $this->postJson("/api/host/bookings/{$this->booking->id}/invoice");
        $firstResponse->assertStatus(201);

        // Lần 2: Trả về HTTP 409 Conflict
        $secondResponse = $this->postJson("/api/host/bookings/{$this->booking->id}/invoice");
        $secondResponse->assertStatus(409);
        $secondResponse->assertJson([
            'message' => "Đơn đặt phòng #{$this->booking->id} đã có hóa đơn. Không thể tạo hóa đơn thứ hai.",
        ]);

        // Đảm bảo số lượng hóa đơn cho booking này trong cơ sở dữ liệu vẫn chỉ là 1
        $this->assertSame(1, Invoice::where('booking_id', $this->booking->id)->count());
    }

    /**
     * Kịch bản 8: Đơn đặt phòng không tồn tại (HTTP 404 Not Found)
     */
    public function test_cannot_create_invoice_for_non_existent_booking(): void
    {
        $this->actingAs($this->host);

        $response = $this->postJson('/api/host/bookings/999999/invoice');

        $response->assertStatus(404);
        $response->assertJson([
            'message' => 'Không tìm thấy đơn đặt phòng.',
        ]);
    }

    /**
     * Kịch bản 9: Mã Booking ID không hợp lệ (chuỗi không phải số nguyên dương) trả về HTTP 422
     */
    public function test_cannot_create_invoice_with_invalid_booking_id_format(): void
    {
        $this->actingAs($this->host);

        $response = $this->postJson('/api/host/bookings/invalid-booking-id/invoice');

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'Mã đơn đặt phòng không hợp lệ.',
        ]);
    }

    /**
     * Kịch bản 10: Khách hàng thiếu số điện thoại snapshot bắt buộc (HTTP 422 Unprocessable Entity)
     */
    public function test_cannot_create_invoice_when_customer_missing_phone(): void
    {
        $this->customer->update(['phone' => null]);
        $this->actingAs($this->host);

        $response = $this->postJson("/api/host/bookings/{$this->booking->id}/invoice");

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'Thông tin khách hàng thiếu số điện thoại snapshot bắt buộc (customer_phone không thể để trống).',
        ]);
        $this->assertSame(0, Invoice::count());
    }

    /**
     * Kịch bản 11: Toàn bộ trường tiền tệ được serialize dưới dạng chuỗi decimal chính xác, không ép kiểu float
     */
    public function test_financial_amounts_are_serialized_as_exact_decimal_strings_not_floats(): void
    {
        $this->actingAs($this->host);

        $response = $this->postJson("/api/host/bookings/{$this->booking->id}/invoice");

        $response->assertStatus(201);
        $data = $response->json('data');

        $this->assertIsString($data['subtotal']);
        $this->assertIsString($data['discount_amount']);
        $this->assertIsString($data['extra_fee']);
        $this->assertIsString($data['total_amount']);

        $this->assertMatchesRegularExpression('/^\d+\.\d{2}$/', $data['subtotal']);
        $this->assertMatchesRegularExpression('/^\d+\.\d{2}$/', $data['discount_amount']);
        $this->assertMatchesRegularExpression('/^\d+\.\d{2}$/', $data['extra_fee']);
        $this->assertMatchesRegularExpression('/^\d+\.\d{2}$/', $data['total_amount']);

        foreach ($data['items'] as $item) {
            $this->assertIsString($item['quantity']);
            $this->assertIsString($item['unit_price']);
            $this->assertIsString($item['amount']);
            $this->assertMatchesRegularExpression('/^\d+\.\d{2}$/', $item['quantity']);
            $this->assertMatchesRegularExpression('/^\d+\.\d{2}$/', $item['unit_price']);
            $this->assertMatchesRegularExpression('/^\d+\.\d{2}$/', $item['amount']);
        }
    }

    public function test_create_uses_typed_command_and_keeps_the_exact_public_payload(): void
    {
        $service = new class(
            app(\App\Repositories\Invoice\InvoiceRepository::class),
            app(\App\Validators\Invoice\InvoiceValidator::class),
            app(\App\Services\Invoice\InvoiceCalculationService::class),
            app(\App\Mappers\Invoice\InvoiceMapper::class),
            app(\Psr\Log\LoggerInterface::class),
            app(\App\Services\Commission\CommissionChargeService::class),
        ) extends \App\Services\Invoice\InvoiceService {
            public ?\App\DTOs\Invoice\Requests\CreateInvoiceRequest $received = null;
            public ?User $actor = null;
            public function createInvoice(int $bookingId, User $hostActor): Invoice
            {
                throw new \LogicException('HTTP must call the typed entry point.');
            }
            public function createInvoiceFromRequest(\App\DTOs\Invoice\Requests\CreateInvoiceRequest $request, User $hostActor): Invoice
            {
                $this->received = $request;
                $this->actor = $hostActor;
                return parent::createInvoiceFromRequest($request, $hostActor);
            }
        };
        app()->instance(\App\Services\Invoice\InvoiceService::class, $service);
        $this->travelTo(Carbon::parse('2026-11-14T01:02:03Z'));
        $response = $this->actingAs($this->host)->postJson("/api/host/bookings/{$this->booking->id}/invoice", [
            // Server snapshots/defaults must win over unsolicited client financial/owner fields.
            'booking_id' => 999999, 'total_amount' => '0.01', 'payment_status' => 'PAID', 'customer_name' => 'Untrusted',
        ])->assertStatus(201)->assertHeader('Content-Type', 'application/json');
        $this->assertSame($this->booking->id, $service->received?->bookingId);
        $this->assertSame($this->host->id, $service->actor?->id);
        $invoice = Invoice::with('items')->where('booking_id', $this->booking->id)->firstOrFail();
        $this->assertMatchesRegularExpression('/^INV-\d{8}-[A-F0-9]{8}$/', $invoice->invoice_code);
        $items = $invoice->items;
        $expected = [
            'id' => $invoice->id, 'invoice_code' => $invoice->invoice_code, 'booking_id' => $this->booking->id,
            'customer_name' => $this->customer->name, 'customer_email' => $this->customer->email, 'customer_phone' => $this->customer->phone,
            'property_name' => $this->property->name, 'room_name' => 'Phòng Suite Hướng Biển 501',
            'subtotal' => '4150000.00', 'discount_amount' => '0.00', 'extra_fee' => '0.00', 'total_amount' => '4150000.00',
            'payment_status' => 'UNPAID', 'paid_at' => null, 'created_at' => '2026-11-14T01:02:03.000000Z',
            'items' => [
                ['id' => $items[0]->id, 'invoice_id' => $invoice->id, 'item_type' => 'ROOM', 'item_name' => 'Phòng Suite Hướng Biển 501',
                    'description' => 'Tiền phòng (3 đêm × 1200000.00)', 'quantity' => '3.00', 'unit_price' => '1200000.00', 'amount' => '3600000.00'],
                ['id' => $items[1]->id, 'invoice_id' => $invoice->id, 'item_type' => 'SERVICE', 'item_name' => 'Buffet sáng quốc tế',
                    'description' => null, 'quantity' => '2.00', 'unit_price' => '150000.00', 'amount' => '300000.00'],
                ['id' => $items[2]->id, 'invoice_id' => $invoice->id, 'item_type' => 'SERVICE', 'item_name' => 'Đưa đón sân bay',
                    'description' => null, 'quantity' => '1.00', 'unit_price' => '250000.00', 'amount' => '250000.00'],
            ],
        ];
        $response->assertExactJson(['data' => $expected]);
        $this->getJson('/api/host/invoices/'.$invoice->id)->assertOk()->assertExactJson(['data' => $expected]);
        $listRow = $expected;
        unset($listRow['items']);
        $this->assertSame($listRow, $this->getJson('/api/host/invoices')->assertOk()->json('data.0'));
        $this->actingAs($this->customer)->getJson('/api/customer/invoices/'.$invoice->id)->assertOk()->assertExactJson(['data' => $expected]);
        $this->assertSame($listRow, $this->getJson('/api/customer/invoices')->assertOk()->json('data.0'));
    }

    public function test_raw_booking_ids_preserve_canonical_validation_and_missing_id_classification(): void
    {
        $this->actingAs($this->host);
        foreach (['0', '-1', '1.5', '1e2', '01', '+1', ' 1 ', 'abc', (string) PHP_INT_MAX.'0'] as $id) {
            $this->postJson('/api/host/bookings/'.rawurlencode($id).'/invoice')->assertStatus(422)
                ->assertExactJson(['message' => 'Mã đơn đặt phòng không hợp lệ.']);
        }
        $this->postJson('/api/host/bookings/'.PHP_INT_MAX.'/invoice')->assertStatus(404)
            ->assertExactJson(['message' => 'Không tìm thấy đơn đặt phòng.']);
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_guest_auth_precedes_malformed_id_and_role_denial_keeps_the_session(): void
    {
        $this->postJson('/api/host/bookings/invalid/invoice')->assertStatus(401);
        $this->actingAs($this->admin)->postJson('/api/host/bookings/invalid/invoice')->assertStatus(422)
            ->assertExactJson(['message' => 'Mã đơn đặt phòng không hợp lệ.']);
        $this->postJson("/api/host/bookings/{$this->booking->id}/invoice")->assertStatus(403)
            ->assertExactJson(['message' => 'Chỉ tài khoản đối tác (HOST) sở hữu cơ sở lưu trú mới có quyền tạo hóa đơn.']);
        $this->getJson('/api/me')->assertOk()->assertJsonPath('user.id', $this->admin->id);
        $this->assertDatabaseCount('invoices', 0);
    }
}
