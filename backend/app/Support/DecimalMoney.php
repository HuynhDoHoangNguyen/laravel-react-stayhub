<?php

namespace App\Support;

use App\Services\Invoice\InvoiceCalculationService;
use InvalidArgumentException;
use OverflowException;

/** Decimal normalization and arithmetic shared by TV2 booking workflows. */
final class DecimalMoney
{
    public const MONEY_SCALE = 2;
    public const MAX_MONEY = '9999999999.99';
    public const MAX_BOOKING_SERVICE_QUANTITY = '9999.99';
    public const MAX_STORED_QUANTITY = '99999999.99';

    private static ?InvoiceCalculationService $invoiceCalculator = null;

    public static function normalizeMoney(mixed $value, string $field = 'amount'): string
    {
        $decimal = self::toDecimalString($value, $field);
        if (bccomp($decimal, '0', max(0, self::scaleOf($decimal))) < 0) {
            throw new InvalidArgumentException("Trường '{$field}' phải là giá trị không âm hợp lệ.");
        }

        $normalized = self::roundHalfUp($decimal, self::MONEY_SCALE);

        self::assertAtMost($normalized, self::MAX_MONEY, $field);

        return $normalized;
    }

    public static function normalizeQuantity(mixed $value, string $field = 'quantity'): string
    {
        $normalized = self::normalizeStoredQuantity($value, $field);
        self::assertAtMost($normalized, self::MAX_BOOKING_SERVICE_QUANTITY, $field);

        return $normalized;
    }

    public static function normalizeStoredQuantity(mixed $value, string $field = 'quantity'): string
    {
        $decimal = self::toDecimalString($value, $field);
        if (bccomp($decimal, '0', max(0, self::scaleOf($decimal))) < 0) {
            throw new InvalidArgumentException("Trường '{$field}' phải là giá trị không âm hợp lệ.");
        }
        $normalized = self::roundHalfUp($decimal, self::MONEY_SCALE);

        if (bccomp($normalized, '0.01', self::MONEY_SCALE) < 0) {
            throw new InvalidArgumentException("Trường '{$field}' phải ít nhất là 0.01.");
        }

        self::assertAtMost($normalized, self::MAX_STORED_QUANTITY, $field);

        return $normalized;
    }

    /** Normalize nonnegative numeric query input without rounding its precision. */
    public static function queryDecimal(mixed $value, string $field): string
    {
        $decimal = self::toDecimalString($value, $field);
        if (str_starts_with($decimal, '-')) {
            throw new InvalidArgumentException("Trường '{$field}' phải là giá trị không âm hợp lệ.");
        }

        return $decimal;
    }

    public static function compare(mixed $left, mixed $right): int
    {
        $leftDecimal = self::toDecimalString($left, 'left');
        $rightDecimal = self::toDecimalString($right, 'right');
        $scale = max(self::scaleOf($leftDecimal), self::scaleOf($rightDecimal));

        return bccomp($leftDecimal, $rightDecimal, $scale);
    }

    public static function multiply(mixed $left, mixed $right): string
    {
        $leftDecimal = self::normalizeMoney($left, 'left');
        $rightDecimal = self::normalizeMoney($right, 'right');
        $product = bcmul($leftDecimal, $rightDecimal, 4);
        $result = self::roundHalfUp($product, self::MONEY_SCALE);
        self::assertAtMost($result, self::MAX_MONEY, 'amount');

        return $result;
    }

    /** @param iterable<mixed> $values */
    public static function sum(iterable $values): string
    {
        $total = '0.00';
        foreach ($values as $value) {
            $total = bcadd($total, self::normalizeMoney($value), self::MONEY_SCALE);
            self::assertAtMost($total, self::MAX_MONEY, 'total');
        }

        return $total;
    }

    public static function addQuantity(mixed $left, mixed $right): string
    {
        $total = bcadd(
            self::normalizeStoredQuantity($left),
            self::normalizeStoredQuantity($right),
            self::MONEY_SCALE,
        );
        self::assertAtMost($total, self::MAX_STORED_QUANTITY, 'quantity');

        return $total;
    }

    public static function format(mixed $value): string
    {
        return self::normalizeMoney($value);
    }

    public static function formatNullable(mixed $value): ?string
    {
        return $value === null ? null : self::format($value);
    }

    /** The only TV2 adapter to TV3's public HALF_UP implementation. */
    public static function roundHalfUp(string $value, int $scale = self::MONEY_SCALE): string
    {
        self::$invoiceCalculator ??= new InvoiceCalculationService();

        return self::$invoiceCalculator->roundHalfUp($value, $scale);
    }

    private static function toDecimalString(mixed $value, string $field): string
    {
        if (is_float($value)) {
            if (! is_finite($value)) {
                throw new InvalidArgumentException("Trường '{$field}' phải là giá trị số hữu hạn.");
            }
            $value = (string) $value;
        } elseif (! is_string($value) && ! is_int($value)) {
            throw new InvalidArgumentException("Trường '{$field}' phải là giá trị số hợp lệ.");
        }

        $input = trim((string) $value);
        if (! preg_match('/^([+-]?)(\d+)(?:\.(\d*))?(?:[eE]([+-]?\d+))?$/', $input, $matches)) {
            throw new InvalidArgumentException("Trường '{$field}' phải là giá trị số hợp lệ.");
        }

        $sign = $matches[1] === '-' ? '-' : '';
        $integer = $matches[2];
        $fraction = $matches[3] ?? '';
        $exponent = isset($matches[4]) ? (int) $matches[4] : 0;
        if (abs($exponent) > 100) {
            throw new OverflowException("Trường '{$field}' vượt quá miền số được hỗ trợ.");
        }

        $digits = $integer . $fraction;
        $point = strlen($integer) + $exponent;
        if ($point <= 0) {
            $expanded = '0.' . str_repeat('0', -$point) . $digits;
        } elseif ($point >= strlen($digits)) {
            $expanded = $digits . str_repeat('0', $point - strlen($digits));
        } else {
            $expanded = substr($digits, 0, $point) . '.' . substr($digits, $point);
        }

        if (str_contains($expanded, '.')) {
            [$whole, $decimal] = explode('.', $expanded, 2);
            $expanded = ltrim($whole, '0') ?: '0';
            $expanded .= '.' . $decimal;
        } else {
            $expanded = ltrim($expanded, '0') ?: '0';
        }

        return ($sign === '-' && bccomp($expanded, '0', max(0, self::scaleOf($expanded))) !== 0 ? '-' : '') . $expanded;
    }

    private static function scaleOf(string $value): int
    {
        return str_contains($value, '.') ? strlen(substr(strrchr($value, '.'), 1)) : 0;
    }

    private static function assertAtMost(string $value, string $maximum, string $field): void
    {
        if (bccomp($value, $maximum, self::MONEY_SCALE) > 0) {
            throw new OverflowException("Trường '{$field}' vượt quá giới hạn {$maximum}.");
        }
    }
}
