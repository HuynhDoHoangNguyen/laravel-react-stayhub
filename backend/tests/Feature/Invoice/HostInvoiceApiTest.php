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
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
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
}
