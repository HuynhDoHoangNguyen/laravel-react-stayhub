<?php

namespace Tests\Support;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/** Committed synthetic browser data; never calls production pricing, create or response mapping. */
final class InvoiceReadBrowserFixtures
{
    public static function orders(): array
    {
        $extra = array_map(static fn ($n) => sprintf('E%02d', $n), range(12, 1));
        return ['H_A' => [...$extra, 'I04', 'I06', 'I02', 'I01'], 'H_B' => ['I05', 'I03'],
            'C_A' => [...$extra, 'I04', 'I03', 'I01'], 'C_B' => ['I05', 'I06', 'I02'],
            'H_EMPTY' => [], 'C_EMPTY' => []];
    }

    public static function create(Application $app, array $manifest, string $password): array
    {
        if (strlen($password) < 24 || DB::connection()->transactionLevel() !== 0) {
            throw new RuntimeException('Browser preparation requires a strong ephemeral password and actual committed rows.');
        }
        $base = (new InvoiceReadAcceptanceFixtures())->create();
        $actors = [];
        foreach ($base->actors as $label => $user) {
            DB::table('users')->where('id', $user->id)->update(['password' => Hash::make($password)]);
            $actors[$label] = ['id' => $user->id, 'email' => $user->email, 'role' => $user->role->value, 'status' => 'ACTIVE'];
        }
        foreach (['BLOCKED', 'INACTIVE'] as $status) {
            $label = 'C_'.$status;
            $email = strtolower($label).'@invoice-read.example.test';
            $id = DB::table('users')->insertGetId(['name' => 'QA '.$label, 'email' => $email,
                'password' => Hash::make($password), 'phone' => '0900000000', 'role' => 'CUSTOMER', 'status' => $status]);
            $actors[$label] = ['id' => $id, 'email' => $email, 'role' => 'CUSTOMER', 'status' => $status];
        }
        foreach (range(1, 12) as $n) {
            $label = sprintf('E%02d', $n);
            $created = sprintf('2026-11-%02d 01:00:00', $n + 5);
            $iso = str_replace(' ', 'T', $created).'.000000Z';
            $amount = (string) (10000 + $n).'.00';
            $booking = DB::table('bookings')->insertGetId(['booking_code' => 'UI-PAGE-'.$label,
                'customer_id' => $actors['C_A']['id'], 'room_id' => $base->rooms['P_A1'],
                'check_in_date' => '2026-10-20', 'check_out_date' => '2026-10-21', 'guest_count' => 1,
                'number_of_nights' => 1, 'price_per_night' => $amount, 'room_total' => $amount, 'status' => 'COMPLETED']);
            $raw = ['invoice_code' => 'UI-PAGE-'.$label, 'booking_id' => $booking,
                'customer_name' => 'Khách snapshot UI nhiều trang', 'customer_email' => 'ui-page@example.test',
                'customer_phone' => '0900000000', 'property_name' => 'Cơ sở snapshot UI nhiều trang',
                'room_name' => 'Phòng snapshot '.$label, 'subtotal' => $amount, 'discount_amount' => '0.00',
                'extra_fee' => '0.00', 'total_amount' => $amount, 'payment_status' => 'PAID',
                'paid_at' => $created, 'created_at' => $created];
            $id = DB::table('invoices')->insertGetId([...$raw, 'updated_at' => $created]);
            $base->headers[$label] = ['id' => $id, ...$raw, 'paid_at' => $iso, 'created_at' => $iso];
            $item = ['invoice_id' => $id, 'item_type' => 'ROOM', 'item_name' => 'ROOM '.$label,
                'description' => null, 'quantity' => '1.00', 'unit_price' => $amount, 'amount' => $amount];
            $itemId = DB::table('invoice_items')->insertGetId($item);
            $base->details[$label] = [...$base->headers[$label], 'items' => [['id' => $itemId, ...$item]]];
        }
        return ['actors' => $actors, 'orders' => self::orders(), 'headers' => $base->headers, 'details' => $base->details,
            'extras' => array_map(static fn ($n) => sprintf('E%02d', $n), range(12, 1)),
            'counts' => ['H_A' => ['total' => 16, 'PAID' => 14, 'UNPAID' => 1, 'REFUNDED' => 1],
                'C_A' => ['total' => 15, 'PAID' => 13, 'UNPAID' => 1, 'REFUNDED' => 1]],
            'notice' => 'Synthetic stored read fixtures; no Booking production or browser UI evidence.'];
    }

    public static function canonical(mixed $value): mixed
    {
        if (!is_array($value)) return $value;
        if (!array_is_list($value)) ksort($value);
        foreach ($value as &$item) $item = self::canonical($item);
        return $value;
    }

    /** Strict projection and ordering oracle; pagination expectations come from the static specification. */
    public static function verify(array $response, array $expected): void
    {
        if (self::canonical($response['data'] ?? null) !== self::canonical($expected['data'])) {
            throw new RuntimeException('Invoice HTTP projection differs from fixture oracle.');
        }
        if (isset($expected['total'])) {
            foreach (['total', 'current_page', 'per_page', 'last_page'] as $field) {
                if (($response['meta'][$field] ?? null) !== $expected[$field]) {
                    throw new RuntimeException('Invoice HTTP pagination differs from fixture oracle.');
                }
            }
            if (array_keys($response) !== ['data', 'links', 'meta']
                || array_keys($response['links'] ?? []) !== ['first', 'last', 'prev', 'next']) {
                throw new RuntimeException('Invoice HTTP envelope differs.');
            }
        } elseif (array_keys($response) !== ['data']) {
            throw new RuntimeException('Invoice HTTP detail envelope differs.');
        }
    }

    public static function audit(Application $app, array $manifest): array
    {
        $business = (new InvoiceReadAcceptanceFixtures())->fingerprint();
        foreach (['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens', 'migrations'] as $table) {
            unset($business[$table]);
        }
        return ['business' => $business, 'session_users' => DB::table('sessions')->whereNotNull('user_id')
            ->selectRaw('user_id, COUNT(*) AS count')->groupBy('user_id')->orderBy('user_id')->get()->toArray()];
    }
}
