<?php

namespace Tests\Support;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Application;

final class Uc39BrowserFixtures
{
    public static function create(Application $app, array $manifest, string $password): array
    {
        if (strlen($password) < 24) { throw new \RuntimeException('Ephemeral QA password required.'); }
        $db = $app['db']->connection();
        Uc39BrowserEnvironment::assertApplication($app, $manifest);
        Uc39DashboardDataFixtures::create($db);
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $first = $now->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'))->modify('first day of this month')->setTime(0, 0);
        $previous = $first->modify('-1 month');
        $created = $now->modify('-2 days')->format('Y-m-d H:i:s');
        foreach (['properties', 'rooms', 'bookings', 'maintenances'] as $table) { $db->table($table)->update(['created_at' => $created]); }
        foreach ($db->table('invoices')->get() as $invoice) {
            $paid = $invoice->paid_at === null ? null : ($invoice->id === 1006 ? $now->modify('+1 day')
                : (in_array($invoice->id, [1001, 4001], true) ? $previous : $first));
            $db->table('invoices')->where('id', $invoice->id)->update(['paid_at' => $paid?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s')]);
        }
        $actors = ['host_a' => 1, 'zero' => 2, 'booking_only' => 3, 'revenue_only' => 4,
            'missing_date' => 5, 'large' => 6, 'fractional' => 7, 'host_b' => 8, 'empty' => 9,
            'no_rooms' => 10, 'customer' => 11, 'admin_a' => 12, 'blocked' => 13, 'inactive' => 14];
        $users = []; $facts = []; $passwordHash = $app['hash']->make($password);
        foreach ($actors as $actor => $id) {
            $u = $db->table('users')->where('id', $id)->first();
            $users[$actor] = [$u->role, $u->status];
            $db->table('users')->where('id', $id)->update(['email' => $actor.'@uc39.example.test', 'password' => $passwordHash]);
            $properties = $id === 9 || $id > 10 ? 0 : 2;
            $bookings = $id > 10 ? [] : $db->table('bookings')->where('room_id', $id * 100 + 1)->orderByDesc('id')->get()->all();
            $recent = [];
            foreach (array_slice($bookings, 0, 5) as $b) {
                $resource = $id * 100 + 1;
                $recent[] = ['id' => (string) $b->id, 'booking_code' => $b->booking_code, 'status' => $b->status,
                    'created_at' => (new DateTimeImmutable($created, new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.u\Z'),
                    'check_in_date' => $b->check_in_date, 'check_out_date' => $b->check_out_date,
                    'property' => ['id' => (string) $resource, 'name' => 'Cơ sở '.$resource],
                    'room' => ['id' => (string) $resource, 'name' => 'Phòng '.$resource]];
            }
            $invoices = [];
            foreach ($bookings as $b) {
                $i = $db->table('invoices')->where('booking_id', $b->id)->first();
                if ($i) { $invoices[] = ['status' => $i->payment_status, 'amount' => $i->total_amount, 'paid' => $i->paid_at]; }
            }
            $facts[$actor] = ['scope' => ['properties_count' => $properties],
                'snapshot' => ['rooms_count' => $properties && $id !== 10 ? 2 : 0,
                    'pending_bookings_count' => $id === 1 || $id === 3 ? 2 : 0,
                    'in_progress_maintenances_count' => $id === 1 ? 2 : 0], 'recent' => $recent, 'invoices' => $invoices];
        }
        return ['actors' => $actors, 'spec' => ['users' => $users, 'facts' => $facts,
            'ui_months' => ['normal_from' => $previous->format('Y-m'), 'normal_to' => $first->format('Y-m'),
                'booking_only' => $first->modify('-4 months')->format('Y-m'), 'revenue_only' => $first->modify('-2 months')->format('Y-m'),
                'large' => $first->modify('-3 months')->format('Y-m'), 'zero' => $first->modify('+1 month')->format('Y-m')]]];
    }

    public static function oracle(array $spec, string $generated, array $query, string $actor): array
    {
        $zone = new DateTimeZone('Asia/Ho_Chi_Minh'); $utc = new DateTimeZone('UTC');
        $cutoff = new DateTimeImmutable($generated);
        $last = $cutoff->setTimezone($zone)->modify('first day of this month')->setTime(0, 0);
        $from = new DateTimeImmutable(($query['from_month'] ?? $last->modify('-11 months')->format('Y-m')).'-01', $zone);
        $to = new DateTimeImmutable(($query['to_month'] ?? $last->format('Y-m')).'-01', $zone);
        $end = $to->modify('+1 month'); $rows = []; $total = 0; $warning = 0;
        $f = $spec['facts'][$actor];
        foreach ($f['invoices'] as $i) { if ($i['status'] === 'PAID' && $i['paid'] === null) { $warning++; } }
        for ($cursor = $from; $cursor < $end; $cursor = $cursor->modify('+1 month')) {
            if (count($rows) >= 12) { throw new \RuntimeException('Invalid oracle period.'); }
            $cents = 0;
            foreach ($f['invoices'] as $i) {
                if ($i['status'] !== 'PAID' || $i['paid'] === null) { continue; }
                $paid = new DateTimeImmutable($i['paid'], $utc);
                if ($paid >= $cursor && $paid < $cursor->modify('+1 month') && $paid <= $cutoff) { $cents += (int) str_replace('.', '', $i['amount']); }
            }
            $total += $cents; $rows[] = ['month' => $cursor->format('Y-m'), 'revenue_total' => self::money($cents)];
        }
        $iso = static fn ($d) => $d->setTimezone($utc)->format('Y-m-d\TH:i:s.u\Z');
        return ['scope' => $f['scope'], 'snapshot' => $f['snapshot'], 'recent_bookings' => $f['recent'],
            'generated_at' => $generated, 'report_timezone' => 'Asia/Ho_Chi_Minh',
            'period' => ['from_month' => $from->format('Y-m'), 'to_month' => $to->format('Y-m'), 'start_at' => $iso($from), 'end_at_exclusive' => $iso($end)],
            'summary' => ['revenue_total' => self::money($total)], 'revenue_by_month' => $rows,
            'warnings' => ['paid_invoices_missing_paid_at_scope_count' => $warning]];
    }

    private static function money(int $cents): string { return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT); }
    public static function canonical(array $data): string
    {
        $sort = static function (&$a) use (&$sort): void { if (!is_array($a)) { return; } if (!array_is_list($a)) { ksort($a); } foreach ($a as &$v) { $sort($v); } };
        $sort($data); return json_encode($data, JSON_THROW_ON_ERROR);
    }
    public static function audit(Application $app, array $manifest): array
    {
        Uc39BrowserEnvironment::assertApplication($app, $manifest); $db = $app['db']->connection(); $business = [];
        foreach (['users','properties','property_images','room_types','rooms','room_images','amenities','room_amenities','maintenances','services','bookings','booking_services','invoices','invoice_items','reviews','faqs','chat_sessions','chat_messages'] as $table) {
            $query = $db->table($table);
            if ($table === 'room_amenities') { $query->orderBy('room_id')->orderBy('amenity_id'); } else { $query->orderBy('id'); }
            $business[$table] = hash('sha256', serialize($query->get()->all()));
        }
        return ['business' => $business, 'session_users' => $db->table('sessions')->whereNotNull('user_id')->selectRaw('user_id, COUNT(*) AS count')->groupBy('user_id')->get()->all()];
    }
}
