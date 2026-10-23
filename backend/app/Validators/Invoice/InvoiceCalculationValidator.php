<?php

namespace App\Validators\Invoice;

use InvalidArgumentException;
use OverflowException;

class InvoiceCalculationValidator
{
    /**
     * Giới hạn tối đa của DECIMAL(12,2) trong MySQL (10 chữ số nguyên, 2 chữ số thập phân).
     */
    public const MAX_MONEY = '9999999999.99';

    /**
     * Giới hạn tối đa của DECIMAL(10,2) cho quantity trong MySQL (8 chữ số nguyên, 2 chữ số thập phân).
     */
    public const MAX_QUANTITY = '99999999.99';

    /**
     * Validate số đêm lưu trú (> 0).
     */
    public function validateNights(int $nights): void
    {
        if ($nights <= 0) {
            throw new InvalidArgumentException("Số đêm lưu trú phải là số nguyên dương lớn hơn 0 (nhận được: {$nights}).");
        }
    }

    /**
     * Validate và chuẩn hóa giá trị tiền tệ:
     * - Không rỗng, không âm, đúng định dạng số.
     * - Không vượt quá 2 chữ số thập phân (chuẩn DECIMAL(12,2)).
     * - Không vượt quá giới hạn MAX_MONEY.
     *
     * @param string|int $value Giá trị tiền tệ đầu vào
     * @param string $fieldName Tên trường hiển thị khi báo lỗi
     * @return string Giá trị tiền đã chuẩn hóa 2 chữ số thập phân
     */
    public function validateMoney(string|int $value, string $fieldName): string
    {
        $str = trim((string) $value);

        if ($str === '' || !preg_match('/^\d+(\.\d+)?$/', $str)) {
            throw new InvalidArgumentException("Trường '{$fieldName}' phải là giá trị số không âm hợp lệ (nhận được: '{$str}').");
        }

        $parts = explode('.', $str, 2);
        if (isset($parts[1]) && strlen($parts[1]) > 2) {
            throw new InvalidArgumentException("Độ chính xác của '{$fieldName}' không được vượt quá 2 chữ số thập phân của schema DECIMAL(12,2) (nhận được: '{$str}').");
        }

        $normalized = $this->roundHalfUp($str, 2);
        $this->assertWithinMoneyLimit($normalized, $fieldName);

        return $normalized;
    }

    /**
     * Validate và chuẩn hóa số lượng dịch vụ:
     * - Không rỗng, lớn hơn 0, đúng định dạng số.
     * - Không vượt quá 2 chữ số thập phân (chuẩn DECIMAL(10,2)).
     * - Không vượt quá MAX_QUANTITY.
     *
     * @param string|int $value Số lượng dịch vụ đầu vào
     * @return string Số lượng đã chuẩn hóa 2 chữ số thập phân
     */
    public function validateQuantity(string|int $value): string
    {
        $str = trim((string) $value);

        if ($str === '' || !preg_match('/^\d+(\.\d+)?$/', $str)) {
            throw new InvalidArgumentException("Số lượng dịch vụ (quantity) phải là giá trị số dương hợp lệ (nhận được: '{$str}').");
        }

        $parts = explode('.', $str, 2);
        if (isset($parts[1]) && strlen($parts[1]) > 2) {
            throw new InvalidArgumentException("Độ chính xác của số lượng dịch vụ không được vượt quá 2 chữ số thập phân của schema DECIMAL(10,2) (nhận được: '{$str}').");
        }

        $normalized = $this->roundHalfUp($str, 2);

        if (bccomp($normalized, '0.00', 2) <= 0) {
            throw new InvalidArgumentException("Số lượng dịch vụ (quantity) phải lớn hơn 0 (nhận được: '{$str}').");
        }

        if (bccomp($normalized, self::MAX_QUANTITY, 2) === 1) {
            throw new OverflowException("Số lượng dịch vụ vượt quá giới hạn tối đa của DECIMAL(10,2) (" . self::MAX_QUANTITY . ").");
        }

        return $normalized;
    }

    /**
     * Kiểm tra số tiền không vượt quá giới hạn lưu trữ của DECIMAL(12,2).
     */
    public function assertWithinMoneyLimit(string $amount, string $fieldName): void
    {
        if (bccomp($amount, self::MAX_MONEY, 2) === 1) {
            throw new OverflowException("Giá trị '{$fieldName}' ({$amount}) vượt quá giới hạn lưu trữ tối đa của DECIMAL(12,2) (" . self::MAX_MONEY . ").");
        }
    }

    /**
     * Kiểm tra số tiền giảm giá không được vượt quá tạm tính (subtotal).
     */
    public function assertDiscountWithinSubtotal(string $discount, string $subtotal): void
    {
        if (bccomp($discount, $subtotal, 2) === 1) {
            throw new InvalidArgumentException("Số tiền giảm giá ({$discount}) không được vượt quá tạm tính ({$subtotal}).");
        }
    }

    /**
     * Làm tròn nửa lên (ROUND_HALF_UP) bằng chuỗi và BCMath (không dùng float).
     */
    public function roundHalfUp(string $val, int $scale = 2): string
    {
        $parts = explode('.', $val, 2);
        $integer = $parts[0];
        $decimal = $parts[1] ?? '';

        if (strlen($decimal) <= $scale) {
            return $integer . '.' . str_pad($decimal, $scale, '0');
        }

        $base = $integer . '.' . substr($decimal, 0, $scale);
        $nextDigit = (int) $decimal[$scale];

        if ($nextDigit >= 5) {
            $increment = '0.' . str_repeat('0', $scale - 1) . '1';
            return bcadd($base, $increment, $scale);
        }

        return $base;
    }
}
