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

        $paginator = $query->orderByDesc('id')->paginate($perPage);

        // Format items with computed display status and formatted properties
        $paginator->getCollection()->transform(function (BookingCommission $item) {
            return $this->formatCommissionListItem($item);
        });

        return $paginator;
    }

    /**
     * Lấy chi tiết khoản hoa hồng kèm thông tin tài khoản chuyển khoản và lịch sử nộp tiền.
     */
    public function findForHost(int $commissionId, int $hostId): array
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

        $displayStatus = $this->computeDisplayStatus($commission);

        return [
            'commission' => [
                'id' => $commission->id,
                'reference_code' => $commission->reference_code,
                'invoice_id' => $commission->invoice_id,
                'invoice_code' => $commission->invoice?->invoice_code,
                'booking_id' => $commission->booking_id,
                'booking_code' => $commission->booking?->booking_code,
                'property_name' => $commission->booking?->room?->property?->name,
                'room_name' => $commission->booking?->room?->name,
                'customer_name' => $commission->booking?->customer?->name,
                'commission_base' => $commission->commission_base,
                'commission_rate_bps' => $commission->commission_rate_bps,
                'commission_amount' => $commission->commission_amount,
                'currency' => $commission->currency,
                'status' => $commission->status->value,
                'display_status' => $displayStatus,
                'due_at' => $commission->due_at?->toIso8601String(),
                'settled_at' => $commission->settled_at?->toIso8601String(),
                'created_at' => $commission->created_at?->toIso8601String(),
            ],
            'payment_instructions' => [
                'bank_name' => config('commission.bank_info.bank_name', 'Vietcombank'),
                'account_number' => config('commission.bank_info.account_number', '1029384756'),
                'account_holder' => config('commission.bank_info.account_holder', 'STAYHUB'),
                'amount' => $commission->commission_amount,
                'reference_code' => $commission->reference_code,
            ],
            'remittances' => $commission->remittances->map(fn ($r) => [
                'id' => $r->id,
                'amount' => $r->amount,
                'bank_transaction_reference' => $r->bank_transaction_reference,
                'idempotency_key' => $r->idempotency_key,
                'status' => $r->status->value,
                'submitted_at' => $r->submitted_at?->toIso8601String(),
                'reviewed_at' => $r->reviewed_at?->toIso8601String(),
                'review_note' => $r->review_note,
            ])->values()->all(),
        ];
    }

    /**
     * Host nộp khai báo chuyển khoản đối soát (Remittance).
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

            // 1. Kiểm tra trạng thái: Phải đang DUE
            if ($commission->status !== CommissionStatus::DUE) {
                throw new UnprocessableEntityHttpException(
                    'Chỉ có thể nộp đối soát cho khoản hoa hồng đang ở trạng thái Chờ thanh toán (DUE).'
                );
            }

            // 2. Kiểm tra số tiền: phải khớp 100%
            $expectedAmount = number_format((float) $commission->commission_amount, 2, '.', '');
            $submittedAmount = number_format((float) ($data['amount'] ?? 0), 2, '.', '');

            if (bccomp($expectedAmount, $submittedAmount, 2) !== 0) {
                throw new UnprocessableEntityHttpException(
                    'Số tiền nộp phải khớp 100% với số tiền hoa hồng cần nộp (' .
                    number_format((float) $commission->commission_amount, 0, ',', '.') . ' VND).'
                );
            }

            // 3. Kiểm tra Idempotency
            $idempotencyKey = (string) ($data['idempotency_key'] ?? '');
            if ($idempotencyKey !== '') {
                $existing = CommissionRemittance::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existing !== null) {
                    if ($existing->booking_commission_id === $commission->id && $existing->host_id === $hostId) {
                        return $existing;
                    }
                    throw new UnprocessableEntityHttpException('Khóa idempotency đã được sử dụng cho một giao dịch khác.');
                }
            }

            // 4. Kiểm tra xem đã có submission pending chưa
            $hasPending = CommissionRemittance::query()
                ->where('booking_commission_id', $commission->id)
                ->where('status', RemittanceStatus::SUBMITTED)
                ->exists();

            if ($hasPending) {
                throw new UnprocessableEntityHttpException(
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

    /**
     * Tính toán trạng thái hiển thị dẫn xuất (Derived Display Status).
     */
    public function computeDisplayStatus(BookingCommission $commission): string
    {
        if ($commission->status === CommissionStatus::DUE) {
            $hasPending = $commission->remittances->contains(fn ($r) => $r->status === RemittanceStatus::SUBMITTED);
            if ($hasPending) {
                return 'UNDER_REVIEW'; // Đang đối soát
            }

            if ($commission->due_at !== null && now()->greaterThan($commission->due_at)) {
                return 'OVERDUE'; // Quá hạn
            }

            return 'DUE'; // Chờ thanh toán
        }

        return $commission->status->value;
    }

    /**
     * Chuẩn hóa phần tử danh sách trả về cho client.
     */
    protected function formatCommissionListItem(BookingCommission $item): array
    {
        $displayStatus = $this->computeDisplayStatus($item);

        return [
            'id' => $item->id,
            'reference_code' => $item->reference_code,
            'invoice_id' => $item->invoice_id,
            'invoice_code' => $item->invoice?->invoice_code,
            'booking_id' => $item->booking_id,
            'booking_code' => $item->booking?->booking_code,
            'property_name' => $item->booking?->room?->property?->name,
            'room_name' => $item->booking?->room?->name,
            'commission_base' => $item->commission_base,
            'commission_rate_bps' => $item->commission_rate_bps,
            'commission_amount' => $item->commission_amount,
            'currency' => $item->currency,
            'status' => $item->status->value,
            'display_status' => $displayStatus,
            'due_at' => $item->due_at?->toIso8601String(),
            'settled_at' => $item->settled_at?->toIso8601String(),
            'created_at' => $item->created_at?->toIso8601String(),
        ];
    }
}
