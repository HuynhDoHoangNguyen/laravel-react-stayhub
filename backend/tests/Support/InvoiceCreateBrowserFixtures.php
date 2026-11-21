<?php

namespace Tests\Support;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/** Synthetic stored inputs and independent literal oracles; no production calculator/mapper. */
final class InvoiceCreateBrowserFixtures
{
    public static function recipe(string $kind): array
    {
        if (!in_array($kind, ['VND-SERVICES', 'ROOM-ONLY', 'ZERO', 'ROUND', 'MAX', 'DUPLICATE', 'NOT-COMPLETED', 'FOREIGN', 'DOUBLE', 'UNKNOWN'], true)) {
            throw new RuntimeException('Unknown browser create recipe.');
        }
        $nights = $kind === 'MAX' ? 1 : ($kind === 'ROUND' || $kind === 'ZERO' || $kind === 'ROOM-ONLY' ? 2 : 3);
        [$price, $room, $total] = match ($kind) {
            'ROUND' => ['100.25', '200.50', '225.78'],
            'ROOM-ONLY' => ['100000.00', '200000.00', '200000.00'],
            'ZERO' => ['0.00', '0.00', '0.00'],
            'MAX' => ['9999999999.99', '9999999999.99', '9999999999.99'],
            default => ['1200000.00', '3600000.00', '4150000.00'],
        };
        $owner = $kind === 'FOREIGN' ? 'H_B' : 'H_A';
        $items = [['item_type' => 'ROOM', 'item_name' => 'Current room '.$owner,
            'description' => 'Tiền phòng ('.$nights.' đêm × '.$price.')',
            'quantity' => $nights.'.00', 'unit_price' => $price, 'amount' => $room]];
        $services = match ($kind) {
            'ROUND' => [['1.00', '12.75', '12.75'], ['1.25', '10.02', '12.53']],
            'ROOM-ONLY', 'ZERO', 'MAX' => [],
            default => [['2.00', '150000.00', '300000.00'], ['1.00', '250000.00', '250000.00']],
        };
        foreach ($services as $i => [$quantity, $unitPrice, $amount]) {
            $items[] = ['item_type' => 'SERVICE', 'item_name' => 'Booked service '.$i, 'description' => null,
                'quantity' => $quantity, 'unit_price' => $unitPrice, 'amount' => $amount];
        }
        return ['nights' => $nights, 'price' => $price, 'room_total' => $room, 'total' => $total,
            'items' => $items, 'owner' => $owner, 'status' => $kind === 'NOT-COMPLETED' ? 'CONFIRMED' : 'COMPLETED'];
    }

    public static function create(Application $app, array $manifest, string $password): array
    {
        if (strlen($password) < 24 || DB::transactionLevel() !== 0 || ($manifest['scenario'] ?? null) !== 'InvoiceCreate') {
            throw new RuntimeException('Create UI requires committed owned fixtures and a strong ephemeral password.');
        }
        return DB::transaction(function () use ($password): array {
            $base = (new InvoiceCreateAcceptanceFixtures())->create();
            $actors = [];
            foreach ($base->actors as $label => $user) {
                DB::table('users')->where('id', $user->id)->update(['password' => Hash::make($password)]);
                $actors[$label] = ['id' => $user->id, 'email' => $user->email, 'role' => $user->role->value, 'status' => 'ACTIVE'];
            }
            $bookings = [];
            $serviceIds = ['H_A' => $base->serviceIds, 'H_B' => []];
            $foreignProperty = DB::table('properties')->where('host_id', $actors['H_B']['id'])->value('id');
            foreach ([0, 1] as $i) {
                $serviceIds['H_B'][] = DB::table('services')->insertGetId(['property_id' => $foreignProperty,
                    'name' => 'Foreign catalog '.$i, 'unit' => 'Lần', 'price' => '888.88', 'status' => true]);
            }
            foreach (['P', 'UI'] as $family) {
                foreach (['VND-SERVICES', 'ROOM-ONLY', 'ZERO', 'ROUND', 'MAX', 'DUPLICATE', 'NOT-COMPLETED', 'FOREIGN', 'DOUBLE', 'UNKNOWN'] as $kind) {
                    $recipe = self::recipe($kind);
                    $roomId = DB::table('rooms')->where('room_number', $recipe['owner'])->value('id');
                    $id = DB::table('bookings')->insertGetId(['booking_code' => $family.'-'.$kind,
                        'customer_id' => $actors['C_A']['id'], 'room_id' => $roomId,
                        'check_in_date' => '2026-11-10', 'check_out_date' => '2026-11-'.(10 + $recipe['nights']),
                        'guest_count' => 1, 'number_of_nights' => $recipe['nights'], 'price_per_night' => $recipe['price'],
                        'room_total' => $recipe['room_total'], 'status' => $recipe['status']]);
                    foreach (array_slice($recipe['items'], 1) as $i => $item) {
                        DB::table('booking_services')->insert(['booking_id' => $id, 'service_id' => $serviceIds[$recipe['owner']][$i],
                            'service_name' => $item['item_name'], 'unit' => 'Lần', 'quantity' => $item['quantity'],
                            'unit_price' => $item['unit_price'], 'amount' => $item['amount']]);
                    }
                    $header = ['booking_id' => $id, 'customer_name' => 'Snapshot C_A',
                        'customer_email' => $actors['C_A']['email'], 'customer_phone' => '0900000000',
                        'property_name' => 'Current property '.$recipe['owner'], 'room_name' => 'Current room '.$recipe['owner'],
                        'subtotal' => $recipe['total'], 'discount_amount' => '0.00', 'extra_fee' => '0.00',
                        'total_amount' => $recipe['total'], 'payment_status' => 'UNPAID', 'paid_at' => null];
                    $label = $family.'-'.$kind;
                    $bookings[$label] = ['id' => $id, 'family' => $family, 'kind' => $kind, 'owner' => $recipe['owner'],
                        'mutable' => !in_array($kind, ['DUPLICATE', 'NOT-COMPLETED', 'FOREIGN'], true),
                        'header' => $header, 'items' => $recipe['items'], 'url' => '/host/bookings/'.$id.'/create-invoice'];
                    if ($kind === 'DUPLICATE') {
                        $stamp = '2026-11-01 01:00:00';
                        $invoice = DB::table('invoices')->insertGetId([...$header, 'invoice_code' => 'QA-'.$label,
                            'created_at' => $stamp, 'updated_at' => $stamp]);
                        foreach ($recipe['items'] as $item) {
                            DB::table('invoice_items')->insert([...$item, 'invoice_id' => $invoice, 'created_at' => $stamp, 'updated_at' => $stamp]);
                        }
                    }
                }
            }
            return ['actors' => $actors, 'bookings' => $bookings, 'missing_id' => 999999999,
                'notice' => 'Committed synthetic QA Booking inputs; P probes and UI bookings are separate. No TV2 checkout/UI evidence.'];
        });
    }

    public static function snapshot(): array
    {
        $source = (new InvoiceCreateAcceptanceFixtures())->fingerprint();
        unset($source['sessions']);
        return ['source' => $source,
            'invoices' => DB::table('invoices')->orderBy('id')->get()->map(static fn ($row) => (array) $row)->all(),
            'items' => DB::table('invoice_items')->orderBy('id')->get()->map(static fn ($row) => (array) $row)->all(),
            'session_users' => DB::table('sessions')->whereNotNull('user_id')->selectRaw('user_id, COUNT(*) AS count')
                ->groupBy('user_id')->orderBy('user_id')->get()->map(static fn ($row) => (array) $row)->all()];
    }

    private static function same(mixed $left, mixed $right): bool
    {
        return InvoiceReadBrowserFixtures::canonical($left) === InvoiceReadBrowserFixtures::canonical($right);
    }

    /** Pure allowlist audit: baseline outputs immutable, only complete new outputs for explicit Booking IDs. */
    public static function verifyDelta(array $before, array $after, array $bookings, array $allowedIds): array
    {
        if (!self::same($before['source'], $after['source'])) throw new RuntimeException('Create QA source records changed.');
        $baselineInvoices = array_column($before['invoices'], null, 'id');
        $baselineItems = array_column($before['items'], null, 'id');
        $afterInvoices = array_column($after['invoices'], null, 'id');
        $afterItems = array_column($after['items'], null, 'id');
        if (count($afterInvoices) !== count($after['invoices']) || count($afterItems) !== count($after['items'])) {
            throw new RuntimeException('Duplicate output row identifiers.');
        }
        foreach ([[$baselineInvoices, $afterInvoices], [$baselineItems, $afterItems]] as [$old, $new]) {
            foreach ($old as $id => $row) {
                if (!isset($new[$id]) || !self::same($row, $new[$id])) throw new RuntimeException('Existing output changed or disappeared.');
            }
        }
        $specs = array_column($bookings, null, 'id');
        $created = [];
        $newInvoiceIds = [];
        if (count(array_unique(array_column($after['invoices'], 'booking_id'))) !== count($after['invoices'])
            || count(array_unique(array_column($after['invoices'], 'invoice_code'))) !== count($after['invoices'])) {
            throw new RuntimeException('Duplicate Booking or invoice code in outputs.');
        }
        foreach (array_diff_key($afterInvoices, $baselineInvoices) as $id => $row) {
            $booking = $specs[$row['booking_id']] ?? null;
            if (!$booking || !$booking['mutable'] || !in_array($row['booking_id'], $allowedIds, true)
                || isset($created[$row['booking_id']])) throw new RuntimeException('Invoice outside allowed Booking mutation set.');
            self::assertGenerated($row, true);
            $header = $row;
            unset($header['id'], $header['invoice_code'], $header['created_at'], $header['updated_at']);
            if (!self::same($booking['header'], $header)) throw new RuntimeException('Persisted Invoice header differs from literal oracle.');
            $items = array_values(array_filter($after['items'], static fn ($item) => $item['invoice_id'] === $id));
            usort($items, static fn ($a, $b) => $a['id'] <=> $b['id']);
            $projection = [];
            foreach ($items as $item) {
                if (isset($baselineItems[$item['id']])) throw new RuntimeException('New Invoice reused an old item.');
                self::assertGenerated($item, false);
                unset($item['id'], $item['invoice_id'], $item['created_at'], $item['updated_at']);
                $projection[] = $item;
            }
            if (!self::same($projection, $booking['items'])) throw new RuntimeException('Persisted items differ from literal oracle.');
            $created[$row['booking_id']] = $id;
            $newInvoiceIds[] = $id;
        }
        foreach (array_diff_key($afterItems, $baselineItems) as $item) {
            if (!in_array($item['invoice_id'], $newInvoiceIds, true)) throw new RuntimeException('Unexpected or orphan item.');
        }
        return ['matched' => true, 'created' => $created, 'new_invoices' => count($created),
            'new_items' => count($afterItems) - count($baselineItems), 'source_unchanged' => true];
    }

    private static function assertGenerated(array $row, bool $invoice): void
    {
        if (!is_int($row['id'] ?? null) || $row['id'] < 1) throw new RuntimeException('Invalid generated ID.');
        foreach (['created_at', 'updated_at'] as $field) {
            if (!is_string($row[$field] ?? null) || preg_match('/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\z/', $row[$field]) !== 1) {
                throw new RuntimeException('Missing generated timestamp.');
            }
        }
        if ($invoice && preg_match('/\AINV-\d{8}(?:\d{6})?-[A-F0-9]{6,8}\z/', $row['invoice_code'] ?? '') !== 1) {
            throw new RuntimeException('Invalid generated invoice code.');
        }
    }

    /** API oracle uses static fixture fields; only generated ID/code/time are taken from audited DB rows. */
    public static function verifyResponse(array $response, array $state, int $bookingId): void
    {
        $spec = array_column($state['fixture']['bookings'], null, 'id')[$bookingId] ?? throw new RuntimeException('Unknown Booking response.');
        $rows = array_values(array_filter($state['snapshot']['invoices'], static fn ($row) => $row['booking_id'] === $bookingId));
        if (count($rows) !== 1) throw new RuntimeException('Expected one persisted Invoice for response.');
        $row = $rows[0];
        $iso = static fn ($stamp) => str_replace(' ', 'T', $stamp).'.000000Z';
        $expected = ['id' => $row['id'], 'invoice_code' => $row['invoice_code'], ...$spec['header'], 'created_at' => $iso($row['created_at'])];
        $items = array_values(array_filter($state['snapshot']['items'], static fn ($item) => $item['invoice_id'] === $row['id']));
        usort($items, static fn ($a, $b) => $a['id'] <=> $b['id']);
        $expected['items'] = [];
        foreach ($spec['items'] as $i => $oracle) {
            $expected['items'][] = ['id' => $items[$i]['id'], 'invoice_id' => $row['id'], ...$oracle];
        }
        InvoiceReadBrowserFixtures::verify($response, ['data' => $expected]);
    }
}
