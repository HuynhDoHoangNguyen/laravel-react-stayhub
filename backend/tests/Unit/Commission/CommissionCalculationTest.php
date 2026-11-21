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
    }

    public function test_commission_base_subtracts_discount(): void
    {
        $result = $this->service->calculate('1500000.00', '300000.00', 1000);

        $this->assertSame('1200000.00', $result['commission_base']);
        $this->assertSame('120000.00', $result['commission_amount']);
        $this->assertSame(CommissionStatus::AWAITING_CUSTOMER_PAYMENT, $result['status']);
    }

    public function test_discount_greater_than_subtotal_floors_base_to_zero(): void
    {
        $result = $this->service->calculate('500000.00', '600000.00', 1000);

        $this->assertSame('0.00', $result['commission_base']);
        $this->assertSame('0.00', $result['commission_amount']);
        $this->assertSame(CommissionStatus::VOID, $result['status']);
    }

    public function test_half_up_rounding_for_vnd(): void
    {
        // 12345.67 * 10% = 1234.567 -> round HALF_UP = 1234.57
        $result = $this->service->calculate('12345.67', '0.00', 1000);

        $this->assertSame('1234.57', $result['commission_amount']);
    }

    public function test_zero_commission_becomes_void(): void
    {
        $result = $this->service->calculate('0.00', '0.00', 1000);

        $this->assertSame('0.00', $result['commission_amount']);
        $this->assertSame(CommissionStatus::VOID, $result['status']);
    }

    public function test_negative_subtotal_throws_invalid_argument(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->calculate('-50000.00', '0.00', 1000);
    }

    public function test_overflow_amount_throws_overflow_exception(): void
    {
        $this->expectException(OverflowException::class);
        $this->service->calculate('1500000000.00', '0.00', 1000);
    }

    public function test_invalid_bps_throws_invalid_argument(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->calculate('100000.00', '0.00', -10);
    }
}
