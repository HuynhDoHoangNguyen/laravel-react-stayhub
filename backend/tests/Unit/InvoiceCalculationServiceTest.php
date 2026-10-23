<?php

namespace Tests\Unit;

use App\Services\Invoice\InvoiceCalculationService;
use InvalidArgumentException;
use OverflowException;
use PHPUnit\Framework\TestCase;

class InvoiceCalculationServiceTest extends TestCase
{
    protected InvoiceCalculationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new InvoiceCalculationService();
    }

    /**
     * Case 1 — Chỉ có tiền phòng
     * 3 đêm × 800000 = 2400000.00
     */
    public function test_calculate_room_total_only(): void
    {
        $roomTotal = $this->service->calculateRoomTotal(3, '800000');
        $this->assertSame('2400000.00', $roomTotal);

        $result = $this->service->calculate(3, '800000');
        $this->assertSame('2400000.00', $result['room_total']);
        $this->assertSame('0.00', $result['service_total']);
        $this->assertSame('2400000.00', $result['subtotal']);
        $this->assertSame('0.00', $result['discount_amount']);
        $this->assertSame('0.00', $result['extra_fee']);
        $this->assertSame('2400000.00', $result['total_amount']);
    }

    /**
     * Case 2 — Có dịch vụ
     * Room total 2400000, Breakfast: 2 × 100000 = 200000, Laundry: 3 × 50000 = 150000
     * Subtotal = 2750000.00
     */
    public function test_calculate_with_services(): void
    {
        $breakfast = $this->service->calculateServiceAmount('2', '100000');
        $this->assertSame('200000.00', $breakfast);

        $laundry = $this->service->calculateServiceAmount('3', '50000');
        $this->assertSame('150000.00', $laundry);

        $serviceTotal = $this->service->calculateServiceTotal([
            ['quantity' => '2', 'unit_price' => '100000'],
            ['quantity' => '3', 'unit_price' => '50000'],
        ]);
        $this->assertSame('350000.00', $serviceTotal);

        $subtotal = $this->service->calculateSubtotal('2400000.00', $serviceTotal);
        $this->assertSame('2750000.00', $subtotal);
    }

    /**
     * Case 3 — Có giảm giá và phụ phí
     * Subtotal = 2750000, Discount = 100000, Extra fee = 50000 -> Total = 2700000.00
     */
    public function test_calculate_with_discount_and_extra_fee(): void
    {
        $total = $this->service->calculateTotalAmount('2750000.00', '100000.00', '50000.00');
        $this->assertSame('2700000.00', $total);

        $result = $this->service->calculate(
            3,
            '800000',
            [
                ['name' => 'Breakfast', 'quantity' => '2', 'unit_price' => '100000'],
                ['name' => 'Laundry', 'quantity' => '3', 'unit_price' => '50000'],
            ],
            '100000',
            '50000'
        );

        $this->assertSame('2400000.00', $result['room_total']);
        $this->assertSame('350000.00', $result['service_total']);
        $this->assertSame('2750000.00', $result['subtotal']);
        $this->assertSame('100000.00', $result['discount_amount']);
        $this->assertSame('50000.00', $result['extra_fee']);
        $this->assertSame('2700000.00', $result['total_amount']);
    }

    /**
     * Case 4 — Không sử dụng dịch vụ
     */
    public function test_calculate_with_no_services(): void
    {
        $serviceTotal = $this->service->calculateServiceTotal([]);
        $this->assertSame('0.00', $serviceTotal);

        $subtotal = $this->service->calculateSubtotal('1500000.00', $serviceTotal);
        $this->assertSame('1500000.00', $subtotal);
    }

    /**
     * Case 5 — Giá có phần thập phân
     * 3 đêm × 800000.50 = 2400001.50
     */
    public function test_calculate_price_with_decimal_places(): void
    {
        $roomTotal = $this->service->calculateRoomTotal(3, '800000.50');
        $this->assertSame('2400001.50', $roomTotal);
    }

    /**
     * Case 6 — Quantity dịch vụ có phần thập phân DECIMAL(10,2)
     * 1.50 kg × 50000 = 75000.00
     */
    public function test_calculate_service_quantity_with_decimals(): void
    {
        $amount = $this->service->calculateServiceAmount('1.50', '50000');
        $this->assertSame('75000.00', $amount);

        $amount2 = $this->service->calculateServiceAmount('2.25', '100000');
        $this->assertSame('225000.00', $amount2);
    }

    /**
     * Case 7 — Discount lớn hơn Subtotal phải bị từ chối
     */
    public function test_discount_greater_than_subtotal_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('vượt quá tạm tính');

        $this->service->calculateTotalAmount('100000.00', '150000.00', '0.00');
    }

    /**
     * Case 8 — Dữ liệu âm hoặc sai định dạng bị từ chối
     */
    public function test_negative_or_invalid_inputs_throw_exception(): void
    {
        // Số đêm <= 0
        try {
            $this->service->calculateRoomTotal(0, '500000');
            $this->fail('Expected exception for nights <= 0');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Số đêm', $e->getMessage());
        }

        // Giá phòng âm
        try {
            $this->service->calculateRoomTotal(2, '-500000');
            $this->fail('Expected exception for negative price');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('không âm', $e->getMessage());
        }

        // Số lượng dịch vụ <= 0
        try {
            $this->service->calculateServiceAmount('0', '100000');
            $this->fail('Expected exception for zero quantity');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('lớn hơn 0', $e->getMessage());
        }

        // Đơn giá dịch vụ âm
        try {
            $this->service->calculateServiceAmount('1', '-100000');
            $this->fail('Expected exception for negative unit price');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('không âm', $e->getMessage());
        }

        // Chuỗi chữ cái không phải số
        try {
            $this->service->calculateRoomTotal(2, 'abc');
            $this->fail('Expected exception for non-numeric price');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('hợp lệ', $e->getMessage());
        }
    }

    /**
     * Case 9 — Tính chuẩn xác ROUND_HALF_UP và không dùng float
     */
    public function test_round_half_up_precision_without_float(): void
    {
        // 1.55 × 10.55 = 16.3525 -> làm tròn thành 16.35
        $amount1 = $this->service->calculateServiceAmount('1.55', '10.55');
        $this->assertSame('16.35', $amount1);

        // 1.57 × 10.55 = 16.5635 -> làm tròn thành 16.56
        $amount2 = $this->service->calculateServiceAmount('1.57', '10.55');
        $this->assertSame('16.56', $amount2);

        // 1.50 × 12.55 = 18.8250 -> làm tròn thành 18.83
        $amount3 = $this->service->calculateServiceAmount('1.50', '12.55');
        $this->assertSame('18.83', $amount3);

        // Test trực tiếp hàm roundHalfUp
        $this->assertSame('12.34', $this->service->roundHalfUp('12.344', 2));
        $this->assertSame('12.35', $this->service->roundHalfUp('12.345', 2));
        $this->assertSame('12.35', $this->service->roundHalfUp('12.346', 2));
        $this->assertSame('13.00', $this->service->roundHalfUp('12.999', 2));
    }

    /**
     * Case 10 — Không làm thay đổi dữ liệu đầu vào (Side-effect free)
     */
    public function test_immutability_and_no_side_effects(): void
    {
        $originalServices = [
            ['name' => 'Breakfast', 'quantity' => '2', 'unit_price' => '100000'],
            ['name' => 'Laundry', 'quantity' => '3', 'unit_price' => '50000'],
        ];

        $servicesCopy = $originalServices;

        $result = $this->service->calculate(2, '500000', $servicesCopy, '50000', '20000');

        // Mảng gốc không bị thay đổi
        $this->assertSame($originalServices, $servicesCopy);
        $this->assertIsArray($result);
        $this->assertSame('1000000.00', $result['room_total']);
        $this->assertSame('350000.00', $result['service_total']);
        $this->assertSame('1350000.00', $result['subtotal']);
        $this->assertSame('50000.00', $result['discount_amount']);
        $this->assertSame('20000.00', $result['extra_fee']);
        $this->assertSame('1320000.00', $result['total_amount']);
    }

    /**
     * Case 11 — Giới hạn lưu trữ DECIMAL và Overflow Protection
     */
    public function test_overflow_protection_for_decimal_limits(): void
    {
        // Vượt quá MAX_MONEY (9,999,999,999.99)
        $this->expectException(OverflowException::class);
        $this->service->calculateRoomTotal(10, '1000000000.00'); // 10 tỷ
    }

    /**
     * Case 12 — Từ chối độ chính xác đầu vào vượt quá schema (hơn 2 chữ số thập phân)
     */
    public function test_reject_precision_exceeding_schema(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Độ chính xác');

        $this->service->calculateRoomTotal(2, '500000.123'); // 3 số thập phân
    }

    /**
     * Case 13 — Ghi log thực thi khi tính toán hóa đơn
     */
    public function test_logging_execution_with_logger(): void
    {
        $mockLogger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $mockLogger->expects($this->exactly(2))
            ->method('info');

        $serviceWithLogger = new InvoiceCalculationService(null, $mockLogger);
        $serviceWithLogger->calculate(2, '500000.00');
    }
}
