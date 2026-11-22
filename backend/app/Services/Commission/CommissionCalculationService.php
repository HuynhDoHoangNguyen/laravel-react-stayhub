<?php

namespace App\Services\Commission;

use App\Enums\CommissionStatus;
use InvalidArgumentException;
use OverflowException;

class CommissionCalculationService
{
    /**
     * Giới hạn an toàn tối đa cho cơ sở tính phí hoa hồng.
     * Đồng bộ với giới hạn tối đa DECIMAL(12,2) của hóa đơn (InvoiceCalculationValidator::MAX_MONEY).
     */
    public const MAX_CALCULATION_AMOUNT = '9999999999.99';

    /**
     * Tính toán cơ sở hoa hồng và số tiền hoa hồng nền tảng bằng số học thập phân chính xác (BCMath).
     *
     * Quy tắc D14 & D15:
     * - commission_base = max(0, subtotal - discount_amount) (Lưu ý: extra_fee không tính vào commission)
     * - Tính hoa hồng trung gian: commission_base * (commission_rate_bps / 10000)
     * - Làm tròn nửa lên (HALF_UP) đến đơn vị ĐỒNG VND nguyên, sau đó format 2 chữ số thập phân (.00)
     * - Nếu commission_amount == '0.00' => status là VOID kèm lý do hệ thống ZERO_COMMISSION (D15/AC-26)
     * - Không sử dụng float hoặc ép kiểu float trong tính toán tiền tệ.
     *
     * @param string|int $subtotal Tổng tiền phòng + dịch vụ
     * @param string|int $discountAmount Số tiền giảm giá
     * @param int $commissionRateBps Tỷ lệ hoa hồng tính theo Basis Points (1000 = 10.00%)
     * @return array{commission_base: string, commission_rate_bps: int, commission_amount: string, status: CommissionStatus, void_reason: ?string}
     */
    public function calculate(string|int $subtotal, string|int $discountAmount = '0.00', int $commissionRateBps = 1000): array
    {
        $cleanSubtotal = $this->normalizeMoney($subtotal, 'subtotal');
        $cleanDiscount = $this->normalizeMoney($discountAmount, 'discount_amount');

        if (bccomp($cleanSubtotal, self::MAX_CALCULATION_AMOUNT, 2) > 0) {
            throw new OverflowException('Số tiền tính toán vượt quá giới hạn tối đa cho phép.');
        }

        if ($commissionRateBps < 0 || $commissionRateBps > 10000) {
            throw new InvalidArgumentException('Tỷ lệ hoa hồng (BPS) phải nằm trong khoảng từ 0 đến 10000.');
        }

        // 1. Cơ sở hoa hồng: max(0, subtotal - discount)
        $rawBase = bcsub($cleanSubtotal, $cleanDiscount, 2);
        $commissionBase = bccomp($rawBase, '0.00', 2) < 0 ? '0.00' : $rawBase;

        // 2. Tính số tiền hoa hồng bằng BCMath thuần:
        // commission_base * commission_rate_bps / 10000
        // Nhân và chia trung gian với scale 4 chữ số thập phân
        $multiplied = bcmul($commissionBase, (string) $commissionRateBps, 4);
        $rawAmount = bcdiv($multiplied, '10000', 4);

        // 3. Quy tắc D14: Làm tròn HALF_UP đến ĐỒNG VND nguyên (0 chữ số thập phân)
        // Bằng cách cộng '0.5' và lấy phần nguyên (scale 0), sau đó định dạng 2 chữ số thập phân (.00)
        $roundedIntegerVnd = bcadd($rawAmount, '0.5', 0);
        $commissionAmount = $roundedIntegerVnd . '.00';

        // 4. Quy tắc D15/AC-26: Nếu số tiền sau làm tròn bằng 0.00 -> VOID với lý do ZERO_COMMISSION
        $isZero = bccomp($commissionAmount, '0.00', 2) === 0;
        $status = $isZero ? CommissionStatus::VOID : CommissionStatus::AWAITING_CUSTOMER_PAYMENT;
        $voidReason = $isZero ? 'ZERO_COMMISSION' : null;

        return [
            'commission_base' => $commissionBase,
            'commission_rate_bps' => $commissionRateBps,
            'commission_amount' => $commissionAmount,
            'status' => $status,
            'void_reason' => $voidReason,
        ];
    }

    /**
     * Chuẩn hóa và xác thực chuỗi số tiền tệ không âm với tối đa 2 chữ số thập phân.
     */
    private function normalizeMoney(string|int $value, string $fieldName): string
    {
        $str = trim((string) $value);

        if ($str === '' || !preg_match('/^\d+(\.\d+)?$/', $str)) {
            throw new InvalidArgumentException("Trường '{$fieldName}' phải là giá trị số không âm hợp lệ.");
        }

        $parts = explode('.', $str, 2);
        $integerPart = $parts[0];
        $fractionalPart = isset($parts[1]) ? substr(str_pad($parts[1], 2, '0'), 0, 2) : '00';

        return $integerPart . '.' . $fractionalPart;
    }
}
