<?php

namespace App\DTOs\Statistics\Responses;

use App\DTOs\Statistics\AdminStatisticsPeriod;
use App\Enums\BookingStatus;
use JsonSerializable;
use UnexpectedValueException;

final readonly class AdminStatisticsResponse implements JsonSerializable
{
    private array $data;

    public function __construct(AdminStatisticsPeriod $period, object $facts)
    {
        $count = static function (mixed $value): int {
            if ((!is_int($value) && !is_string($value)) || preg_match('/\A(?:0|[1-9][0-9]*)\z/', (string) $value) !== 1
                || strlen((string) $value) > 16 || (strlen((string) $value) === 16 && strcmp((string) $value, '9007199254740991') > 0)) {
                throw new UnexpectedValueException('Số lượng thống kê không hợp lệ.');
            }
            return (int) $value;
        };
        $money = static function (mixed $value): string {
            if (!is_string($value) || preg_match('/\A(?:0|[1-9][0-9]*)\.[0-9]{2}\z/', $value) !== 1) {
                throw new UnexpectedValueException('Số tiền thống kê không hợp lệ.');
            }
            return $value;
        };
        $statuses = static function (string $prefix) use ($facts, $count): array {
            return array_map(static fn (BookingStatus $s): array => ['status' => $s->value,
                'count' => $count($facts->{$prefix.'_'.strtolower($s->value)} ?? null)], BookingStatus::cases());
        };
        $assertStatuses = static function (array $statuses, int $total): void {
            // BCMath also avoids overflowing PHP integers for malformed aggregate input.
            $sum = '0';
            foreach ($statuses as $status) { $sum = bcadd($sum, (string) $status['count'], 0); }
            if (bccomp($sum, (string) $total, 0) !== 0) { throw new UnexpectedValueException('Tổng trạng thái không khớp.'); }
        };
        $summary = ['bookings_count' => $count($facts->summary_count ?? null), 'revenue_total' => $money($facts->summary_revenue ?? null)];
        $summaryStatuses = $statuses('summary');
        $assertStatuses($summaryStatuses, $summary['bookings_count']);
        $monthly = []; $bookingSum = '0'; $revenueSum = '0.00';
        $statusSums = array_fill(0, count($summaryStatuses), '0');
        foreach ($period->months as $i => $month) {
            $prefix = 'month_'.$i;
            $row = ['month' => $month['month'], 'bookings_count' => $count($facts->{$prefix.'_count'} ?? null),
                'revenue_total' => $money($facts->{$prefix.'_revenue'} ?? null), 'booking_status_counts' => $statuses($prefix)];
            $assertStatuses($row['booking_status_counts'], $row['bookings_count']);
            $bookingSum = bcadd($bookingSum, (string) $row['bookings_count'], 0);
            $revenueSum = bcadd($revenueSum, $row['revenue_total'], 2);
            foreach ($row['booking_status_counts'] as $j => $status) { $statusSums[$j] = bcadd($statusSums[$j], (string) $status['count'], 0); }
            $monthly[] = $row;
        }
        if (bccomp($bookingSum, (string) $summary['bookings_count'], 0) !== 0 || bccomp($revenueSum, $summary['revenue_total'], 2) !== 0) {
            throw new UnexpectedValueException('Tổng kỳ thống kê không khớp.');
        }
        foreach ($summaryStatuses as $j => $status) {
            if (bccomp($statusSums[$j], (string) $status['count'], 0) !== 0) { throw new UnexpectedValueException('Phân bố trạng thái không khớp.'); }
        }
        $this->data = ['period' => $period->projection(), 'report_timezone' => AdminStatisticsPeriod::TIMEZONE,
            'generated_at' => AdminStatisticsPeriod::iso($period->generatedAt), 'summary' => $summary,
            'booking_status_counts' => $summaryStatuses, 'monthly' => $monthly,
            'warnings' => ['paid_invoices_missing_paid_at_global_count' => $count($facts->missing_paid_at ?? null)]];
    }

    public function jsonSerialize(): array { return $this->data; }
}
