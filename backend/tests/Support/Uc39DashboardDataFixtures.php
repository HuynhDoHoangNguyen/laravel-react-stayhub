<?php

namespace Tests\Support;

use DateTimeImmutable;
use Illuminate\Database\Connection;

/** Direct synthetic QA records; never seeds development or simulates the Booking workflow. */
final class Uc39DashboardDataFixtures
{
    public const CUTOFF = '2026-11-21T05:00:00Z';

    public static function scenarios(): array
    {
        // Independent literal oracles: October + November, scoped warning, pending backlog.
        return [
            'Normal' => [1, '300.76', ['100.25', '200.51'], 1, 2],
            'Zero' => [2, '0.00', ['0.00', '0.00'], 0, 0],
            'BookingOnly' => [3, '0.00', ['0.00', '0.00'], 0, 2],
            'RevenueOnly' => [4, '300.75', ['100.25', '200.50'], 0, 0],
            'MissingDate' => [5, '0.00', ['0.00', '0.00'], 1, 0],
            'Large' => [6, '19999999999.98', ['0.00', '19999999999.98'], 0, 0],
            'Fractional' => [7, '0.49', ['0.00', '0.49'], 0, 0],
        ];
    }

    public static function create(Connection $db): void
    {
        $stamp = ['created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'];
        for ($id = 1; $id <= 14; $id++) {
            $db->table('users')->insert(['id' => $id, 'name' => 'Data QA '.$id, 'email' => 'data'.$id.'@example.test',
                'password' => 'synthetic-not-a-login', 'role' => $id === 11 ? 'CUSTOMER' : ($id === 12 ? 'ADMIN' : 'HOST'),
                'status' => $id === 13 ? 'BLOCKED' : ($id === 14 ? 'INACTIVE' : 'ACTIVE'), ...$stamp]);
        }
        foreach ([1, 2, 3, 4, 5, 6, 7, 8, 10] as $host) {
            foreach ([1, 2] as $slot) {
                $id = $host * 100 + $slot;
                $db->table('properties')->insert(['id' => $id, 'host_id' => $host, 'name' => 'Cơ sở '.$id,
                    'type' => 'HOTEL', 'address' => 'QA', 'status' => $slot === 1 ? 'ACTIVE' : 'BLOCKED', ...$stamp]);
                if ($host === 10) { continue; }
                $db->table('room_types')->insert(['id' => $id, 'property_id' => $id, 'name' => 'QA', ...$stamp]);
                $db->table('rooms')->insert(['id' => $id, 'property_id' => $id, 'room_type_id' => $id,
                    'room_number' => (string) $id, 'name' => 'Phòng '.$id, 'capacity' => 2, 'price_per_night' => '100.00',
                    'status' => $slot === 1 ? 'ACTIVE' : 'INACTIVE', ...$stamp]);
            }
        }
        $paid = static fn (string $amount, string $at = '2026-11-01T00:00:00Z'): array => ['PAID', $amount, $at];
        $ledgers = [
            1 => [$paid('100.25', '2026-09-30T17:00:00Z'), $paid('200.50'), $paid('0.01', self::CUTOFF),
                ['PAID', '300.00', null], ['REFUNDED', '500.00', '2026-10-01T00:00:00Z'],
                $paid('999.99', '2026-11-21T05:00:01Z'), ['UNPAID', '400.00', '2026-10-01T00:00:00Z']],
            3 => [null, null], 4 => [$paid('100.25', '2026-09-30T17:00:00Z'), $paid('200.50')],
            5 => [['PAID', '300.00', null]], 6 => [$paid('9999999999.99'), $paid('9999999999.99')],
            7 => [$paid('0.49')], 8 => [$paid('9999.99')],
        ];
        foreach ($ledgers as $host => $ledger) {
            foreach ($ledger as $offset => $invoice) {
                $id = $host * 1000 + $offset + 1;
                $db->table('bookings')->insert(['id' => $id, 'booking_code' => 'DATA-'.$id, 'room_id' => $host * 100 + 1,
                    'customer_id' => 11, 'check_in_date' => '2026-12-01', 'check_out_date' => '2026-12-02',
                    'guest_count' => 1, 'number_of_nights' => 1, 'price_per_night' => '100.00', 'room_total' => '100.00',
                    'status' => ($host === 1 && $offset < 2) || $host === 3 ? 'PENDING' : 'COMPLETED',
                    'note' => 'PRIVATE_DATA_SENTINEL', ...$stamp]);
                if ($invoice === null) { continue; }
                [$status, $amount, $at] = $invoice;
                $epoch = $at === null ? null : (new DateTimeImmutable($at))->getTimestamp();
                $db->table('invoices')->insert(['id' => $id, 'invoice_code' => 'DATA-INV-'.$id, 'booking_id' => $id,
                    'customer_name' => 'PRIVATE_DATA_SENTINEL', 'customer_email' => 'private@example.test', 'customer_phone' => '000',
                    'property_name' => 'Snapshot', 'room_name' => 'Snapshot', 'subtotal' => $amount, 'total_amount' => $amount,
                    'payment_status' => $status, 'paid_at' => $epoch === null ? null : $db->raw('FROM_UNIXTIME('.$epoch.')'), ...$stamp]);
            }
        }
        foreach ([1, 2] as $id) {
            $db->table('maintenances')->insert(['id' => $id, 'room_id' => 101, 'status' => 'IN_PROGRESS',
                'start_date' => '2025-01-01', 'end_date' => '2025-01-02', ...$stamp]);
        }
    }

    public static function fingerprint(Connection $db): string
    {
        $rows = [];
        foreach (['users', 'properties', 'room_types', 'rooms', 'maintenances', 'bookings', 'booking_services', 'invoices', 'invoice_items'] as $table) {
            $rows[$table] = $db->table($table)->orderBy('id')->get()->all();
        }
        return hash('sha256', serialize($rows));
    }
}
