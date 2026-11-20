<?php

namespace Tests\Unit;

use App\Services\Invoice\InvoiceCalculationService;
use App\Support\DecimalMoney;
use PHPUnit\Framework\TestCase;

class DecimalMoneyTest extends TestCase
{
    public function test_money_products_match_invoice_calculation_results(): void
    {
        $invoice = new InvoiceCalculationService();

        $cases = [
            ['0.10', 3, $invoice->calculateRoomTotal(3, '0.10')],
            ['1234567.89', 7, $invoice->calculateRoomTotal(7, '1234567.89')],
            ['99999999.99', 1, $invoice->calculateRoomTotal(1, '99999999.99')],
            ['0.01', '0.01', $invoice->calculateServiceAmount('0.01', '0.01')],
        ];

        foreach ($cases as [$left, $right, $invoiceResult]) {
            self::assertSame($invoiceResult, DecimalMoney::multiply($left, $right));
        }
    }

    public function test_half_up_rounding_matches_invoice_calculator(): void
    {
        $invoice = new InvoiceCalculationService();

        foreach (['0.005', '0.015', '0.025', '1.005', '1.015'] as $value) {
            self::assertSame(
                $invoice->roundHalfUp($value, 2),
                DecimalMoney::roundHalfUp($value, 2),
            );
        }

        self::assertSame('0.01', DecimalMoney::format('0.005'));
        self::assertSame('0.02', DecimalMoney::format('0.015'));
        self::assertSame('0.03', DecimalMoney::format('0.025'));
    }

    public function test_input_normalization_preserves_old_two_decimal_rounding(): void
    {
        self::assertSame('1.24', DecimalMoney::normalizeMoney('1.235'));
        self::assertSame('1.24', DecimalMoney::normalizeQuantity('1.235'));
        self::assertSame('0.01', DecimalMoney::normalizeQuantity('0.01'));
        self::assertSame('9999.99', DecimalMoney::normalizeQuantity('9999.99'));
        self::assertSame('0.30', DecimalMoney::addQuantity('0.10', '0.20'));
        self::assertSame('10000.00', DecimalMoney::addQuantity('0.01', '9999.99'));
    }

    public function test_decimal_output_preserves_null_and_uses_two_places(): void
    {
        self::assertSame('123456.78', DecimalMoney::format('123456.78'));
        self::assertSame('370370.34', DecimalMoney::format('370370.34'));
        self::assertNull(DecimalMoney::formatNullable(null));
    }

    public function test_search_decimal_comparison_keeps_input_precision(): void
    {
        self::assertSame(-1, DecimalMoney::compare('100.004', '100.005'));
        self::assertSame(0, DecimalMoney::compare('1e2', '100.00'));
        self::assertSame('100.005', DecimalMoney::queryDecimal('100.005', 'min_price'));
    }
}
