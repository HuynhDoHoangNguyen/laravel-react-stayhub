<?php

namespace App\Services\Commission;

use App\Enums\CommissionStatus;
use App\Enums\RemittanceStatus;
use App\Models\BookingCommission;
use App\Models\CommissionRemittance;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class AdminCommissionService
{
    /**
     * Danh sách toàn bộ hoa hồng hệ thống dành cho Admin.
     */
    public function listCommissions(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = BookingCommission::query()
            ->with([
                'host:id,name,email,phone',
                'invoice:id,invoice_code,subtotal,discount_amount,extra_fee,total_amount,payment_status,paid_at',
                'booking:id,booking_code,check_in_date,check_out_date,room_id',
                'booking.room:id,name,property_id',
                'booking.room.property:id,name',
                'remittances' => fn ($q) => $q->orderByDesc('id'),
            ]);

        if (! empty($filters['host_id'])) {
            $query->where('host_id', (int) $filters['host_id']);
        }

        $statusFilter = strtoupper((string) ($filters['status'] ?? 'ALL'));
        if ($statusFilter !== 'ALL' && $statusFilter !== '') {
            if ($statusFilter === 'OVERDUE') {
                $query->where('status', CommissionStatus::DUE)
                    ->whereNotNull('due_at')
                    ->where('due_at', '<', now())
                    ->whereDoesntHave('remittances', fn ($q) => $q->where('status', RemittanceStatus::SUBMITTED));
            } elseif ($statusFilter === 'UNDER_REVIEW') {
                $query->where('status', CommissionStatus::DUE)
                    ->whereHas('remittances', fn ($q) => $q->where('status', RemittanceStatus::SUBMITTED));
            } elseif (in_array($statusFilter, ['AWAITING_CUSTOMER_PAYMENT', 'DUE', 'SETTLED', 'DISPUTED', 'VOID'], true)) {
                $query->where('status', $statusFilter);
            }
        }

        $paginator = $query->orderByDesc('id')->paginate($perPage);

        $paginator->getCollection()->transform(function (BookingCommission $item) {
            $hasPending = $item->remittances->contains(fn ($r) => $r->status === RemittanceStatus::SUBMITTED);
            $displayStatus = $item->status->value;

            if ($item->status === CommissionStatus::DUE) {
                if ($hasPending) {
                    $displayStatus = 'UNDER_REVIEW';
                } elseif ($item->due_at !== null && now()->greaterThan($item->due_at)) {
                    $displayStatus = 'OVERDUE';
                } else {
                    $displayStatus = 'DUE';
                }
            }

            return [
                'id' => $item->id,
                'reference_code' => $item->reference_code,
                'host_id' => $item->host_id,
                'host_name' => $item->host?->name,
                'host_phone' => $item->host?->phone,
                'invoice_id' => $item->invoice_id,
                'invoice_code' => $item->invoice?->invoice_code,
                'booking_id' => $item->booking_id,
                'booking_code' => $item->booking?->booking_code,
                'property_name' => $item->booking?->room?->property?->name,
                'commission_base' => $item->commission_base,
                'commission_rate_bps' => $item->commission_rate_bps,
                'commission_amount' => $item->commission_amount,
                'status' => $item->status->value,
                'display_status' => $displayStatus,
                'due_at' => $item->due_at?->toIso8601String(),
                'settled_at' => $item->settled_at?->toIso8601String(),
                'created_at' => $item->created_at?->toIso8601String(),
            ];
        });

        return $paginator;
    }

    /**
     * Hàng chờ khai báo đối soát (Remittance Queue) dành cho Admin.
     */
    public function listRemittances(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = CommissionRemittance::query()
            ->with([
                'host:id,name,email,phone',
                'reviewedByAdmin:id,name',
                'commission.invoice:id,invoice_code,total_amount,paid_at',
                'commission.booking.room.property:id,name',
            ]);

        $status = strtoupper((string) ($filters['status'] ?? 'SUBMITTED'));
        if ($status !== 'ALL' && $status !== '') {
            if (in_array($status, ['SUBMITTED', 'VERIFIED', 'REJECTED'], true)) {
                $query->where('status', $status);
            }
        }

        return $query->orderByDesc('submitted_at')->paginate($perPage);
    }

    /**
     * Admin xác minh giao dịch đối soát (Verify).
     * Tuân thủ quy tắc kiểm tra trạng thái và settlement atomic (REM-01, AC-17).
     */
    public function verifyRemittance(int $remittanceId, int $adminId, array $data): array
    {
        return DB::transaction(function () use ($remittanceId, $adminId, $data) {
            /** @var CommissionRemittance|null $remittance */
            $remittance = CommissionRemittance::query()
                ->where('id', $remittanceId)
                ->lockForUpdate()
                ->first();

            if ($remittance === null) {
                throw new NotFoundHttpException('Không tìm thấy yêu cầu nộp đối soát.');
            }

            // 1. Idempotency: nếu đã VERIFIED trước đó thì trả về thành công no-op (AC-17)
            if ($remittance->status === RemittanceStatus::VERIFIED) {
                return [
                    'remittance' => $remittance,
                    'message' => 'Yêu cầu đối soát này đã được xác minh trước đó.',
                ];
            }

            // 2. Kiểm tra trạng thái remittance: bắt buộc phải đang SUBMITTED (State conflict dùng 409)
            if ($remittance->status !== RemittanceStatus::SUBMITTED) {
                throw new \Symfony\Component\HttpKernel\Exception\ConflictHttpException(
                    'Không thể xác minh yêu cầu nộp tiền không ở trạng thái chờ duyệt (SUBMITTED).'
                );
            }

            $verifiedBankRef = trim((string) ($data['verified_bank_reference'] ?? ''));
            if ($verifiedBankRef === '') {
                throw new UnprocessableEntityHttpException('Mã giao dịch ngân hàng trích từ sao kê là bắt buộc.');
            }

            // 3. Chống tái sử dụng verified_bank_reference (AC-19)
            $duplicateRef = CommissionRemittance::query()
                ->where('verified_bank_reference', $verifiedBankRef)
                ->where('id', '!=', $remittance->id)
                ->exists();

            if ($duplicateRef) {
                throw new UnprocessableEntityHttpException(
                    'Mã giao dịch ngân hàng này đã được xác minh cho một khoản đối soát khác. Vui lòng kiểm tra lại sao kê.'
                );
            }

            /** @var BookingCommission|null $commission */
            $commission = BookingCommission::query()
                ->where('id', $remittance->booking_commission_id)
                ->lockForUpdate()
                ->first();

            if ($commission === null) {
                throw new NotFoundHttpException('Không tìm thấy khoản hoa hồng liên kết.');
            }

            // 4. Kiểm tra trạng thái commission: bắt buộc phải đang DUE (State conflict dùng 409)
            if ($commission->status !== CommissionStatus::DUE) {
                throw new \Symfony\Component\HttpKernel\Exception\ConflictHttpException(
                    'Khoản hoa hồng liên kết không ở trạng thái Chờ thanh toán (DUE) để xác minh.'
                );
            }

            // 5. Kiểm tra số tiền: số tiền đối soát phải khớp 100% với hoa hồng
            if (bccomp($remittance->amount, $commission->commission_amount, 2) !== 0) {
                throw new UnprocessableEntityHttpException(
                    'Số tiền đối soát không khớp với số tiền hoa hồng cần nộp.'
                );
            }

            $now = now();

            // Cập nhật Remittance -> VERIFIED
            $remittance->update([
                'status' => RemittanceStatus::VERIFIED,
                'verified_bank_reference' => $verifiedBankRef,
                'review_note' => $data['review_note'] ?? null,
                'reviewed_by_admin_id' => $adminId,
                'reviewed_at' => $now,
            ]);

            // Cập nhật Commission -> SETTLED atomically (AC-17)
            $commission->update([
                'status' => CommissionStatus::SETTLED,
                'settled_at' => $now,
            ]);

            return [
                'remittance' => $remittance,
                'commission' => $commission,
                'message' => 'Xác minh giao dịch đối soát thành công. Khoản hoa hồng đã được ghi nhận thực thu (SETTLED).',
            ];
        });
    }

    /**
     * Admin từ chối giao dịch đối soát (Reject) với lý do bắt buộc.
     */
    public function rejectRemittance(int $remittanceId, int $adminId, array $data): array
    {
        return DB::transaction(function () use ($remittanceId, $adminId, $data) {
            /** @var CommissionRemittance|null $remittance */
            $remittance = CommissionRemittance::query()
                ->where('id', $remittanceId)
                ->lockForUpdate()
                ->first();

            if ($remittance === null) {
                throw new NotFoundHttpException('Không tìm thấy yêu cầu nộp đối soát.');
            }

            if ($remittance->status === RemittanceStatus::VERIFIED) {
                throw new \Symfony\Component\HttpKernel\Exception\ConflictHttpException(
                    'Không thể từ chối giao dịch đã được xác minh thành công.'
                );
            }

            if ($remittance->status !== RemittanceStatus::SUBMITTED) {
                throw new \Symfony\Component\HttpKernel\Exception\ConflictHttpException(
                    'Yêu cầu nộp đối soát không ở trạng thái chờ duyệt (SUBMITTED) để từ chối.'
                );
            }

            $reason = trim((string) ($data['reason'] ?? ''));
            if ($reason === '') {
                throw new UnprocessableEntityHttpException('Lý do từ chối là bắt buộc.');
            }

            /** @var BookingCommission|null $commission */
            $commission = BookingCommission::query()
                ->where('id', $remittance->booking_commission_id)
                ->lockForUpdate()
                ->first();

            if ($commission === null) {
                throw new NotFoundHttpException('Không tìm thấy khoản hoa hồng liên kết.');
            }

            $now = now();

            // Cập nhật Remittance -> REJECTED, giữ lịch sử (AC-18)
            $remittance->update([
                'status' => RemittanceStatus::REJECTED,
                'review_note' => $reason,
                'reviewed_by_admin_id' => $adminId,
                'reviewed_at' => $now,
            ]);

            // Commission giữ nguyên trạng thái DUE (AC-18)
            if ($commission->status !== CommissionStatus::DUE && $commission->status !== CommissionStatus::SETTLED) {
                $commission->update([
                    'status' => CommissionStatus::DUE,
                ]);
            }

            return [
                'remittance' => $remittance,
                'commission' => $commission,
                'message' => 'Đã từ chối giao dịch đối soát. Khoản hoa hồng vẫn ở trạng thái Chờ thanh toán (DUE).',
            ];
        });
    }
}
