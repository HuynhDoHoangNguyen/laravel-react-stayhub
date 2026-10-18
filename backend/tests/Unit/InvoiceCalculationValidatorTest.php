<?php

namespace Tests\Unit;

use App\Validators\Invoice\InvoiceCalculationValidator;
use InvalidArgumentException;
use OverflowException;
use PHPUnit\Framework\TestCase;

class InvoiceCalculationValidatorTest extends TestCase
{
    protected InvoiceCalculationValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new InvoiceCalculationValidator();
    }

    public function test_validate_nights_valid(): void
    {
        $this->validator->validateNights(1);
        $this->validator->validateNights(30);
        $this->assertTrue(true);
    }

    public function test_validate_nights_invalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->validator->validateNights(0);
    }

    public function test_validate_money_valid(): void
    {
        $this->assertSame('100.00', $this->validator->validateMoney('100', 'price'));
        $this->assertSame('100.50', $this->validator->validateMoney('100.5', 'price'));
        $this->assertSame('100.55', $this->validator->validateMoney('100.55', 'price'));
        $this->assertSame('0.00', $this->validator->validateMoney('0', 'price'));
    }

    public function test_validate_money_exceeding_scale(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Độ chính xác');
        $this->validator->validateMoney('100.123', 'price');
    }

    public function test_validate_quantity_valid(): void
    {
        $this->assertSame('2.00', $this->validator->validateQuantity('2'));
        $this->assertSame('1.50', $this->validator->validateQuantity('1.5'));
    }

    public function test_validate_quantity_zero_or_negative(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->validator->validateQuantity('0');
    }

    public function test_assert_discount_within_subtotal(): void
    {
        $this->validator->assertDiscountWithinSubtotal('50.00', '100.00');
        $this->validator->assertDiscountWithinSubtotal('100.00', '100.00');
        $this->assertTrue(true);
    }

    public function test_assert_discount_exceeding_subtotal_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->validator->assertDiscountWithinSubtotal('100.01', '100.00');
    }

    public function test_assert_within_money_limit_overflow(): void
    {
        $this->expectException(OverflowException::class);
        $this->validator->assertWithinMoneyLimit('10000000000.00', 'total');
    }
}
