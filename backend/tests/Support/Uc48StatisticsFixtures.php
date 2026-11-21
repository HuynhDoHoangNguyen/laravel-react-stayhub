<?php

namespace Tests\Support;

use Illuminate\Foundation\Application;

/** Synthetic reporting snapshots. These do not exercise the production Booking workflow. */
final class Uc48StatisticsFixtures
{
    public static function base(Application $app, array $manifest): array
    {
        Uc48MySqlEnvironment::assertApplication($app, $manifest);
        $db = $app['db']->connection();
        $stamp = ['created_at' => '2026-11-19 02:00:00', 'updated_at' => '2026-11-19 02:00:00'];
        $actors = [];
        foreach (['admin' => ['ADMIN', 'ACTIVE'], 'host' => ['HOST', 'BLOCKED'], 'customer' => ['CUSTOMER', 'INACTIVE']] as $name => [$role, $status]) {
            $actors[$name] = $db->table('users')->insertGetId(['name' => 'UC48 '.$name, 'email' => $name.'@uc48.example.test',
                'password' => 'synthetic-not-a-login', 'role' => $role, 'status' => $status, ...$stamp]);
        }
        $property = $db->table('properties')->insertGetId(['host_id' => $actors['host'], 'name' => 'UC48 property', 'type' => 'HOTEL',
            'address' => 'Synthetic address', 'status' => 'BLOCKED', ...$stamp]);
        $type = $db->table('room_types')->insertGetId(['property_id' => $property, 'name' => 'UC48 type', ...$stamp]);
        $room = $db->table('rooms')->insertGetId(['property_id' => $property, 'room_type_id' => $type, 'room_number' => '001',
            'name' => 'UC48 room', 'capacity' => 2, 'price_per_night' => '999.99', 'status' => 'MAINTENANCE', ...$stamp]);
        return compact('actors', 'room', 'property');
    }

    public static function booking(Application $app, array $base, string $createdAt, string $status = 'PENDING', string $amount = '100.25'): int
    {
        return $app['db']->connection()->table('bookings')->insertGetId(['booking_code' => 'UC48-B-'.bin2hex(random_bytes(8)),
            'customer_id' => $base['actors']['customer'], 'room_id' => $base['room'], 'check_in_date' => '2026-12-20', 'check_out_date' => '2026-12-21',
            'guest_count' => 1, 'number_of_nights' => 1, 'price_per_night' => $amount, 'room_total' => $amount, 'status' => $status,
            'created_at' => $createdAt, 'updated_at' => $createdAt]);
    }

    public static function invoice(Application $app, int $booking, ?string $paidAt, string $amount, string $status = 'PAID'): int
    {
        $db = $app['db']->connection();
        $invoice = $db->table('invoices')->insertGetId(['invoice_code' => 'UC48-I-'.bin2hex(random_bytes(8)), 'booking_id' => $booking,
            'customer_name' => 'Synthetic customer', 'customer_email' => 'snapshot@example.test', 'customer_phone' => '0900000000',
            'property_name' => 'Snapshot property', 'room_name' => 'Snapshot room', 'subtotal' => $amount, 'discount_amount' => '0.00',
            'extra_fee' => '0.00', 'total_amount' => $amount, 'payment_status' => $status, 'paid_at' => $paidAt,
            'created_at' => '2026-12-01 00:00:00', 'updated_at' => '2026-12-01 00:00:00']);
        $db->table('invoice_items')->insert(['invoice_id' => $invoice, 'item_type' => 'ROOM', 'item_name' => 'Snapshot room',
            'quantity' => '1.00', 'unit_price' => $amount, 'amount' => $amount, 'created_at' => '2026-12-01 00:00:00', 'updated_at' => '2026-12-01 00:00:00']);
        return $invoice;
    }

    public static function contract(Application $app, array $manifest): array
    {
        $base = self::base($app, $manifest);
        $bookings = [];
        foreach ([['2026-09-30 17:00:00', 'COMPLETED', '100.25'], ['2026-10-31 16:59:59', 'CANCELLED', '500.00'],
            ['2026-10-31 17:00:00', 'PENDING', '200.50'], ['2026-11-19 05:00:00', 'CONFIRMED', '0.01'],
            ['2026-11-19 05:00:01', 'PENDING', '999.99'], ['2026-09-30 16:59:59', 'COMPLETED', '300.00'],
            ['2026-09-01 00:00:00', 'COMPLETED', '400.00']] as [$created, $status, $amount]) {
            $bookings[] = self::booking($app, $base, $created, $status, $amount);
        }
        $invoices = [];
        foreach ([[0, '2026-09-30 17:00:00', '100.25', 'PAID'], [2, '2026-10-31 17:00:00', '200.50', 'PAID'],
            [3, '2026-11-19 05:00:00', '0.01', 'PAID'], [4, '2026-11-19 05:00:01', '999.99', 'PAID'],
            [1, '2026-10-15 00:00:00', '500.00', 'REFUNDED'], [5, null, '300.00', 'PAID'], [6, null, '400.00', 'UNPAID']] as [$i, $paid, $amount, $status]) {
            $invoices[] = self::invoice($app, $bookings[$i], $paid, $amount, $status);
        }
        return $base + compact('bookings', 'invoices');
    }
}
