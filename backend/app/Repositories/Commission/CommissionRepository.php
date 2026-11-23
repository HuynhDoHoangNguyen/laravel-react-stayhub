<?php

namespace App\Repositories\Commission;

use App\Enums\CommissionStatus;
use App\Enums\RemittanceStatus;
use App\Models\BookingCommission;
use App\Models\CommissionRemittance;
use App\Models\Invoice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class CommissionRepository
{
    /**
     * Tìm khoản hoa hồng theo invoice_id.
     */
    public function findByInvoiceId(int $invoiceId): ?BookingCommission
    {
        return BookingCommission::query()->where('invoice_id', $invoiceId)->first();
    }

    /**
     * Tìm và khóa bản ghi hoa hồng theo invoice_id (FOR UPDATE).
     */
    public function findByInvoiceIdForUpdate(int $invoiceId): ?BookingCommission
    {
        return BookingCommission::query()
            ->where('invoice_id', $invoiceId)
            ->lockForUpdate()
            ->first();
    }

    /**
     * Kiểm tra mã reference_code đã tồn tại chưa.
     */
    public function referenceCodeExists(string $referenceCode): bool
    {
        return BookingCommission::query()->where('reference_code', $referenceCode)->exists();
    }

    /**
     * Tạo mới bản ghi BookingCommission.
     */
    public function createCharge(array $attributes): BookingCommission
    {
        return BookingCommission::query()->create($attributes);
    }

    /**
     * Cập nhật bản ghi BookingCommission.
     */
    public function updateCharge(BookingCommission $commission, array $attributes): BookingCommission
    {
        $commission->update($attributes);

        return $commission;
    }

    /**
     * Phân trang danh sách hoa hồng của Host.
     */
    public function paginateForHost(int $hostId, array $filters, int $perPage): LengthAwarePaginator
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

        $this->applyCommissionStatusFilter($query, (string) ($filters['status'] ?? 'ALL'));

        return $query->orderByDesc('id')->paginate($perPage);
    }

    /**
     * Tìm chi tiết khoản hoa hồng thuộc Host kèm relations.
     */
    public function findForHostWithRelations(int $commissionId, int $hostId): ?BookingCommission
    {
        return BookingCommission::query()
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
    }

    /**
     * Tìm và khóa khoản hoa hồng thuộc Host (FOR UPDATE).
     */
    public function findForHostForUpdate(int $commissionId, int $hostId): ?BookingCommission
    {
        return BookingCommission::query()
            ->where('id', $commissionId)
            ->where('host_id', $hostId)
            ->lockForUpdate()
            ->first();
    }

    /**
     * Tìm và khóa khoản hoa hồng theo ID (FOR UPDATE).
     */
    public function findCommissionForUpdate(int $commissionId): ?BookingCommission
    {
        return BookingCommission::query()
            ->where('id', $commissionId)
            ->lockForUpdate()
            ->first();
    }

    /**
     * Tìm bản ghi đối soát theo idempotency_key.
     */
    public function findRemittanceByIdempotencyKey(string $idempotencyKey): ?CommissionRemittance
    {
        return CommissionRemittance::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    /**
     * Kiểm tra khoản hoa hồng đã có yêu cầu đối soát đang chờ duyệt (SUBMITTED) chưa.
     */
    public function hasPendingRemittance(int $commissionId): bool
    {
        return CommissionRemittance::query()
            ->where('booking_commission_id', $commissionId)
            ->where('status', RemittanceStatus::SUBMITTED)
            ->exists();
    }

    /**
     * Tạo mới bản ghi khai báo đối soát (CommissionRemittance).
     */
    public function createRemittance(array $attributes): CommissionRemittance
    {
        return CommissionRemittance::query()->create($attributes);
    }

    /**
     * Cập nhật bản ghi khai báo đối soát (CommissionRemittance).
     */
    public function updateRemittance(CommissionRemittance $remittance, array $attributes): CommissionRemittance
    {
        $remittance->update($attributes);

        return $remittance;
    }

    /**
     * Phân trang danh sách toàn bộ hoa hồng hệ thống cho Admin.
     */
    public function paginateForAdmin(array $filters, int $perPage): LengthAwarePaginator
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

        $this->applyCommissionStatusFilter($query, (string) ($filters['status'] ?? 'ALL'));

        $column = match ($filters['date_field'] ?? 'created_at') {
            'settled_at' => 'settled_at',
            'due_at' => 'due_at',
            default => 'created_at',
        };
        foreach (['date_start' => '>=', 'date_end_exclusive' => '<'] as $filter => $operator) {
            $boundary = $filters[$filter] ?? null;
            if ($boundary === null) {
                continue;
            }

            // MySQL TIMESTAMP uses the session zone, while due_at and settled_at store UTC DATETIME values.
            if ($column === 'created_at' && $query->getConnection()->getDriverName() === 'mysql') {
                $query->whereRaw("UNIX_TIMESTAMP(created_at) {$operator} CAST(? AS DECIMAL(20,0))", [$boundary->getTimestamp()]);
            } else {
                $query->where($column, $operator, $boundary->toDateTimeString());
            }
        }

        return $query->orderByDesc('id')->paginate($perPage);
    }

    /**
     * Phân trang hàng chờ đối soát (Remittance Queue) cho Admin.
     */
    public function paginateRemittancesForAdmin(array $filters, int $perPage): LengthAwarePaginator
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

        if (isset($filters['date_start'])) {
            $query->where('submitted_at', '>=', $filters['date_start']->toDateTimeString());
        }

        if (isset($filters['date_end_exclusive'])) {
            $query->where('submitted_at', '<', $filters['date_end_exclusive']->toDateTimeString());
        }

        return $query->orderByDesc('submitted_at')->orderByDesc('id')->paginate($perPage);
    }

    /**
     * Lấy booking_commission_id của một remittance để khóa theo thứ tự cha -> con (BookingCommission -> CommissionRemittance).
     */
    public function findRemittanceCommissionId(int $remittanceId): ?int
    {
        $row = CommissionRemittance::query()
            ->select(['id', 'booking_commission_id'])
            ->find($remittanceId);

        return $row !== null ? (int) $row->booking_commission_id : null;
    }

    /**
     * Tìm và khóa bản ghi remittance theo ID (FOR UPDATE).
     */
    public function findRemittanceForUpdate(int $remittanceId): ?CommissionRemittance
    {
        return CommissionRemittance::query()
            ->where('id', $remittanceId)
            ->lockForUpdate()
            ->first();
    }

    /**
     * Kiểm tra mã giao dịch ngân hàng đã xác minh (verified_bank_reference) có bị trùng lặp không.
     */
    public function verifiedBankReferenceExists(string $verifiedBankRef, int $excludeRemittanceId): bool
    {
        return CommissionRemittance::query()
            ->where('verified_bank_reference', $verifiedBankRef)
            ->where('id', '!=', $excludeRemittanceId)
            ->exists();
    }

    /**
     * Tính tổng doanh thu gộp từ các hóa đơn PAID của Host bằng số học thập phân chính xác (không ép float).
     *
     * @return array{gross_revenue: string, count: int}
     */
    public function getHostPaidInvoicesTotalAndCount(int $hostId): array
    {
        $amounts = Invoice::query()
            ->where('payment_status', 'PAID')
            ->whereHas('booking.room.property', function ($q) use ($hostId) {
                $q->where('host_id', $hostId);
            })
            ->pluck('total_amount');

        $gross = '0.00';
        foreach ($amounts as $amt) {
            $gross = bcadd($gross, bcadd((string) $amt, '0.00', 2), 2);
        }

        return [
            'gross_revenue' => $gross,
            'count' => $amounts->count(),
        ];
    }

    /**
     * Tính tổng hoa hồng phát sinh và đã đối soát (SETTLED) của Host bằng BCMath (không ép float).
     *
     * @return array{assessed: string, settled: string, settled_count: int}
     */
    public function getHostCommissionAmounts(int $hostId): array
    {
        $rows = BookingCommission::query()
            ->select(['commission_amount', 'status'])
            ->where('host_id', $hostId)
            ->get();

        $assessed = '0.00';
        $settled = '0.00';
        $settledCount = 0;

        foreach ($rows as $row) {
            $amt = bcadd((string) $row->commission_amount, '0.00', 2);
            $assessed = bcadd($assessed, $amt, 2);

            if ($row->status === CommissionStatus::SETTLED) {
                $settled = bcadd($settled, $amt, 2);
                $settledCount++;
            }
        }

        return [
            'assessed' => $assessed,
            'settled' => $settled,
            'settled_count' => $settledCount,
        ];
    }

    /**
     * Lấy danh sách các khoản hoa hồng DUE của Host kèm lịch sử đối soát.
     *
     * @return Collection<int, BookingCommission>
     */
    public function getHostDueCommissionsWithRemittances(int $hostId): Collection
    {
        return BookingCommission::query()
            ->where('host_id', $hostId)
            ->where('status', CommissionStatus::DUE)
            ->with(['remittances' => fn ($q) => $q->orderByDesc('id')])
            ->get();
    }

    /**
     * Tính tổng hoa hồng phát sinh và thực thu toàn hệ thống cho Admin bằng BCMath (không ép float).
     *
     * @return array{assessed: string, settled: string, settled_count: int}
     */
    public function getAdminCommissionAmounts(): array
    {
        $rows = BookingCommission::query()
            ->select(['commission_amount', 'status'])
            ->get();

        $assessed = '0.00';
        $settled = '0.00';
        $settledCount = 0;

        foreach ($rows as $row) {
            $amt = bcadd((string) $row->commission_amount, '0.00', 2);
            $assessed = bcadd($assessed, $amt, 2);

            if ($row->status === CommissionStatus::SETTLED) {
                $settled = bcadd($settled, $amt, 2);
                $settledCount++;
            }
        }

        return [
            'assessed' => $assessed,
            'settled' => $settled,
            'settled_count' => $settledCount,
        ];
    }

    /**
     * Lấy toàn bộ các khoản hoa hồng DUE kèm lịch sử đối soát cho Admin.
     *
     * @return Collection<int, BookingCommission>
     */
    public function getAdminDueCommissionsWithRemittances(): Collection
    {
        return BookingCommission::query()
            ->where('status', CommissionStatus::DUE)
            ->with(['remittances' => fn ($q) => $q->orderByDesc('id')])
            ->get();
    }

    /**
     * Đếm số lượng yêu cầu đối soát đang chờ Admin duyệt (SUBMITTED).
     */
    public function countPendingRemittances(): int
    {
        return CommissionRemittance::query()
            ->where('status', RemittanceStatus::SUBMITTED)
            ->count();
    }

    /**
     * Áp dụng bộ lọc trạng thái hoa hồng (bao gồm trạng thái dẫn xuất OVERDUE và UNDER_REVIEW).
     */
    private function applyCommissionStatusFilter($query, string $rawStatus): void
    {
        $statusFilter = strtoupper($rawStatus);
        if ($statusFilter === 'ALL' || $statusFilter === '') {
            return;
        }

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
}
