<?php

namespace Tests\Unit\Commission;

use App\Enums\CommissionStatus;
use App\Services\Commission\CommissionCalculationService;
use InvalidArgumentException;
use OverflowException;
use PHPUnit\Framework\TestCase;

class CommissionCalculationTest extends TestCase
{
    private CommissionCalculationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CommissionCalculationService();
    }

    public function test_standard_10_percent_commission(): void
    {
        $result = $this->service->calculate('1000000.00', '0.00', 1000);

        $this->assertSame('1000000.00', $result['commission_base']);
        $this->assertSame(1000, $result['commission_rate_bps']);
        $this->assertSame('100000.00', $result['commission_amount']);
        $this->assertSame(CommissionStatus::AWAITING_CUSTOMER_PAYMENT, $result['status']);
        $this->assertNull($result['void_reason']);
    }

    public function test_commission_base_subtracts_discount(): void
    {
        $result = $this->service->calculate('1500000.00', '300000.00', 1000);

        $this->assertSame('1200000.00', $result['commission_base']);
        $this->assertSame('120000.00', $result['commission_amount']);
        $this->assertSame(CommissionStatus::AWAITING_CUSTOMER_PAYMENT, $result['status']);
        $this->assertNull($result['void_reason']);
    }

    public function test_discount_greater_than_subtotal_floors_base_to_zero(): void
    {
        $result = $this->service->calculate('500000.00', '600000.00', 1000);

        $this->assertSame('0.00', $result['commission_base']);
        $this->assertSame('0.00', $result['commission_amount']);
        $this->assertSame(CommissionStatus::VOID, $result['status']);
        $this->assertSame('ZERO_COMMISSION', $result['void_reason']);
    }

    /**
     * D14: Base 100005.00 × 10% phải trả 10001.00 (HALF_UP đến đồng).
     */
    public function test_half_up_rounding_to_nearest_dong_for_vnd(): void
    {
        // 100005.00 * 10% = 10000.5000 -> làm tròn HALF_UP đến đồng thành 10001.00
        $resultTechLead = $this->service->calculate('100005.00', '0.00', 1000);
        $this->assertSame('10001.00', $resultTechLead['commission_amount']);

        // 12345.67 * 10% = 1234.5670 -> làm tròn HALF_UP đến đồng thành 1235.00
        $result = $this->service->calculate('12345.67', '0.00', 1000);
        $this->assertSame('1235.00', $result['commission_amount']);

        // 100004.00 * 10% = 10000.4000 -> làm tròn xuống 10000.00
        $resultDown = $this->service->calculate('100004.00', '0.00', 1000);
        $this->assertSame('10000.00', $resultDown['commission_amount']);
    }

    /**
     * MONEY-01: Hóa đơn vượt 1 tỷ (ví dụ 1000000000.01) vẫn hợp lệ theo giới hạn DECIMAL(12,2).
     */
    public function test_amounts_over_one_billion_do_not_overflow(): void
    {
        $overOneBillion = '1000000000.01';
        $result = $this->service->calculate($overOneBillion, '0.00', 1000);

        $this->assertSame('1000000000.01', $result['commission_base']);
        // 1000000000.01 * 10% = 100000000.001 -> làm tròn thành 100000000.00
        $this->assertSame('100000000.00', $result['commission_amount']);

        // Giới hạn tối đa DECIMAL(12,2) = 9999999999.99
        $maxLimit = '9999999999.99';
        $resultMax = $this->service->calculate($maxLimit, '0.00', 1000);
        $this->assertSame('9999999999.99', $resultMax['commission_base']);
        $this->assertSame('1000000000.00', $resultMax['commission_amount']);
    }

    /**
     * D15 & AC-26: Phí bằng 0.00 phải chuyển VOID và có lý do ZERO_COMMISSION.
     */
    public function test_zero_commission_yields_void_status_with_reason(): void
    {
        $result = $this->service->calculate('0.00', '0.00', 1000);

        $this->assertSame('0.00', $result['commission_amount']);
        $this->assertSame(CommissionStatus::VOID, $result['status']);
        $this->assertSame('ZERO_COMMISSION', $result['void_reason']);
    }

    public function test_negative_subtotal_throws_invalid_argument(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->calculate('-50000.00', '0.00', 1000);
    }

    public function test_overflow_amount_throws_overflow_exception(): void
    {
        $this->expectException(OverflowException::class);
        $this->service->calculate('10000000000.00', '0.00', 1000);
    }

    public function test_invalid_bps_throws_invalid_argument(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->calculate('100000.00', '0.00', -10);
    }

    public function test_bps_over_ten_thousand_throws_invalid_argument(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->calculate('100000.00', '0.00', 10001);
    }
}
