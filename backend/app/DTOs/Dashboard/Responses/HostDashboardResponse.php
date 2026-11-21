<?php

namespace App\DTOs\Dashboard\Responses;

use App\DTOs\Dashboard\HostDashboardPeriod;
use JsonSerializable;
use UnexpectedValueException;

/** Complete data projection; HTTP status/envelope still belong to the future HTTP boundary. */
final readonly class HostDashboardResponse implements JsonSerializable
{
    private array $data;

    public function __construct(HostDashboardPeriod $period, object $facts)
    {
        $snapshot = (new HostDashboardSnapshotResponse($period->generatedAt, $facts))->jsonSerialize();
        $money = static function (mixed $value): string {
            if (!is_string($value) || preg_match('/\A(?:0|[1-9][0-9]*)\.[0-9]{2}\z/', $value) !== 1) {
                throw new UnexpectedValueException('Doanh thu Host không hợp lệ.');
            }
            return $value;
        };
        $warning = $facts->missing_paid_at ?? null;
        if ((!is_int($warning) && !is_string($warning)) || preg_match('/\A(?:0|[1-9][0-9]*)\z/', (string) $warning) !== 1
            || strlen((string) $warning) > 16 || (strlen((string) $warning) === 16 && strcmp((string) $warning, '9007199254740991') > 0)) {
            throw new UnexpectedValueException('Số lượng cảnh báo Host không hợp lệ.');
        }
        $total = $money($facts->summary_revenue ?? null);
        $monthly = []; $sum = '0.00';
        foreach ($period->months as $i => $month) {
            $value = $money($facts->{'month_'.$i.'_revenue'} ?? null);
            $monthly[] = ['month' => $month['month'], 'revenue_total' => $value];
            $sum = bcadd($sum, $value, 2);
        }
        if (bccomp($sum, $total, 2) !== 0) { throw new UnexpectedValueException('Tổng doanh thu Host không khớp các tháng.'); }
        $this->data = ['scope' => $snapshot['scope'], 'period' => $period->projection(),
            'report_timezone' => $snapshot['report_timezone'], 'generated_at' => $snapshot['generated_at'],
            'snapshot' => $snapshot['snapshot'], 'summary' => ['revenue_total' => $total],
            'revenue_by_month' => $monthly, 'recent_bookings' => $snapshot['recent_bookings'],
            'warnings' => ['paid_invoices_missing_paid_at_scope_count' => (int) $warning]];
    }

    public function jsonSerialize(): array { return $this->data; }
}
