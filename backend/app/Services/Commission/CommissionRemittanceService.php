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

class CommissionRemittanceService
{
    /**
     * Danh sách hoa hồng của Host có phân trang, lọc và tính trạng thái dẫn xuất.
     */
    public function listForHost(int $hostId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = BookingCommission::query()
            ->with([
                'invoice:id,invoice_code,subtotal,discount_amount,extra_fee,total_amount,payment_status,paid_at',
                'booking:id,booking_code,check_in_date,check_out_date,room_id',
                'booking.room:id,name,property_id',
                'booking.room.property:id,name',
                'remittances' => function ($q) {
                    $q->orderByDesc('id');
                },
            ])
            ->where('host_id', $hostId);

        $statusFilter = strtoupper((string) ($filters['status'] ?? 'ALL'));

        if ($statusFilter !== 'ALL' && $statusFilter !== '') {
            if ($statusFilter === 'OVERDUE') {
                $query->where('status', CommissionStatus::DUE)
                    ->whereNotNull('due_at')
                    ->where('due_at', '<', now())
                    ->whereDoesntHave('remittances', function ($q) {
                        $q->where('status', RemittanceStatus::SUBMITTED);
                    });
            } elseif ($statusFilter === 'UNDER_REVIEW') {
                $query->where('status', CommissionStatus::DUE)
                    ->whereHas('remittances', function ($q) {
                        $q->where('status', RemittanceStatus::SUBMITTED);
                    });
            } elseif (in_array($statusFilter, ['AWAITING_CUSTOMER_PAYMENT', 'DUE', 'SETTLED', 'DISPUTED', 'VOID'], true)) {
                $query->where('status', $statusFilter);
            }
        }

        return $query->orderByDesc('id')->paginate($perPage);
    }

    /**
     * Lấy chi tiết khoản hoa hồng kèm thông tin tài khoản chuyển khoản và lịch sử nộp tiền.
     */
    public function findForHost(int $commissionId, int $hostId): BookingCommission
    {
        /** @var BookingCommission|null $commission */
        $commission = BookingCommission::query()
            ->with([
                'invoice',
                'booking.room.property',
                'booking.customer:id,name,email,phone',
                'remittances' => function ($q) {
                    $q->orderByDesc('id');
                },
            ])
            ->where('host_id', $hostId)
            ->where('id', $commissionId)
            ->first();

        if ($commission === null) {
            throw new NotFoundHttpException('Không tìm thấy khoản hoa hồng.');
        }

        return $commission;
    }

    /**
     * Host nộp khai báo chuyển khoản đối soát (Remittance).
     * Tuân thủ quy tắc Idempotency và State Machine (REM-01, AC-16).
     */
    public function submitRemittance(int $commissionId, int $hostId, array $data): CommissionRemittance
    {
        return DB::transaction(function () use ($commissionId, $hostId, $data) {
            /** @var BookingCommission|null $commission */
            $commission = BookingCommission::query()
                ->where('id', $commissionId)
                ->where('host_id', $hostId)
                ->lockForUpdate()
                ->first();

            if ($commission === null) {
                throw new NotFoundHttpException('Không tìm thấy khoản hoa hồng cần nộp.');
            }

            $submittedAmount = number_format((float) ($data['amount'] ?? 0), 2, '.', '');
            $idempotencyKey = trim((string) ($data['idempotency_key'] ?? ''));

            // 1. Xử lý Idempotency TRƯỚC gate kiểm tra trạng thái DUE (REM-01, AC-16)
            if ($idempotencyKey !== '') {
                $existing = CommissionRemittance::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existing !== null) {
                    // Kiểm tra đúng owner và đúng commission
                    if ($existing->booking_commission_id === $commission->id && $existing->host_id === $hostId) {
                        // Kiểm tra tính nhất quán của payload: số tiền phải khớp với lần submit trước
                        if (bccomp($existing->amount, $submittedAmount, 2) === 0) {
                            return $existing;
                        }

                        throw new UnprocessableEntityHttpException(
                            'Khóa idempotency đã được sử dụng với số tiền khác.'
                        );
                    }

                    throw new UnprocessableEntityHttpException(
                        'Khóa idempotency đã được sử dụng cho một giao dịch khác.'
                    );
                }
            }

            // 2. Kiểm tra trạng thái: Phải đang DUE (State conflict dùng 409 theo plan §5.2)
            if ($commission->status !== CommissionStatus::DUE) {
                throw new \Symfony\Component\HttpKernel\Exception\ConflictHttpException(
                    'Chỉ có thể nộp đối soát cho khoản hoa hồng đang ở trạng thái Chờ thanh toán (DUE).'
                );
            }

            // 3. Kiểm tra số tiền: phải khớp 100% với số tiền hoa hồng cần nộp
            $expectedAmount = number_format((float) $commission->commission_amount, 2, '.', '');
            if (bccomp($expectedAmount, $submittedAmount, 2) !== 0) {
                throw new UnprocessableEntityHttpException(
                    'Số tiền nộp phải khớp 100% với số tiền hoa hồng cần nộp (' .
                    number_format((float) $commission->commission_amount, 0, ',', '.') . ' VND).'
                );
            }

            // 4. Kiểm tra xem đã có submission pending chưa (State conflict dùng 409)
            $hasPending = CommissionRemittance::query()
                ->where('booking_commission_id', $commission->id)
                ->where('status', RemittanceStatus::SUBMITTED)
                ->exists();

            if ($hasPending) {
                throw new \Symfony\Component\HttpKernel\Exception\ConflictHttpException(
                    'Khoản hoa hồng này đã có một yêu cầu đối soát đang chờ Admin kiểm tra.'
                );
            }

            // 5. Tạo mới submission
            return CommissionRemittance::query()->create([
                'booking_commission_id' => $commission->id,
                'host_id' => $hostId,
                'amount' => $submittedAmount,
                'bank_transaction_reference' => $data['bank_transaction_reference'] ?? null,
                'idempotency_key' => $idempotencyKey,
                'status' => RemittanceStatus::SUBMITTED,
                'submitted_at' => now(),
            ]);
        });
    }
}
