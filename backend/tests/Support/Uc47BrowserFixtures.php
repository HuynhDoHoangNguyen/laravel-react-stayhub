<?php

namespace Tests\Support;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Application;
use InvalidArgumentException;
use OverflowException;
use RuntimeException;

/** Browser-only synthetic data and an oracle independent of Dashboard query/mapping code. */
final class Uc47BrowserFixtures
{
    private const STATUSES = ['PENDING', 'CONFIRMED', 'CHECKED_IN', 'COMPLETED', 'CANCELLED', 'REJECTED'];

    public static function cents(string $amount): int
    {
        if (PHP_INT_SIZE < 8 || preg_match('/\A(0|[1-9][0-9]*)\.[0-9]{2}\z/', $amount) !== 1) {
            throw new InvalidArgumentException('Oracle requires a positive canonical decimal and 64-bit PHP.');
        }
        $digits = ltrim(str_replace('.', '', $amount), '0') ?: '0';
        if (strlen($digits) > strlen((string) PHP_INT_MAX) || (strlen($digits) === strlen((string) PHP_INT_MAX) && strcmp($digits, (string) PHP_INT_MAX) > 0)) {
            throw new OverflowException('Oracle amount exceeds integer cents.');
        }
        return (int) $digits;
    }

    private static function add(int $left, int $right): int
    {
        if ($right > PHP_INT_MAX - $left) throw new OverflowException('Oracle sum exceeds integer cents.');
        return $left + $right;
    }

    private static function decimal(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function specification(string $scenario, string $anchor): array
    {
        if (!in_array($scenario, ['Populated', 'Zero', 'NoRevenue', 'Large'], true)) throw new InvalidArgumentException('Unknown browser fixture scenario.');
        $local = (new DateTimeImmutable($anchor))->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'));
        $first = $local->modify('first day of this month')->setTime(0, 0);
        $previous = $first->modify('-1 month')->modify('+14 days')->setTime(12, 0);
        $users = ['admin_a' => ['ADMIN', 'ACTIVE'], 'admin_b' => ['ADMIN', 'ACTIVE']];
        if ($scenario !== 'Zero') $users += ['host_a' => ['HOST', 'ACTIVE'], 'host_b' => ['HOST', 'ACTIVE'],
            'host_none' => ['HOST', 'INACTIVE'], 'customer' => ['CUSTOMER', 'ACTIVE'],
            'blocked' => ['ADMIN', 'BLOCKED'], 'inactive' => ['ADMIN', 'INACTIVE']];
        $invoices = [];
        if ($scenario !== 'Zero') {
            foreach ([['PAID', $previous, '100.25'], ['PAID', $first, '200.50'], ['UNPAID', null, '50.00'],
                ['REFUNDED', $previous, '70.00'], ['PAID', null, '25.00']] as $i => [$status, $date, $amount]) {
                if ($scenario === 'Large' && $i < 2) $amount = '9999999999.99';
                if ($scenario === 'NoRevenue') { $status = 'UNPAID'; $date = null; }
                $invoices[] = ['payment_status' => $status, 'paid_at' => $date?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'), 'total_amount' => $amount];
            }
        }
        return ['scenario' => $scenario, 'anchor_utc' => (new DateTimeImmutable($anchor))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z'),
            'users' => $users, 'property_statuses' => $scenario === 'Zero' ? [] : ['ACTIVE', 'INACTIVE', 'BLOCKED', 'PENDING'],
            'room_statuses' => $scenario === 'Zero' ? [] : ['ACTIVE', 'MAINTENANCE', 'INACTIVE', 'ACTIVE'],
            'booking_statuses' => $scenario === 'Zero' ? [] : ['PENDING', 'CONFIRMED', 'CHECKED_IN', 'COMPLETED', 'COMPLETED', 'COMPLETED', 'COMPLETED', 'COMPLETED', 'CANCELLED', 'REJECTED'],
            'invoices' => $invoices];
    }

    public static function oracle(array $spec, string $generatedAt): array
    {
        $asOf = new DateTimeImmutable($generatedAt);
        $zone = new DateTimeZone('Asia/Ho_Chi_Minh');
        $last = $asOf->setTimezone($zone)->format('Y-m');
        // Native calendar iteration, independent of the production Carbon period builder.
        $cursor = new DateTimeImmutable($last.'-01 00:00:00', $zone);
        $cursor = $cursor->modify('-11 months');
        $months = [];
        for ($i = 0; $i < 12; $i++) { $months[$cursor->format('Y-m')] = 0; $cursor = $cursor->modify('+1 month'); }
        $total = $excluded = 0;
        foreach ($spec['invoices'] as $invoice) {
            if ($invoice['payment_status'] !== 'PAID') continue;
            if ($invoice['paid_at'] === null) { $excluded++; continue; }
            $amount = self::cents($invoice['total_amount']);
            $total = self::add($total, $amount);
            $paid = new DateTimeImmutable($invoice['paid_at'], new DateTimeZone('UTC'));
            $key = $paid->setTimezone($zone)->format('Y-m');
            if ($paid <= $asOf && array_key_exists($key, $months)) $months[$key] = self::add($months[$key], $amount);
        }
        return ['summary' => ['users_count' => count($spec['users']),
            'hosts_count' => count(array_filter($spec['users'], fn ($actor) => $actor[0] === 'HOST')),
            'properties_count' => count($spec['property_statuses']), 'rooms_count' => count($spec['room_statuses']),
            'bookings_count' => count($spec['booking_statuses']), 'revenue_total' => self::decimal($total)],
            'excluded_paid_invoices_count' => $excluded, 'generated_at' => $generatedAt,
            'booking_status_counts' => array_map(fn ($status) => ['status' => $status, 'count' => count(array_keys($spec['booking_statuses'], $status, true))], self::STATUSES),
            'revenue_by_month' => array_map(fn ($month, $amount) => ['month' => $month, 'total_amount' => self::decimal($amount)], array_keys($months), array_values($months)),
            'report_timezone' => 'Asia/Ho_Chi_Minh'];
    }

    public static function create(Application $app, array $manifest, string $password): array
    {
        Uc47BrowserEnvironment::assertApplication($app, $manifest);
        if (strlen($password) < 24) throw new InvalidArgumentException('Ephemeral fixture password is required.');
        $spec = self::specification($manifest['scenario'], $manifest['created_utc']);
        $db = $app['db']->connection();
        $fixture = $db->transaction(function () use ($app, $db, $spec, $password): array {
            $stamp = ['created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'];
            // Match the application's hasher so login does not silently rehash fixture users.
            $hash = $app['hash']->make($password);
            $actors = [];
            foreach ($spec['users'] as $key => [$role, $status]) {
                $actors[$key] = $db->table('users')->insertGetId(['name' => 'UC47 Browser '.$key,
                    'email' => $key.'@uc47.example.test', 'password' => $hash, 'role' => $role, 'status' => $status, ...$stamp]);
            }
            if (!$app['hash']->check($password, $db->table('users')->where('id', $actors['admin_a'])->value('password'))) throw new RuntimeException('Fixture password hash verification failed.');
            $rooms = [];
            foreach ($spec['property_statuses'] as $i => $status) {
                $property = $db->table('properties')->insertGetId(['host_id' => $actors[$i % 2 ? 'host_b' : 'host_a'], 'name' => 'UC47 Browser '.$i,
                    'type' => 'HOTEL', 'address' => 'Synthetic QA address', 'status' => $status, ...$stamp]);
                $type = $db->table('room_types')->insertGetId(['property_id' => $property, 'name' => 'QA room type', ...$stamp]);
                $rooms[] = $db->table('rooms')->insertGetId(['property_id' => $property, 'room_type_id' => $type,
                    'room_number' => '001', 'name' => 'QA room '.$i, 'capacity' => 2, 'price_per_night' => '50.00', 'status' => $spec['room_statuses'][$i], ...$stamp]);
            }
            foreach ($spec['booking_statuses'] as $i => $status) {
                $invoice = $i >= 3 && $i <= 7 ? $spec['invoices'][$i - 3] : null;
                $amount = $invoice['total_amount'] ?? '50.00';
                $checkout = new DateTimeImmutable($invoice['paid_at'] ?? $spec['anchor_utc'], new DateTimeZone('UTC'));
                $checkout = $checkout->modify('-1 day');
                $booking = $db->table('bookings')->insertGetId(['booking_code' => 'UC47-BROWSER-B-'.$i,
                    'customer_id' => $actors['customer'], 'room_id' => $rooms[$i % 4], 'check_in_date' => $checkout->modify('-1 day')->format('Y-m-d'),
                    'check_out_date' => $checkout->format('Y-m-d'), 'guest_count' => 1, 'number_of_nights' => 1,
                    'price_per_night' => $amount, 'room_total' => $amount, 'status' => $status, ...$stamp]);
                if ($invoice !== null) {
                    $id = $db->table('invoices')->insertGetId(['invoice_code' => 'UC47-BROWSER-I-'.$i, 'booking_id' => $booking,
                        'customer_name' => 'QA customer snapshot', 'customer_email' => 'qa@example.test', 'customer_phone' => '0900000000',
                        'property_name' => 'QA property snapshot', 'room_name' => 'QA room snapshot', 'subtotal' => $amount,
                        'discount_amount' => '0.00', 'extra_fee' => '0.00', 'total_amount' => $amount,
                        'payment_status' => $invoice['payment_status'], 'paid_at' => $invoice['paid_at'], ...$stamp]);
                    $db->table('invoice_items')->insert(['invoice_id' => $id, 'item_type' => 'ROOM', 'item_name' => 'QA one night',
                        'quantity' => '1.00', 'unit_price' => $amount, 'amount' => $amount, ...$stamp]);
                }
            }
            return ['actors' => $actors, 'password_hash_verified' => true];
        });
        // Verify persisted snapshot arithmetic independently, without production pricing services.
        foreach ($db->table('invoices')->get() as $invoice) {
            $booking = $db->table('bookings')->where('id', $invoice->booking_id)->first();
            $item = $db->table('invoice_items')->where('invoice_id', $invoice->id)->first();
            if ($booking->status !== 'COMPLETED' || $booking->number_of_nights !== 1
                || $booking->price_per_night !== $invoice->total_amount || $booking->room_total !== $invoice->total_amount
                || $item->amount !== $invoice->total_amount || $invoice->subtotal !== $invoice->total_amount
                || $invoice->discount_amount !== '0.00' || $invoice->extra_fee !== '0.00') throw new RuntimeException('Persisted fixture snapshot arithmetic mismatch.');
        }
        return $fixture + ['spec' => $spec, 'financial_snapshots_verified' => true];
    }

    public static function audit(Application $app, array $manifest): array
    {
        Uc47BrowserEnvironment::assertApplication($app, $manifest);
        $db = $app['db']->connection();
        $hashes = [];
        foreach (['users', 'properties', 'room_types', 'rooms', 'services', 'bookings', 'booking_services', 'invoices', 'invoice_items'] as $table) {
            $hashes[$table] = hash('sha256', json_encode($db->table($table)->orderBy('id')->get(), JSON_THROW_ON_ERROR));
        }
        return ['business' => $hashes, 'session_users' => $db->table('sessions')->whereNotNull('user_id')->selectRaw('user_id, COUNT(*) AS count')->groupBy('user_id')->orderBy('user_id')->get()->all()];
    }

    public static function canonical(mixed $value): mixed
    {
        if (!is_array($value)) return $value;
        if (!array_is_list($value)) ksort($value);
        return array_map([self::class, 'canonical'], $value);
    }
}
