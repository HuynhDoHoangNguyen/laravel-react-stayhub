<?php

namespace App\Repositories\Dashboard;

use App\DTOs\Dashboard\HostDashboardPeriod;
use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class HostDashboardRepository
{
    private function properties(string $hostId, string $cutoff): Builder
    {
        return DB::table('properties as p')->where('p.host_id', $hostId)
            ->whereRaw('UNIX_TIMESTAMP(p.created_at) <= CAST(? AS DECIMAL(20,6))', [$cutoff]);
    }

    private function rooms(string $hostId, string $cutoff): Builder
    {
        return $this->properties($hostId, $cutoff)->join('rooms as r', 'r.property_id', '=', 'p.id')
            ->whereRaw('UNIX_TIMESTAMP(r.created_at) <= CAST(? AS DECIMAL(20,6))', [$cutoff]);
    }

    private function bookings(string $hostId, string $cutoff): Builder
    {
        return $this->rooms($hostId, $cutoff)->join('bookings as b', 'b.room_id', '=', 'r.id')
            ->whereRaw('UNIX_TIMESTAMP(b.created_at) <= CAST(? AS DECIMAL(20,6))', [$cutoff]);
    }

    /** All facts, including the bounded recent set, share one InnoDB statement read view. */
    public function snapshot(string $hostId, DateTimeImmutable $generatedAt): object
    {
        return $this->snapshotQuery($hostId, $generatedAt)->first();
    }

    private function snapshotQuery(string $hostId, DateTimeImmutable $generatedAt): Builder
    {
        $cutoff = $generatedAt->format('U.u');
        $recent = $this->bookings($hostId, $cutoff)
            ->orderByDesc('b.created_at')->orderByDesc('b.id')->limit(5)
            ->selectRaw("CAST(b.id AS CHAR) AS id, b.booking_code, b.status,
                CAST(UNIX_TIMESTAMP(b.created_at) AS CHAR) AS created_at_epoch,
                CAST(b.check_in_date AS CHAR) AS check_in_date, CAST(b.check_out_date AS CHAR) AS check_out_date,
                CAST(p.id AS CHAR) AS property_id, p.name AS property_name,
                CAST(r.id AS CHAR) AS room_id, r.name AS room_name");
        // JSON_ARRAYAGG does not promise order. Selection is ordered before LIMIT;
        // the response projection restores that same order without casting IDs to numbers.
        $recentJson = DB::query()->fromSub($recent, 'recent')->selectRaw("COALESCE(JSON_ARRAYAGG(JSON_OBJECT(
            'id', id, 'booking_code', booking_code, 'status', status, 'created_at_epoch', created_at_epoch,
            'check_in_date', check_in_date, 'check_out_date', check_out_date,
            'property_id', property_id, 'property_name', property_name, 'room_id', room_id, 'room_name', room_name)), JSON_ARRAY())");
        $maintenance = $this->rooms($hostId, $cutoff)->join('maintenances as m', 'm.room_id', '=', 'r.id')
            ->where('m.status', 'IN_PROGRESS')
            ->whereRaw('UNIX_TIMESTAMP(m.created_at) <= CAST(? AS DECIMAL(20,6))', [$cutoff]);

        return DB::query()
            ->selectSub($this->properties($hostId, $cutoff)->selectRaw('COUNT(*)'), 'properties_count')
            ->selectSub($this->rooms($hostId, $cutoff)->selectRaw('COUNT(*)'), 'rooms_count')
            ->selectSub($this->bookings($hostId, $cutoff)->where('b.status', 'PENDING')->selectRaw('COUNT(*)'), 'pending_bookings_count')
            ->selectSub($maintenance->selectRaw('COUNT(*)'), 'in_progress_maintenances_count')
            ->selectSub($recentJson, 'recent_json');
    }

    public function overview(string $hostId, HostDashboardPeriod $period): object
    {
        // Financial scope shares only Property/Room predicates. Booking.created_at/status
        // and Invoice.created_at do not determine revenue or the missing-date warning.
        $finance = $this->rooms($hostId, $period->generatedAt->format('U.u'))
            ->join('bookings as b', 'b.room_id', '=', 'r.id')->join('invoices as i', 'i.booking_id', '=', 'b.id')
            ->selectRaw("COALESCE(SUM(CASE WHEN i.payment_status = 'PAID' AND i.paid_at IS NULL THEN 1 ELSE 0 END), 0) AS missing_paid_at");
        $windows = ['summary' => ['start' => $period->months[0]['start'], 'end' => $period->months[array_key_last($period->months)]['end']]];
        foreach ($period->months as $index => $month) { $windows['month_'.$index] = $month; }
        foreach ($windows as $alias => $window) {
            $predicate = "i.payment_status = 'PAID' AND i.paid_at IS NOT NULL
                AND UNIX_TIMESTAMP(i.paid_at) >= CAST(? AS DECIMAL(20,6))
                AND UNIX_TIMESTAMP(i.paid_at) < CAST(? AS DECIMAL(20,6))
                AND UNIX_TIMESTAMP(i.paid_at) <= CAST(? AS DECIMAL(20,6))";
            $finance->selectRaw('CAST(COALESCE(SUM(CASE WHEN '.$predicate.' THEN i.total_amount ELSE 0.00 END), 0.00) AS CHAR) AS '.$alias.'_revenue',
                [(string) $window['start'], (string) $window['end'], $period->generatedAt->format('U.u')]);
        }
        // Every scalar subquery and the single-row financial aggregate share one SELECT/read view.
        return $this->snapshotQuery($hostId, $period->generatedAt)->fromSub($finance, 'finance_facts')
            ->addSelect('finance_facts.*')->first();
    }
}
