<?php

namespace App\Services\Commission;

use App\Enums\CommissionStatus;
use App\Models\Booking;
use App\Models\BookingCommission;
use App\Models\Invoice;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;

class CommissionChargeService
{
    public function __construct(
        protected readonly CommissionCalculationService $calculationService,
        protected readonly LoggerInterface $logger,
    ) {}

    /**
     * Tạo khoản hoa hồng phải thu (BookingCommission) atomically cho Invoice.
     * Nếu Booking không có snapshot hoa hồng (legacy booking) thì bỏ qua không sinh charge (AC-09).
     */
    public function createChargeForInvoice(Invoice $invoice, Booking $booking): ?BookingCommission
    {
        // 1. Kiểm tra điều kiện có snapshot hoa hồng
        if ($booking->commission_rate_bps === null || $booking->host_agreement_acceptance_id === null) {
            $this->logger->info('Bỏ qua tạo hoa hồng cho đơn booking legacy không có snapshot', [
                'booking_id' => $booking->id,
                'invoice_id' => $invoice->id,
            ]);

            return null;
        }

        // 2. Kiểm tra nếu đã tồn tại commission cho Invoice này (Idempotency)
        $existing = BookingCommission::query()->where('invoice_id', $invoice->id)->first();
        if ($existing !== null) {
            return $existing;
        }

        // 3. Xác định Host sở hữu
        $booking->loadMissing('room.property');
        $hostId = (int) ($booking->room?->property?->host_id ?? 0);
        if ($hostId <= 0) {
            $this->logger->warning('Không thể xác định host_id cho đơn booking khi tạo hoa hồng', [
                'booking_id' => $booking->id,
                'invoice_id' => $invoice->id,
            ]);

            return null;
        }

        // 4. Tính toán hoa hồng chính xác qua CommissionCalculationService
        $calc = $this->calculationService->calculate(
            $invoice->subtotal,
            $invoice->discount_amount,
            (int) $booking->commission_rate_bps
        );

        // 5. Sinh mã chuyển khoản duy nhất (Unique Reference Code)
        $referenceCode = $this->generateUniqueReferenceCode();

        // 6. Tạo bản ghi BookingCommission
        $commission = BookingCommission::query()->create([
            'invoice_id' => $invoice->id,
            'booking_id' => $booking->id,
            'host_id' => $hostId,
            'host_agreement_acceptance_id' => $booking->host_agreement_acceptance_id,
            'reference_code' => $referenceCode,
            'commission_base' => $calc['commission_base'],
            'commission_rate_bps' => $calc['commission_rate_bps'],
            'commission_amount' => $calc['commission_amount'],
            'currency' => 'VND',
            'status' => $calc['status'],
            'void_reason' => $calc['void_reason'] ?? null,
            'due_at' => null,
            'settled_at' => null,
        ]);

        $this->logger->info('Tạo khoản hoa hồng nền tảng thành công cho hóa đơn', [
            'commission_id' => $commission->id,
            'invoice_id' => $invoice->id,
            'reference_code' => $referenceCode,
            'amount' => $commission->commission_amount,
            'status' => $commission->status->value,
        ]);

        return $commission;
    }

    /**
     * Kích hoạt hạn nộp hoa hồng (72 giờ) khi Hóa đơn chuyển trạng thái sang PAID.
     * Hành vi là idempotent: nếu commission đã DUE, SETTLED hoặc VOID thì không thay đổi.
     */
    public function activateDue(int $invoiceId, CarbonInterface|string $paidAt): ?BookingCommission
    {
        $commission = BookingCommission::query()->where('invoice_id', $invoiceId)->first();

        if ($commission === null) {
            return null;
        }

        // Chỉ chuyển DUE khi đang ở trạng thái AWAITING_CUSTOMER_PAYMENT
        if ($commission->status !== CommissionStatus::AWAITING_CUSTOMER_PAYMENT) {
            return $commission;
        }

        $paidAtCarbon = $paidAt instanceof CarbonInterface ? $paidAt : Carbon::parse($paidAt);
        $windowHours = (int) config('commission.payment_window_hours', 72);
        $dueAt = $paidAtCarbon->copy()->addHours($windowHours);

        $commission->update([
            'status' => CommissionStatus::DUE,
            'due_at' => $dueAt,
        ]);

        $this->logger->info('Kích hoạt nghĩa vụ nộp hoa hồng 72h cho Host', [
            'commission_id' => $commission->id,
            'invoice_id' => $invoiceId,
            'status' => CommissionStatus::DUE->value,
            'paid_at' => $paidAtCarbon->toIso8601String(),
            'due_at' => $dueAt->toIso8601String(),
        ]);

        return $commission;
    }

    /**
     * Sinh mã chuyển khoản duy nhất có định dạng SHF-XXXXXXXX (ví dụ: SHF-A1B2C3D4).
     */
    protected function generateUniqueReferenceCode(): string
    {
        $prefix = (string) config('commission.reference_prefix', 'SHF');

        for ($i = 0; $i < 5; $i++) {
            $candidate = $prefix . '-' . strtoupper(Str::random(8));
            if (! BookingCommission::query()->where('reference_code', $candidate)->exists()) {
                return $candidate;
            }
        }

        return $prefix . '-' . strtoupper(Str::random(10));
    }
}
