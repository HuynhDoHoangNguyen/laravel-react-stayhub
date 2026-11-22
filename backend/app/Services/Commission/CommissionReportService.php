<?php

namespace App\Services\Commission;

use App\Enums\RemittanceStatus;
use App\Repositories\Commission\CommissionRepository;

class CommissionReportService
{
    public function __construct(
        protected readonly CommissionRepository $repository,
    ) {}

    /**
     * Báo cáo tổng hợp tài chính và hoa hồng dành cho Host.
     * Tính toán hoàn toàn bằng BCMath trên chuỗi thập phân, không ép kiểu float (MONEY-01).
     *
     * @return array<string, mixed>
     */
    public function getHostFinancialSummary(int $hostId): array
    {
        // 1. Tổng doanh thu gộp từ các Hóa đơn PAID của Host
        $invoiceTotals = $this->repository->getHostPaidInvoicesTotalAndCount($hostId);
        $grossRevenue = $invoiceTotals['gross_revenue'];
        $paidInvoicesCount = $invoiceTotals['count'];

        // 2. Thống kê hoa hồng phát sinh và đã đối soát (SETTLED) của Host
        $commTotals = $this->repository->getHostCommissionAmounts($hostId);
        $assessedAmount = $commTotals['assessed'];
        $settledAmount = $commTotals['settled'];
        $settledCount = $commTotals['settled_count'];

        // 3. Phân nhóm các khoản đang ở trạng thái DUE
        $dueCommissions = $this->repository->getHostDueCommissionsWithRemittances($hostId);

        $underReviewAmount = '0.00';
        $overdueAmount = '0.00';
        $pureDueAmount = '0.00';

        $underReviewCount = 0;
        $overdueCount = 0;
        $pureDueCount = 0;

        $now = now();
        foreach ($dueCommissions as $comm) {
            $hasPending = $comm->remittances->contains(fn ($r) => $r->status === RemittanceStatus::SUBMITTED);
            $amountStr = bcadd((string) $comm->commission_amount, '0.00', 2);

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
     * Tính toán hoàn toàn bằng BCMath trên chuỗi thập phân, không ép kiểu float (MONEY-01).
     *
     * @return array<string, mixed>
     */
    public function getAdminCommissionSummary(): array
    {
        // 1. Tổng hoa hồng phát sinh và thực thu (SETTLED)
        $commTotals = $this->repository->getAdminCommissionAmounts();
        $totalAssessed = $commTotals['assessed'];
        $totalSettled = $commTotals['settled'];
        $settledCount = $commTotals['settled_count'];

        // 2. Thống kê theo trạng thái DUE
        $dueCommissions = $this->repository->getAdminDueCommissionsWithRemittances();

        $underReviewAmount = '0.00';
        $overdueAmount = '0.00';
        $pureDueAmount = '0.00';

        $underReviewCount = 0;
        $overdueCount = 0;
        $pureDueCount = 0;

        $now = now();
        foreach ($dueCommissions as $comm) {
            $hasPending = $comm->remittances->contains(fn ($r) => $r->status === RemittanceStatus::SUBMITTED);
            $amountStr = bcadd((string) $comm->commission_amount, '0.00', 2);

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
        $pendingRemittancesCount = $this->repository->countPendingRemittances();

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
