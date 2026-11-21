<?php

namespace App\Repositories\Dashboard;

use App\Enums\BookingStatus;
use Illuminate\Support\Facades\DB;

class AdminDashboardRepository
{
    /**
     * One statement read view for KPI and charts; each aggregate derived table has one row.
     * @param list<array{month: string, start: int, end: int}> $months UTC epoch boundaries
     */
    public function aggregate(array $months, string $asOfEpoch): object
    {
        $bookings = DB::table('bookings')->selectRaw('COUNT(*) AS bookings_count');
        foreach (BookingStatus::cases() as $status) {
            $bookings->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) AS booking_'.strtolower($status->value), [$status->value]);
        }
        $invoices = DB::table('invoices')
            ->selectRaw("CAST(COALESCE(SUM(CASE WHEN payment_status = 'PAID' AND paid_at IS NOT NULL THEN total_amount ELSE 0.00 END), 0.00) AS CHAR) AS revenue_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN payment_status = 'PAID' AND paid_at IS NULL THEN 1 ELSE 0 END), 0) AS excluded_paid_invoices_count");
        foreach ($months as $index => $month) {
            // FROM_UNIXTIME converts absolute boundaries into the connection's timestamp context.
            // No named timezone tables, timezone mutation or float money arithmetic is needed.
            $invoices->selectRaw("CAST(COALESCE(SUM(CASE WHEN payment_status = 'PAID' AND paid_at >= FROM_UNIXTIME(?) AND paid_at < FROM_UNIXTIME(?) AND paid_at <= FROM_UNIXTIME(?) THEN total_amount ELSE 0.00 END), 0.00) AS CHAR) AS revenue_month_".$index,
                [$month['start'], $month['end'], $asOfEpoch]);
        }

        return DB::query()->fromSub($bookings, 'booking_facts')->crossJoinSub($invoices, 'invoice_facts')
            ->select('booking_facts.*', 'invoice_facts.*')
            ->selectSub(DB::table('users')->selectRaw('COUNT(*)'), 'users_count')
            ->selectSub(DB::table('users')->where('role', 'HOST')->selectRaw('COUNT(*)'), 'hosts_count')
            ->selectSub(DB::table('properties')->selectRaw('COUNT(*)'), 'properties_count')
            ->selectSub(DB::table('rooms')->selectRaw('COUNT(*)'), 'rooms_count')
            ->first();
    }
}
