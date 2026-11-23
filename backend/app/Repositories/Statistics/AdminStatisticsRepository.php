<?php

namespace App\Repositories\Statistics;

use App\DTOs\Statistics\AdminStatisticsPeriod;
use App\Enums\BookingStatus;
use Illuminate\Support\Facades\DB;

class AdminStatisticsRepository
{
    public function aggregate(AdminStatisticsPeriod $period): object
    {
        $bookings = DB::table('bookings');
        $invoices = DB::table('invoices')->selectRaw("COALESCE(SUM(CASE WHEN payment_status = 'PAID' AND paid_at IS NULL THEN 1 ELSE 0 END), 0) AS missing_paid_at");
        $commissions = DB::table('booking_commissions')->where('status', 'SETTLED')
            ->selectRaw('COUNT(CASE WHEN settled_at IS NULL THEN 1 END) AS missing_settled_at');
        $windows = ['summary' => ['start' => $period->months[0]['start'], 'end' => $period->months[array_key_last($period->months)]['end']]];
        foreach ($period->months as $i => $month) {
            $windows['month_'.$i] = $month;
        }
        // Integer epoch seconds are exact for the schema's TIMESTAMP(0) sources.
        // Casting parameters to DECIMAL avoids implicit float comparison and accepts extreme-year boundaries.
        $cutoff = $period->generatedAt->format('U');
        // The column stores UTC DATETIME values. Elapsed seconds avoid the session timezone conversion of UNIX_TIMESTAMP.
        $settledEpoch = "TIMESTAMPDIFF(SECOND, '1970-01-01 00:00:00', settled_at)";
        $commissionTime = "$settledEpoch >= CAST(? AS DECIMAL(20,0)) AND $settledEpoch < CAST(? AS DECIMAL(20,0)) AND $settledEpoch <= CAST(? AS DECIMAL(20,0))";
        foreach ($windows as $alias => $window) {
            $bindings = [(string) $window['start'], (string) $window['end'], $cutoff];
            $bookingTime = 'UNIX_TIMESTAMP(created_at) >= CAST(? AS DECIMAL(20,0)) AND UNIX_TIMESTAMP(created_at) < CAST(? AS DECIMAL(20,0)) AND UNIX_TIMESTAMP(created_at) <= CAST(? AS DECIMAL(20,0))';
            $invoiceTime = "payment_status = 'PAID' AND paid_at IS NOT NULL AND UNIX_TIMESTAMP(paid_at) >= CAST(? AS DECIMAL(20,0)) AND UNIX_TIMESTAMP(paid_at) < CAST(? AS DECIMAL(20,0)) AND UNIX_TIMESTAMP(paid_at) <= CAST(? AS DECIMAL(20,0))";
            $bookings->selectRaw('COALESCE(SUM(CASE WHEN '.$bookingTime.' THEN 1 ELSE 0 END), 0) AS '.$alias.'_count', $bindings);
            foreach (BookingStatus::cases() as $status) {
                $bookings->selectRaw('COALESCE(SUM(CASE WHEN '.$bookingTime.' AND status = ? THEN 1 ELSE 0 END), 0) AS '.$alias.'_'.strtolower($status->value), [...$bindings, $status->value]);
            }
            $invoices->selectRaw('CAST(COALESCE(SUM(CASE WHEN '.$invoiceTime.' THEN total_amount ELSE 0.00 END), 0.00) AS CHAR) AS '.$alias.'_revenue', $bindings);
            $commissions->selectRaw('CAST(COALESCE(SUM(CASE WHEN '.$commissionTime.' THEN commission_amount ELSE 0.00 END), 0.00) AS CHAR) AS '.$alias.'_commission_revenue', $bindings);
        }
        // Each derived table is one aggregate row. One SELECT gives all facts the same InnoDB read view.
        return DB::query()->fromSub($bookings, 'booking_facts')->crossJoinSub($invoices, 'invoice_facts')
            ->crossJoinSub($commissions, 'commission_facts')->select('booking_facts.*', 'invoice_facts.*', 'commission_facts.*')->first();
    }
}
