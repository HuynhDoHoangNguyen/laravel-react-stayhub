<?php

namespace App\Services\Commission;

use App\Enums\CommissionStatus;
use App\Enums\RemittanceStatus;
use App\Models\BookingCommission;
use App\Models\CommissionRemittance;
use App\Models\Invoice;

class CommissionReportService
{
    /**
     * Báo cáo tổng hợp tài chính và hoa hồng dành cho Host.
     *
     * @return array<string, mixed>
     */
    public function getHostFinancialSummary(int $hostId): array
    {
        // 1. Tổng doanh thu gộp từ các Hóa đơn PAID của Host
        $invoiceQuery = Invoice::query()
            ->where('payment_status', 'PAID')
            ->whereHas('booking.room.property', function ($q) use ($hostId) {
                $q->where('host_id', $hostId);
            });

        $grossRevenue = number_format((float) $invoiceQuery->sum('total_amount'), 2, '.', '');
        $paidInvoicesCount = $invoiceQuery->count();

        // 2. Thống kê hoa hồng của Host
        $commQuery = BookingCommission::query()->where('host_id', $hostId);

        $assessedAmount = number_format((float) (clone $commQuery)->sum('commission_amount'), 2, '.', '');
        $settledAmount = number_format((float) (clone $commQuery)->where('status', CommissionStatus::SETTLED)->sum('commission_amount'), 2, '.', '');
        $settledCount = (clone $commQuery)->where('status', CommissionStatus::SETTLED)->count();

        // Các khoản đang ở trạng thái DUE
        $dueCommissions = (clone $commQuery)
            ->where('status', CommissionStatus::DUE)
            ->with(['remittances' => fn ($q) => $q->orderByDesc('id')])
            ->get();

        $underReviewAmount = '0.00';
        $overdueAmount = '0.00';
        $pureDueAmount = '0.00';

        $underReviewCount = 0;
        $overdueCount = 0;
        $pureDueCount = 0;

        $now = now();
        foreach ($dueCommissions as $comm) {
            $hasPending = $comm->remittances->contains(fn ($r) => $r->status === RemittanceStatus::SUBMITTED);
            $amountStr = number_format((float) $comm->commission_amount, 2, '.', '');

            if ($hasPending) {
                $underReviewAmount = bcadd($underReviewAmount, $amountStr, 2);
                $underReviewCount++;
            } elseif ($comm->due_at !== null && $now->greaterThan($comm->due_at)) {
                $overdueAmount = bcadd($overdueAmount, $amountStr, 2);
                $overdueCount++;
            } else {
                $pureDueAmount = bcadd($pureDueAmount, $amountStr, 2);
                $pureDueCount++;
            }
        }

        // Doanh thu thực nhận ước tính: Gross - Assessed
        $netEstimate = bcsub($grossRevenue, $assessedAmount, 2);
        if (bccomp($netEstimate, '0.00', 2) < 0) {
            $netEstimate = '0.00';
        }

        return [
            'gross_revenue' => $grossRevenue,
            'paid_invoices_count' => $paidInvoicesCount,
            'commission_assessed' => $assessedAmount,
            'commission_settled' => $settledAmount,
            'settled_count' => $settledCount,
            'commission_due' => $pureDueAmount,
            'due_count' => $pureDueCount,
            'commission_under_review' => $underReviewAmount,
            'under_review_count' => $underReviewCount,
            'commission_overdue' => $overdueAmount,
            'overdue_count' => $overdueCount,
            'net_revenue_estimate' => $netEstimate,
        ];
    }

    /**
     * Báo cáo tổng hợp đối soát hoa hồng toàn hệ thống dành cho Admin.
     *
     * @return array<string, mixed>
     */
    public function getAdminCommissionSummary(): array
    {
        // 1. Tổng hoa hồng phát sinh và thực thu (SETTLED)
        $totalAssessed = number_format((float) BookingCommission::query()->sum('commission_amount'), 2, '.', '');
        $totalSettled = number_format((float) BookingCommission::query()->where('status', CommissionStatus::SETTLED)->sum('commission_amount'), 2, '.', '');
        $settledCount = BookingCommission::query()->where('status', CommissionStatus::SETTLED)->count();

        // 2. Thống kê theo trạng thái DUE
        $dueCommissions = BookingCommission::query()
            ->where('status', CommissionStatus::DUE)
            ->with(['remittances' => fn ($q) => $q->orderByDesc('id')])
            ->get();

        $underReviewAmount = '0.00';
        $overdueAmount = '0.00';
        $pureDueAmount = '0.00';

        $underReviewCount = 0;
        $overdueCount = 0;
        $pureDueCount = 0;

        $now = now();
        foreach ($dueCommissions as $comm) {
            $hasPending = $comm->remittances->contains(fn ($r) => $r->status === RemittanceStatus::SUBMITTED);
            $amountStr = number_format((float) $comm->commission_amount, 2, '.', '');

            if ($hasPending) {
                $underReviewAmount = bcadd($underReviewAmount, $amountStr, 2);
                $underReviewCount++;
            } elseif ($comm->due_at !== null && $now->greaterThan($comm->due_at)) {
                $overdueAmount = bcadd($overdueAmount, $amountStr, 2);
                $overdueCount++;
            } else {
                $pureDueAmount = bcadd($pureDueAmount, $amountStr, 2);
                $pureDueCount++;
            }
        }

        // Tổng công nợ phải thu (Outstanding): DUE + UNDER_REVIEW + OVERDUE (AC-21)
        $totalOutstanding = bcadd($pureDueAmount, bcadd($underReviewAmount, $overdueAmount, 2), 2);

        // Số lượng yêu cầu đối soát đang chờ Admin duyệt trong hàng chờ
        $pendingRemittancesCount = CommissionRemittance::query()
            ->where('status', RemittanceStatus::SUBMITTED)
            ->count();

        return [
            'total_assessed' => $totalAssessed,
            'total_settled' => $totalSettled,
            'settled_count' => $settledCount,
            'total_outstanding' => $totalOutstanding,
            'total_due' => $pureDueAmount,
            'due_count' => $pureDueCount,
            'total_under_review' => $underReviewAmount,
            'under_review_count' => $underReviewCount,
            'total_overdue' => $overdueAmount,
            'overdue_count' => $overdueCount,
            'pending_remittances_count' => $pendingRemittancesCount,
        ];
    }
}
