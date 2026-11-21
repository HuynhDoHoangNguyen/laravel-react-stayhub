<?php

namespace App\Services\Commission;

use App\Enums\CommissionStatus;
use InvalidArgumentException;
use OverflowException;

class CommissionCalculationService
{
    /**
     * Giới hạn an toàn tối đa cho cơ sở tính phí hoa hồng (1 tỷ VND).
     */
    public const MAX_CALCULATION_AMOUNT = '1000000000.00';

    /**
     * Tính toán cơ sở hoa hồng và số tiền hoa hồng nền tảng bằng số học thập phân chính xác.
     *
     * Công thức:
     * - commission_base = max(0, subtotal - discount_amount) (Lưu ý: extra_fee không tính vào commission)
     * - commission_amount = round(commission_base * (commission_rate_bps / 10000), 2)
     * - Nếu commission_amount == 0.00 => status là VOID
     *
     * @param string|float|int $subtotal Tổng tiền phòng + dịch vụ
     * @param string|float|int $discountAmount Số tiền giảm giá
     * @param int $commissionRateBps Tỷ lệ hoa hồng tính theo Basis Points (1000 = 10.00%)
     * @return array{commission_base: string, commission_rate_bps: int, commission_amount: string, status: CommissionStatus}
     */
    public function calculate(string|float|int $subtotal, string|float|int $discountAmount = '0.00', int $commissionRateBps = 1000): array
    {
        $subtotalStr = number_format((float) $subtotal, 2, '.', '');
        $discountStr = number_format((float) $discountAmount, 2, '.', '');

        if (bccomp($subtotalStr, '0.00', 2) < 0) {
            throw new InvalidArgumentException('Subtotal không thể là số âm.');
        }

        if (bccomp($subtotalStr, self::MAX_CALCULATION_AMOUNT, 2) > 0) {
            throw new OverflowException('Số tiền tính toán vượt quá giới hạn an toàn tối đa cho phép.');
        }

        if ($commissionRateBps < 0 || $commissionRateBps > 10000) {
            throw new InvalidArgumentException('Tỷ lệ hoa hồng (BPS) phải nằm trong khoảng từ 0 đến 10000.');
        }

        // 1. Cơ sở hoa hồng: max(0, subtotal - discount)
        $rawBase = bcsub($subtotalStr, $discountStr, 2);
        $commissionBase = bccomp($rawBase, '0.00', 2) < 0 ? '0.00' : $rawBase;

        // 2. Tính số tiền hoa hồng: commission_base * commission_rate_bps / 10000
        // Tính trung gian với độ chính xác 4 chữ số thập phân trước khi làm tròn 2 chữ số (HALF_UP)
        $multiplied = bcmul($commissionBase, (string) $commissionRateBps, 4);
        $divided = bcdiv($multiplied, '10000', 4);

        // Làm tròn HALF_UP đến 2 chữ số thập phân
        $commissionAmount = number_format(round((float) $divided, 2, PHP_ROUND_HALF_UP), 2, '.', '');

        // 3. Nếu số tiền sau làm tròn bằng 0.00 -> VOID (AC-26)
        $status = bccomp($commissionAmount, '0.00', 2) === 0
            ? CommissionStatus::VOID
            : CommissionStatus::AWAITING_CUSTOMER_PAYMENT;

        return [
            'commission_base' => $commissionBase,
            'commission_rate_bps' => $commissionRateBps,
            'commission_amount' => $commissionAmount,
            'status' => $status,
        ];
    }
}
