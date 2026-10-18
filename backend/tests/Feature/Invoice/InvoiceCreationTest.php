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
use App\Services\Invoice\DuplicateInvoiceException;
use App\Services\Invoice\InvoiceService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class InvoiceCreationTest extends TestCase
{
    use RefreshDatabase;

    protected InvoiceService $invoiceService;
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

        $this->invoiceService = app(InvoiceService::class);
        $this->seedTestData();
    }

    /**
     * Khởi tạo dữ liệu test cô lập trong SQLite in-memory
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
            'note' => 'Khách đến nhận phòng lúc 14h',
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
     * Kịch bản 1: Tạo hóa đơn thành công cho Booking COMPLETED kèm ROOM và SERVICE items
     */
    public function test_create_invoice_successfully_for_completed_booking_with_room_and_services(): void
    {
        $invoice = $this->invoiceService->createInvoice($this->booking->id, $this->host);

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'booking_id' => $this->booking->id,
            'payment_status' => 'UNPAID',
            'paid_at' => null,
            'customer_name' => 'Trần Thị Thu Thảo',
            'customer_email' => 'khachhang@gmail.com',
            'customer_phone' => '0988777666',
            'property_name' => 'StayHub Luxury Đà Nẵng',
            'room_name' => 'Phòng Suite Hướng Biển 501',
            'subtotal' => '4150000.00',      // 3.600.000 + 300.000 + 250.000 = 4.150.000.00
            'discount_amount' => '0.00',
            'extra_fee' => '0.00',
            'total_amount' => '4150000.00',
        ]);

        // Kiểm tra mã hóa đơn có tiền tố INV- và độ dài hợp lệ <= 50
        $this->assertStringStartsWith('INV-', $invoice->invoice_code);
        $this->assertLessThanOrEqual(50, strlen($invoice->invoice_code));

        // Kiểm tra danh sách items
        $this->assertCount(3, $invoice->items);

        // Item ROOM
        $roomItem = $invoice->items->where('item_type', 'ROOM')->first();
        $this->assertNotNull($roomItem);
        $this->assertSame('Phòng Suite Hướng Biển 501', $roomItem->item_name);
        $this->assertSame('3.00', $roomItem->quantity);
        $this->assertSame('1200000.00', $roomItem->unit_price);
        $this->assertSame('3600000.00', $roomItem->amount);

        // Items SERVICE
        $serviceItems = $invoice->items->where('item_type', 'SERVICE')->values();
        $this->assertCount(2, $serviceItems);

        $this->assertSame('Buffet sáng quốc tế', $serviceItems[0]->item_name);
        $this->assertSame('2.00', $serviceItems[0]->quantity);
        $this->assertSame('150000.00', $serviceItems[0]->unit_price);
        $this->assertSame('300000.00', $serviceItems[0]->amount);

        $this->assertSame('Đưa đón sân bay', $serviceItems[1]->item_name);
        $this->assertSame('1.00', $serviceItems[1]->quantity);
        $this->assertSame('250000.00', $serviceItems[1]->unit_price);
        $this->assertSame('250000.00', $serviceItems[1]->amount);
    }

    /**
     * Kịch bản 2: Chống tạo trùng hóa đơn (Duplicate Prevention)
     */
    public function test_duplicate_invoice_prevention_throws_duplicate_invoice_exception(): void
    {
        // Lần 1: Thành công
        $invoice1 = $this->invoiceService->createInvoice($this->booking->id, $this->host);
        $this->assertNotNull($invoice1);

        // Lần 2: Phải ném DuplicateInvoiceException và không tạo bản ghi mới
        $this->expectException(DuplicateInvoiceException::class);
        $this->expectExceptionMessage("Đơn đặt phòng #{$this->booking->id} đã có hóa đơn.");

        $this->invoiceService->createInvoice($this->booking->id, $this->host);

        // Số lượng hóa đơn trong DB cho booking này vẫn chỉ là 1
        $this->assertSame(1, Invoice::where('booking_id', $this->booking->id)->count());
    }

    /**
     * Kịch bản 3: Xử lý vi phạm UNIQUE constraint ở mức DB cho booking_id
     */
    public function test_database_unique_constraint_on_booking_id_is_handled_correctly(): void
    {
        // Chèn trước một hóa đơn vào DB cho booking này (mô phỏng request cạnh tranh đã ghi trước)
        Invoice::create([
            'invoice_code' => 'INV-PRE-EXISTING-01',
            'booking_id' => $this->booking->id,
            'customer_name' => $this->customer->name,
            'customer_email' => $this->customer->email,
            'customer_phone' => $this->customer->phone,
            'property_name' => $this->property->name,
            'room_name' => $this->room->name,
            'subtotal' => '3600000.00',
            'discount_amount' => '0.00',
            'extra_fee' => '0.00',
            'total_amount' => '3600000.00',
            'payment_status' => 'UNPAID',
        ]);

        $this->expectException(DuplicateInvoiceException::class);
        $this->invoiceService->createInvoice($this->booking->id, $this->host);
    }

    /**
     * Kịch bản 4: Phân biệt lỗi trùng mã invoice_code (ném RuntimeException, không coi là duplicate booking)
     */
    public function test_database_unique_constraint_on_invoice_code_throws_runtime_exception(): void
    {
        // Mock method generateUniqueInvoiceCode để cố tình trả về mã đã tồn tại
        $mockService = new class($this->invoiceService->getValidator(), $this->invoiceService->getCalculationService()) extends InvoiceService {
            public function generateUniqueInvoiceCode(): string
            {
                return 'INV-DUPLICATE-CODE-001';
            }
        };

        // Chèn trước một invoice với mã trên nhưng cho booking khác
        $otherBooking = Booking::create([
            'booking_code' => 'BK-OTHER-999',
            'customer_id' => $this->customer->id,
            'room_id' => $this->room->id,
            'check_in_date' => Carbon::now()->subDays(10),
            'check_out_date' => Carbon::now()->subDays(8),
            'guest_count' => 1,
            'number_of_nights' => 2,
            'price_per_night' => '1200000.00',
            'room_total' => '2400000.00',
            'status' => BookingStatus::COMPLETED,
        ]);

        Invoice::create([
            'invoice_code' => 'INV-DUPLICATE-CODE-001',
            'booking_id' => $otherBooking->id,
            'customer_name' => $this->customer->name,
            'customer_email' => $this->customer->email,
            'customer_phone' => $this->customer->phone,
            'property_name' => $this->property->name,
            'room_name' => $this->room->name,
            'subtotal' => '2400000.00',
            'discount_amount' => '0.00',
            'extra_fee' => '0.00',
            'total_amount' => '2400000.00',
            'payment_status' => 'UNPAID',
        ]);

        // Khi chạy tạo hóa đơn cho $this->booking với mã trùng lặp -> phải ném RuntimeException về invoice_code
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Trùng mã hóa đơn khi lưu vào cơ sở dữ liệu. Vui lòng thử lại.');

        $mockService->createInvoice($this->booking->id, $this->host);
    }

    /**
     * Kịch bản 5: Từ chối tạo hóa đơn cho Booking chưa COMPLETED (ví dụ: CHECKED_IN)
     */
    public function test_cannot_create_invoice_for_non_completed_booking(): void
    {
        $this->booking->update(['status' => BookingStatus::CHECKED_IN]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Chỉ có thể tạo hóa đơn cho đơn đặt phòng đã hoàn tất (COMPLETED). Trạng thái hiện tại: 'CHECKED_IN'.");

        $this->invoiceService->createInvoice($this->booking->id, $this->host);
    }

    /**
     * Kịch bản 6 (BẮT BUỘC): ADMIN không được tự tạo hóa đơn trong workflow của Host
     */
    public function test_admin_cannot_create_invoice_in_host_workflow(): void
    {
        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('Chỉ tài khoản đối tác (HOST) sở hữu cơ sở lưu trú mới có quyền tạo hóa đơn.');

        $this->invoiceService->createInvoice($this->booking->id, $this->admin);
    }

    /**
     * Kịch bản 7: Host khác không sở hữu Property bị từ chối
     */
    public function test_other_host_cannot_create_invoice_for_unowned_property(): void
    {
        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('Bạn không có quyền tạo hóa đơn cho cơ sở lưu trú của đối tác khác.');

        $this->invoiceService->createInvoice($this->booking->id, $this->otherHost);
    }

    /**
     * Kịch bản 8: Customer thiếu số điện thoại bắt buộc -> từ chối, không tạo bản ghi nào
     */
    public function test_customer_missing_phone_throws_validation_exception_and_does_not_create_invoice(): void
    {
        $this->customer->update(['phone' => null]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Thông tin khách hàng thiếu số điện thoại snapshot bắt buộc (customer_phone không thể để trống).');

        try {
            $this->invoiceService->createInvoice($this->booking->id, $this->host);
        } finally {
            $this->assertSame(0, Invoice::count());
            $this->assertSame(0, InvoiceItem::count());
        }
    }

    /**
     * Kịch bản 9: Kiểm tra Rollback toàn diện khi phát sinh lỗi sau khi chèn Invoice và trước khi hoàn tất InvoiceItems
     */
    public function test_transaction_rollback_when_invoice_item_creation_fails(): void
    {
        // Gắn model hook mô phỏng lỗi sập DB / ngoại lệ khi đang insert InvoiceItem
        InvoiceItem::creating(function ($item) {
            throw new RuntimeException('Lỗi mô phỏng gián đoạn transaction khi lưu InvoiceItem');
        });

        try {
            $this->invoiceService->createInvoice($this->booking->id, $this->host);
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $e) {
            $this->assertSame('Lỗi mô phỏng gián đoạn transaction khi lưu InvoiceItem', $e->getMessage());
        }

        // Khôi phục dispatcher của InvoiceItem
        InvoiceItem::flushEventListeners();

        // Kiểm tra database hoàn toàn sạch sẽ, không có bất kỳ dòng dữ liệu hóa đơn nào bị lưu dở dang
        $this->assertSame(0, Invoice::count(), 'Invoice không được phép tồn tại sau khi transaction bị rollback.');
        $this->assertSame(0, InvoiceItem::count(), 'InvoiceItems không được phép tồn tại sau khi transaction bị rollback.');
    }

    /**
     * Kịch bản 10: Tính bất biến của các Model liên quan (Booking, Room, BookingService không bị sửa đổi)
     */
    public function test_booking_room_and_services_are_not_mutated_after_invoice_creation(): void
    {
        $bookingBefore = $this->booking->fresh()->toArray();
        $roomBefore = $this->room->fresh()->toArray();
        $servicesBefore = BookingService::where('booking_id', $this->booking->id)->get()->toArray();

        $this->invoiceService->createInvoice($this->booking->id, $this->host);

        $bookingAfter = $this->booking->fresh()->toArray();
        $roomAfter = $this->room->fresh()->toArray();
        $servicesAfter = BookingService::where('booking_id', $this->booking->id)->get()->toArray();

        $this->assertSame($bookingBefore, $bookingAfter, 'Dữ liệu Booking không được phép bị sửa đổi khi tạo hóa đơn.');
        $this->assertSame($roomBefore, $roomAfter, 'Dữ liệu Room không được phép bị sửa đổi khi tạo hóa đơn.');
        $this->assertSame($servicesBefore, $servicesAfter, 'Dữ liệu BookingServices không được phép bị sửa đổi khi tạo hóa đơn.');
    }
}
