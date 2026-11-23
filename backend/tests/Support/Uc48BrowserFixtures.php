<?php

namespace Tests\Support;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Application;
use InvalidArgumentException;
use RuntimeException;

/** Synthetic read-side snapshots, never a substitute for TV2 workflow verification. */
final class Uc48BrowserFixtures
{
    public const SCENARIOS = ['Populated', 'Zero', 'BookingOnly', 'RevenueOnly', 'MissingDate', 'Large'];
    private const STATUSES = ['PENDING', 'CONFIRMED', 'CHECKED_IN', 'COMPLETED', 'CANCELLED', 'REJECTED'];

    public static function specification(string $scenario, string $anchor): array
    {
        if (!in_array($scenario, self::SCENARIOS, true)) throw new InvalidArgumentException('Unknown scenario.');
        $utc = new DateTimeZone('UTC');
        $first = (new DateTimeImmutable($anchor))->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'))
            ->modify('first day of this month')->setTime(0, 0);
        $at = static fn (int $offset): string => $first->modify($offset.' months')->setTimezone($utc)->format('Y-m-d H:i:s');
        $users = ['admin_a' => ['ADMIN', 'ACTIVE'], 'admin_b' => ['ADMIN', 'ACTIVE'],
            'host_a' => ['HOST', 'ACTIVE'], 'host_b' => ['HOST', 'ACTIVE'], 'customer' => ['CUSTOMER', 'ACTIVE'],
            'blocked' => ['ADMIN', 'BLOCKED'], 'inactive' => ['ADMIN', 'INACTIVE']];
        $bookings = [];
        $append = static function (string $created, string $status, ?array $invoice = null) use (&$bookings): void {
            $bookings[] = ['created_at' => $created, 'status' => $status, 'invoice' => $invoice];
        };
        if ($scenario === 'Populated') {
            $invoices = [
                ['PAID', $at(-1), '100.25'], ['PAID', $at(0), '200.50'], ['PAID', $at(0), '0.01'],
                ['REFUNDED', $at(-1), '500.00'], ['PAID', null, '300.00'], ['PAID', $at(12), '999.99'],
            ];
            foreach (self::STATUSES as $i => $status) $append($at($i < 3 ? -1 : 0), $status, $invoices[$i]);
            // Separate months let the owner exercise every data state in one live environment.
            $append($at(-4), 'PENDING');
            $append($at(-4), 'CANCELLED');
            $append($at(-13), 'COMPLETED', ['PAID', $at(-2), '0.75']);
            for ($i = 0; $i < 2; $i++) $append($at(-13), 'COMPLETED', ['PAID', $at(-3), '9999999999.99']);
        } elseif ($scenario === 'BookingOnly') {
            foreach (self::STATUSES as $status) $append($at(-1), $status);
        } elseif ($scenario === 'RevenueOnly' || $scenario === 'Large') {
            foreach ($scenario === 'Large' ? ['9999999999.99', '9999999999.99'] : ['100.25', '200.51'] as $amount) {
                $append($at(-13), 'COMPLETED', ['PAID', $at(-1), $amount]);
            }
        } elseif ($scenario === 'MissingDate') {
            $append($at(-13), 'COMPLETED', ['PAID', null, '300.00']);
        }
        return ['scenario' => $scenario, 'anchor_utc' => $anchor, 'users' => $users, 'bookings' => $bookings,
            'ui_months' => ['normal_from' => $first->modify('-1 month')->format('Y-m'), 'normal_to' => $first->format('Y-m'),
                'booking_only' => $first->modify('-4 months')->format('Y-m'),
                'revenue_only' => $first->modify('-2 months')->format('Y-m'),
                'large' => $first->modify('-3 months')->format('Y-m'), 'zero' => $first->modify('+1 month')->format('Y-m')]];
    }

    /** Native calendar + ledger iteration; does not call production DTOs, queries or mappers. */
    public static function oracle(array $spec, string $generatedAt, array $query = []): array
    {
        $utc = new DateTimeZone('UTC');
        $zone = new DateTimeZone('Asia/Ho_Chi_Minh');
        $cutoff = new DateTimeImmutable($generatedAt);
        $current = $cutoff->setTimezone($zone)->modify('first day of this month')->setTime(0, 0);
        $from = $query['from_month'] ?? $current->modify('-11 months')->format('Y-m');
        $to = $query['to_month'] ?? $current->format('Y-m');
        foreach ([$from, $to] as $month) {
            if (!is_string($month) || preg_match('/\A(?!0000)[0-9]{4}-(?:0[1-9]|1[0-2])\z/', $month) !== 1) throw new InvalidArgumentException('Invalid oracle period.');
        }
        $start = new DateTimeImmutable($from.'-01 00:00:00', $zone);
        $end = (new DateTimeImmutable($to.'-01 00:00:00', $zone))->modify('+1 month');
        $months = [];
        for ($cursor = $start; $cursor < $end; $cursor = $cursor->modify('+1 month')) {
            if (count($months) >= 12) throw new InvalidArgumentException('Oracle period exceeds twelve months.');
            $months[$cursor->format('Y-m')] = ['count' => 0, 'revenue' => '0.00', 'statuses' => array_fill_keys(self::STATUSES, 0)];
        }
        if ($months === []) throw new InvalidArgumentException('Reversed oracle period.');
        $missing = 0;
        foreach ($spec['bookings'] as $booking) {
            $created = new DateTimeImmutable($booking['created_at'], $utc);
            if ($created >= $start && $created < $end && $created <= $cutoff) {
                $key = $created->setTimezone($zone)->format('Y-m');
                $months[$key]['count']++;
                $months[$key]['statuses'][$booking['status']]++;
            }
            $invoice = $booking['invoice'];
            if ($invoice === null || $invoice[0] !== 'PAID') continue;
            if ($invoice[1] === null) { $missing++; continue; }
            $paid = new DateTimeImmutable($invoice[1], $utc);
            if ($paid < $start || $paid >= $end || $paid > $cutoff) continue;
            if (!is_string($invoice[2]) || preg_match('/\A(?:0|[1-9][0-9]*)\.[0-9]{2}\z/', $invoice[2]) !== 1) throw new InvalidArgumentException('Invalid oracle money.');
            $key = $paid->setTimezone($zone)->format('Y-m');
            $months[$key]['revenue'] = bcadd($months[$key]['revenue'], $invoice[2], 2);
        }
        $statuses = static fn (array $counts): array => array_map(static fn (string $status): array => ['status' => $status, 'count' => $counts[$status]], self::STATUSES);
        $summary = ['bookings_count' => 0, 'revenue_total' => '0.00', 'commission_revenue_total' => '0.00'];
        $allStatuses = array_fill_keys(self::STATUSES, 0);
        $monthly = [];
        foreach ($months as $month => $facts) {
            $summary['bookings_count'] += $facts['count'];
            $summary['revenue_total'] = bcadd($summary['revenue_total'], $facts['revenue'], 2);
            foreach (self::STATUSES as $status) $allStatuses[$status] += $facts['statuses'][$status];
            $monthly[] = ['month' => $month, 'bookings_count' => $facts['count'], 'revenue_total' => $facts['revenue'], 'commission_revenue_total' => '0.00', 'booking_status_counts' => $statuses($facts['statuses'])];
        }
        $iso = static fn (DateTimeImmutable $date): string => $date->setTimezone($utc)->format('Y-m-d\TH:i:s.u\Z');
        return ['period' => ['from_month' => $from, 'to_month' => $to, 'start_at' => $iso($start), 'end_at_exclusive' => $iso($end)],
            'report_timezone' => 'Asia/Ho_Chi_Minh', 'generated_at' => $iso($cutoff), 'summary' => $summary,
            'booking_status_counts' => $statuses($allStatuses), 'monthly' => $monthly,
            'warnings' => ['paid_invoices_missing_paid_at_global_count' => $missing, 'settled_commissions_missing_settled_at_global_count' => 0]];
    }

    public static function create(Application $app, array $manifest, string $password): array
    {
        Uc48BrowserEnvironment::assertApplication($app, $manifest);
        if (strlen($password) < 24) throw new InvalidArgumentException('Ephemeral password required.');
        $spec = self::specification($manifest['scenario'], $manifest['created_utc']);
        $db = $app['db']->connection();
        $actors = $db->transaction(function () use ($app, $db, $spec, $password): array {
            $stamp = ['created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'];
            $hash = $app['hash']->make($password);
            $actors = [];
            foreach ($spec['users'] as $name => [$role, $status]) {
                $actors[$name] = $db->table('users')->insertGetId(['name' => 'UC48 QA '.$name, 'email' => $name.'@uc48.example.test',
                    'password' => $hash, 'role' => $role, 'status' => $status, ...$stamp]);
            }
            if (!$app['hash']->check($password, $db->table('users')->where('id', $actors['admin_a'])->value('password'))) throw new RuntimeException('Hash mismatch.');
            $rooms = [];
            foreach (['ACTIVE', 'BLOCKED'] as $i => $status) {
                $property = $db->table('properties')->insertGetId(['host_id' => $actors[$i ? 'host_b' : 'host_a'], 'name' => 'UC48 QA '.$i,
                    'type' => 'HOTEL', 'address' => 'Synthetic QA address', 'status' => $status, ...$stamp]);
                $type = $db->table('room_types')->insertGetId(['property_id' => $property, 'name' => 'QA type', ...$stamp]);
                $rooms[] = $db->table('rooms')->insertGetId(['property_id' => $property, 'room_type_id' => $type, 'room_number' => '001',
                    'name' => 'QA room', 'capacity' => 2, 'price_per_night' => '50.00', 'status' => $i ? 'MAINTENANCE' : 'ACTIVE', ...$stamp]);
            }
            foreach ($spec['bookings'] as $i => $row) {
                $invoice = $row['invoice'];
                $amount = $invoice[2] ?? '50.00';
                $booking = $db->table('bookings')->insertGetId(['booking_code' => 'UC48-QA-B-'.$i, 'customer_id' => $actors['customer'],
                    'room_id' => $rooms[$i % 2], 'check_in_date' => '2026-12-20', 'check_out_date' => '2026-12-21',
                    'guest_count' => 1, 'number_of_nights' => 1, 'price_per_night' => $amount, 'room_total' => $amount,
                    'status' => $row['status'], 'created_at' => $row['created_at'], 'updated_at' => $row['created_at']]);
                if ($invoice === null) continue;
                $id = $db->table('invoices')->insertGetId(['invoice_code' => 'UC48-QA-I-'.$i, 'booking_id' => $booking,
                    'customer_name' => 'QA snapshot', 'customer_email' => 'snapshot@example.test', 'customer_phone' => '0900000000',
                    'property_name' => 'QA snapshot', 'room_name' => 'QA room', 'subtotal' => $amount, 'discount_amount' => '0.00',
                    'extra_fee' => '0.00', 'total_amount' => $amount, 'payment_status' => $invoice[0], 'paid_at' => $invoice[1], ...$stamp]);
                $db->table('invoice_items')->insert(['invoice_id' => $id, 'item_type' => 'ROOM', 'item_name' => 'QA snapshot',
                    'quantity' => '1.00', 'unit_price' => $amount, 'amount' => $amount, ...$stamp]);
            }
            return $actors;
        });
        return ['actors' => $actors, 'spec' => $spec, 'password_hash_verified' => true];
    }

    public static function audit(Application $app, array $manifest): array
    {
        Uc48BrowserEnvironment::assertApplication($app, $manifest);
        $db = $app['db']->connection();
        $hashes = [];
        foreach (['users', 'properties', 'room_types', 'rooms', 'services', 'bookings', 'booking_services', 'invoices', 'invoice_items'] as $table) {
            $hashes[$table] = hash('sha256', json_encode($db->table($table)->orderBy('id')->get(), JSON_THROW_ON_ERROR));
        }
        return ['business' => $hashes, 'session_users' => $db->table('sessions')->whereNotNull('user_id')
            ->selectRaw('user_id, COUNT(*) AS count')->groupBy('user_id')->orderBy('user_id')->get()->all()];
    }

    public static function canonical(mixed $value): mixed
    {
        if (!is_array($value)) return $value;
        if (!array_is_list($value)) ksort($value);
        return array_map([self::class, 'canonical'], $value);
    }
}
