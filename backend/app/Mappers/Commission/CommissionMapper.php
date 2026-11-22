<?php

namespace App\Mappers\Commission;

use App\Enums\CommissionStatus;
use App\Enums\RemittanceStatus;
use App\Models\BookingCommission;
use App\Models\CommissionRemittance;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CommissionMapper
{
    /**
     * Tính toán trạng thái hiển thị (display_status) cho một khoản hoa hồng.
     */
    public function computeDisplayStatus(BookingCommission $commission): string
    {
        if ($commission->status === CommissionStatus::DUE) {
            $hasPending = $commission->remittances->contains(fn ($r) => $r->status === RemittanceStatus::SUBMITTED);
            if ($hasPending) {
                return 'UNDER_REVIEW';
            }

            if ($commission->due_at !== null && now()->greaterThan($commission->due_at)) {
                return 'OVERDUE';
            }

            return 'DUE';
        }

        return $commission->status->value;
    }

    /**
     * Map một phần tử trong danh sách hoa hồng của Host.
     */
    public function toHostCommissionListItem(BookingCommission $item): array
    {
        $displayStatus = $this->computeDisplayStatus($item);

        $bankName = config('commission.bank_info.bank_name');
        $accountNumber = config('commission.bank_info.account_number');
        $accountHolder = config('commission.bank_info.account_holder');
        $hasValidConfig = ! empty($bankName) && ! empty($accountNumber);

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
            'payment_instructions' => $hasValidConfig ? [
                'bank_name' => (string) $bankName,
                'account_number' => (string) $accountNumber,
                'account_holder' => (string) ($accountHolder ?? 'CONG TY CP CONG NGHE STAYHUB'),
                'amount' => $item->commission_amount,
                'reference_code' => $item->reference_code,
            ] : null,
        ];
    }

    /**
     * Map phân trang danh sách hoa hồng của Host.
     */
    public function toHostCommissionPaginatedResponse(LengthAwarePaginator $paginator): array
    {
        $items = collect($paginator->items())->map(
            fn (BookingCommission $item) => $this->toHostCommissionListItem($item)
        )->all();

        return [
            'success' => true,
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    /**
     * Map chi tiết hoa hồng cho Host kèm hướng dẫn thanh toán và lịch sử nộp tiền.
     */
    public function toHostCommissionDetailResponse(BookingCommission $commission): array
    {
        $displayStatus = $this->computeDisplayStatus($commission);

        $bankName = config('commission.bank_info.bank_name');
        $accountNumber = config('commission.bank_info.account_number');
        $accountHolder = config('commission.bank_info.account_holder');
        $hasValidConfig = ! empty($bankName) && ! empty($accountNumber);

        return [
            'success' => true,
            'data' => [
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
                'payment_instructions' => $hasValidConfig ? [
                    'bank_name' => (string) $bankName,
                    'account_number' => (string) $accountNumber,
                    'account_holder' => (string) ($accountHolder ?? 'CONG TY CP CONG NGHE STAYHUB'),
                    'amount' => $commission->commission_amount,
                    'reference_code' => $commission->reference_code,
                ] : null,
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
            ],
        ];
    }

    /**
     * Map một phần tử trong danh sách hoa hồng của Admin.
     */
    public function toAdminCommissionListItem(BookingCommission $item): array
    {
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
    }

    /**
     * Map phân trang danh sách hoa hồng của Admin.
     */
    public function toAdminCommissionPaginatedResponse(LengthAwarePaginator $paginator): array
    {
        $items = collect($paginator->items())->map(
            fn (BookingCommission $item) => $this->toAdminCommissionListItem($item)
        )->all();

        return [
            'success' => true,
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    /**
     * Map một phần tử trong hàng chờ khai báo đối soát của Admin.
     */
    public function toAdminRemittanceItem(CommissionRemittance $r): array
    {
        return [
            'id' => $r->id,
            'booking_commission_id' => $r->booking_commission_id,
            'host_id' => $r->host_id,
            'amount' => $r->amount,
            'bank_transaction_reference' => $r->bank_transaction_reference,
            'idempotency_key' => $r->idempotency_key,
            'status' => $r->status->value,
            'verified_bank_reference' => $r->verified_bank_reference,
            'reviewed_by_admin_id' => $r->reviewed_by_admin_id,
            'review_note' => $r->review_note,
            'submitted_at' => $r->submitted_at?->toIso8601String(),
            'reviewed_at' => $r->reviewed_at?->toIso8601String(),
            'created_at' => $r->created_at?->toIso8601String(),
            'host' => $r->host ? [
                'id' => $r->host->id,
                'name' => $r->host->name,
                'email' => $r->host->email,
                'phone' => $r->host->phone,
            ] : null,
            'reviewed_by_admin' => $r->reviewedByAdmin ? [
                'id' => $r->reviewedByAdmin->id,
                'name' => $r->reviewedByAdmin->name,
            ] : null,
            'commission' => $r->commission ? [
                'id' => $r->commission->id,
                'reference_code' => $r->commission->reference_code,
                'commission_amount' => $r->commission->commission_amount,
                'invoice' => $r->commission->invoice ? [
                    'id' => $r->commission->invoice->id,
                    'invoice_code' => $r->commission->invoice->invoice_code,
                    'total_amount' => $r->commission->invoice->total_amount,
                    'paid_at' => $r->commission->invoice->paid_at?->toIso8601String(),
                ] : null,
                'property_name' => $r->commission->booking?->room?->property?->name,
            ] : null,
        ];
    }

    /**
     * Map phân trang hàng chờ khai báo đối soát của Admin.
     */
    public function toAdminRemittancePaginatedResponse(LengthAwarePaginator $paginator): array
    {
        $items = collect($paginator->items())->map(
            fn (CommissionRemittance $item) => $this->toAdminRemittanceItem($item)
        )->all();

        return [
            'success' => true,
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    /**
     * Map kết quả submit remittance cho Host.
     */
    public function toRemittanceSubmissionResponse(CommissionRemittance $remittance): array
    {
        return [
            'success' => true,
            'message' => 'Đã gửi thông tin chuyển khoản đối soát. Vui lòng chờ Ban quản trị xác nhận.',
            'data' => [
                'id' => $remittance->id,
                'booking_commission_id' => $remittance->booking_commission_id,
                'amount' => $remittance->amount,
                'bank_transaction_reference' => $remittance->bank_transaction_reference,
                'status' => $remittance->status->value,
                'submitted_at' => $remittance->submitted_at?->toIso8601String(),
            ],
        ];
    }

    /**
     * Map kết quả Admin duyệt remittance (verify).
     */
    public function toRemittanceVerificationResponse(array $result): array
    {
        /** @var CommissionRemittance $remittance */
        $remittance = $result['remittance'];

        return [
            'success' => true,
            'message' => $result['message'],
            'data' => [
                'remittance_id' => $remittance->id,
                'verified_bank_reference' => $remittance->verified_bank_reference,
                'status' => $remittance->status->value,
                'reviewed_at' => $remittance->reviewed_at?->toIso8601String(),
            ],
        ];
    }

    /**
     * Map kết quả Admin từ chối remittance (reject).
     */
    public function toRemittanceRejectionResponse(array $result): array
    {
        /** @var CommissionRemittance $remittance */
        $remittance = $result['remittance'];

        return [
            'success' => true,
            'message' => $result['message'],
            'data' => [
                'remittance_id' => $remittance->id,
                'status' => $remittance->status->value,
                'review_note' => $remittance->review_note,
                'reviewed_at' => $remittance->reviewed_at?->toIso8601String(),
            ],
        ];
    }

    /**
     * Map báo cáo tài chính Host (RPT-01).
     */
    public function toFinancialSummaryResponse(array $summary): array
    {
        return [
            'success' => true,
            'data' => $summary,
        ];
    }

    /**
     * Map báo cáo đối soát Admin (RPT-02).
     */
    public function toCommissionSummaryResponse(array $summary): array
    {
        return [
            'success' => true,
            'data' => $summary,
        ];
    }
}
