<?php

namespace Tests\Unit;

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\BookingService;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use App\Validators\Invoice\InvoiceCreationValidator;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class InvoiceCreationValidatorTest extends TestCase
{
    protected InvoiceCreationValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new InvoiceCreationValidator();
    }

    /**
     * Helper tạo giả lập Booking với quan hệ cần thiết
     */
    protected function createMockBooking(
        string $status = 'COMPLETED',
        int $hostId = 1,
        ?string $customerPhone = '0901234567',
        int $nights = 2,
        string $pricePerNight = '1000000.00',
        string $roomTotal = '2000000.00',
        array $services = []
    ): Booking {
        $host = new User();
        $host->id = $hostId;
        $host->role = UserRole::HOST;

        $property = new Property();
        $property->id = 10;
        $property->host_id = $hostId;
        $property->name = 'StayHub Resort';
        $property->setRelation('host', $host);

        $room = new Room();
        $room->id = 20;
        $room->name = 'Deluxe Suite';
        $room->price_per_night = $pricePerNight;
        $room->setRelation('property', $property);

        $customer = new User();
        $customer->id = 5;
        $customer->name = 'Nguyễn Văn A';
        $customer->email = 'nguyenvana@example.com';
        $customer->phone = $customerPhone;
        $customer->role = UserRole::CUSTOMER;

        $booking = new Booking();
        $booking->id = 100;
        $booking->status = BookingStatus::from($status);
        $booking->number_of_nights = $nights;
        $booking->price_per_night = $pricePerNight;
        $booking->room_total = $roomTotal;

        $booking->setRelation('customer', $customer);
        $booking->setRelation('room', $room);

        $serviceModels = collect();
        foreach ($services as $svc) {
            $bs = new BookingService();
            $bs->service_id = $svc['id'] ?? 1;
            $bs->service_name = $svc['name'] ?? 'Bữa sáng';
            $bs->quantity = $svc['quantity'] ?? '1.00';
            $bs->unit_price = $svc['unit_price'] ?? '100000.00';
            $bs->amount = $svc['amount'] ?? '100000.00';
            $serviceModels->push($bs);
        }
        $booking->setRelation('bookingServices', $serviceModels);

        return $booking;
    }

    /**
     * Host chính chủ sở hữu cơ sở lưu trú được phép xác thực
     */
    public function test_host_authorization_passes_for_valid_host(): void
    {
        $host = new User();
        $host->id = 1;
        $host->role = UserRole::HOST;

        $booking = $this->createMockBooking('COMPLETED', 1);

        $this->validator->validateHostAuthorization($booking, $host);
        $this->assertTrue(true);
    }

    /**
     * BẮT BUỘC: ADMIN không được phép tự tạo hóa đơn trong workflow Host
     */
    public function test_admin_cannot_create_invoice(): void
    {
        $admin = new User();
        $admin->id = 999;
        $admin->role = UserRole::ADMIN;

        $booking = $this->createMockBooking('COMPLETED', 1);

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('Chỉ tài khoản đối tác (HOST) sở hữu cơ sở lưu trú mới có quyền tạo hóa đơn.');

        $this->validator->validateHostAuthorization($booking, $admin);
    }

    /**
     * Host khác không sở hữu cơ sở lưu trú bị từ chối
     */
    public function test_host_cannot_create_invoice_for_another_hosts_property(): void
    {
        $otherHost = new User();
        $otherHost->id = 2; // Khác với host_id = 1 của Property
        $otherHost->role = UserRole::HOST;

        $booking = $this->createMockBooking('COMPLETED', 1);

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('Bạn không có quyền tạo hóa đơn cho cơ sở lưu trú của đối tác khác.');

        $this->validator->validateHostAuthorization($booking, $otherHost);
    }

    /**
     * Booking chưa COMPLETED bị từ chối
     */
    public function test_booking_status_must_be_completed(): void
    {
        $booking = $this->createMockBooking('CHECKED_IN', 1);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Chỉ có thể tạo hóa đơn cho đơn đặt phòng đã hoàn tất (COMPLETED). Trạng thái hiện tại: 'CHECKED_IN'.");

        $this->validator->validateBookingStatus($booking);
    }

    /**
     * Customer thiếu số điện thoại bắt buộc (customer_phone NOT NULL) bị từ chối
     */
    public function test_customer_missing_phone_throws_invalid_argument_exception(): void
    {
        $booking = $this->createMockBooking('COMPLETED', 1, null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Thông tin khách hàng thiếu số điện thoại snapshot bắt buộc (customer_phone không thể để trống).');

        $this->validator->validateSnapshots($booking);
    }

    /**
     * Customer chuỗi rỗng phone bị từ chối
     */
    public function test_customer_empty_string_phone_throws_exception(): void
    {
        $booking = $this->createMockBooking('COMPLETED', 1, '   ');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Thông tin khách hàng thiếu số điện thoại snapshot bắt buộc (customer_phone không thể để trống).');

        $this->validator->validateSnapshots($booking);
    }

    /**
     * Dữ liệu room_total không khớp với số đêm × đơn giá bị từ chối
     */
    public function test_financial_integrity_room_total_mismatch_throws_exception(): void
    {
        // 2 đêm × 1.000.000 = 2.000.000, nhưng DB lưu 1.800.000
        $booking = $this->createMockBooking('COMPLETED', 1, '0901234567', 2, '1000000.00', '1800000.00');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Dữ liệu room_total của Booking (1800000.00) không khớp với phép tính snapshot');

        $this->validator->validateFinancialIntegrity($booking);
    }

    /**
     * Dịch vụ có amount không khớp với quantity × unit_price bị từ chối
     */
    public function test_financial_integrity_service_amount_mismatch_throws_exception(): void
    {
        $booking = $this->createMockBooking('COMPLETED', 1, '0901234567', 2, '1000000.00', '2000000.00', [
            ['id' => 1, 'name' => 'Bữa sáng buffet', 'quantity' => '2.00', 'unit_price' => '150000.00', 'amount' => '250000.00'], // Đúng phải là 300000.00
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Thành tiền của dịch vụ 'Bữa sáng buffet' (250000.00) không khớp với phép tính snapshot");

        $this->validator->validateFinancialIntegrity($booking);
    }

    /**
     * Số đêm lưu trú <= 0 bị từ chối
     */
    public function test_non_positive_nights_throws_exception(): void
    {
        $booking = $this->createMockBooking('COMPLETED', 1, '0901234567', 0, '1000000.00', '0.00');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Số đêm lưu trú của Booking phải lớn hơn 0 (nhận được: 0).');

        $this->validator->validateFinancialIntegrity($booking);
    }
}
