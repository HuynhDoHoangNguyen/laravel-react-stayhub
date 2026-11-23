<?php

namespace Tests\Support;

use Illuminate\Foundation\Application;

/** Synthetic reporting fixtures; not production Booking workflow evidence. */
final class Uc47DashboardFixtures
{
    public static function create(Application $app, array $manifest): array
    {
        Uc47MySqlEnvironment::assertApplication($app, $manifest);
        $db = $app['db']->connection();
        $stamp = ['created_at' => '2026-11-18 02:00:00', 'updated_at' => '2026-11-18 02:00:00'];
        $actors = [];
        foreach (['admin' => ['ADMIN', 'ACTIVE'], 'host_a' => ['HOST', 'ACTIVE'],
            'host_b' => ['HOST', 'BLOCKED'], 'host_no_property' => ['HOST', 'INACTIVE'],
            'customer' => ['CUSTOMER', 'ACTIVE'], 'blocked' => ['ADMIN', 'BLOCKED'],
            'inactive' => ['ADMIN', 'INACTIVE']] as $key => [$role, $status]) {
            $actors[$key] = $db->table('users')->insertGetId(['name' => 'UC47 '.$key,
                'email' => $key.'@uc47.example.test', 'password' => 'synthetic-not-a-login',
                'role' => $role, 'status' => $status, ...$stamp]);
        }
        $rooms = [];
        foreach (['ACTIVE', 'INACTIVE', 'BLOCKED', 'PENDING'] as $i => $status) {
            $property = $db->table('properties')->insertGetId(['host_id' => $actors[$i % 2 ? 'host_b' : 'host_a'],
                'name' => 'UC47 property '.$i, 'type' => 'HOTEL', 'address' => 'Synthetic address', 'status' => $status, ...$stamp]);
            $type = $db->table('room_types')->insertGetId(['property_id' => $property, 'name' => 'UC47 type', ...$stamp]);
            $rooms[] = $db->table('rooms')->insertGetId(['property_id' => $property, 'room_type_id' => $type,
                'room_number' => '001', 'name' => 'UC47 room', 'capacity' => 2, 'price_per_night' => '999.99',
                'status' => ['ACTIVE', 'MAINTENANCE', 'INACTIVE', 'ACTIVE'][$i], ...$stamp]);
        }
        $invoices = [];
        foreach (['PENDING', 'CONFIRMED', 'CHECKED_IN', 'COMPLETED', 'CANCELLED', 'REJECTED'] as $i => $status) {
            $booking = $db->table('bookings')->insertGetId(['booking_code' => 'UC47-B-'.$i,
                'customer_id' => $actors['customer'], 'room_id' => $rooms[$i % 4],
                'check_in_date' => '2026-11-10', 'check_out_date' => '2026-11-12', 'guest_count' => 2,
                'number_of_nights' => 2, 'price_per_night' => '100.25', 'room_total' => '200.50', 'status' => $status, ...$stamp]);
            // Two valid PAID exceed a single invoice DECIMAL(12,2) capacity in aggregate.
            $payment = ['PAID', 'PAID', 'UNPAID', 'REFUNDED', 'PAID', 'PAID'][$i];
            $invoice = $db->table('invoices')->insertGetId(['invoice_code' => 'UC47-I-'.$i, 'booking_id' => $booking,
                'customer_name' => 'Synthetic customer', 'customer_email' => 'snapshot@example.test',
                'customer_phone' => '0900000000', 'property_name' => 'Snapshot property', 'room_name' => 'Snapshot room',
                'subtotal' => '200.50', 'discount_amount' => '10.00', 'extra_fee' => '5.25',
                'total_amount' => $i < 2 ? '9999999999.99' : '123.45', 'payment_status' => $payment,
                'paid_at' => $i < 2 ? '2026-11-18 03:00:00' : null, ...$stamp]);
            $invoices[] = $invoice;
            foreach (['ROOM', 'SERVICE'] as $kind) {
                $db->table('invoice_items')->insert(['invoice_id' => $invoice, 'item_type' => $kind,
                    'item_name' => 'Synthetic item', 'quantity' => '1.00', 'unit_price' => '100.25', 'amount' => '100.25', ...$stamp]);
            }
        }
        // Multiple BookingService rows must not multiply the invoice aggregate.
        $propertyId = $db->table('rooms')->where('id', $rooms[0])->value('property_id');
        $service = $db->table('services')->insertGetId(['property_id' => $propertyId, 'name' => 'UC47 service',
            'unit' => 'Lần', 'price' => '12.75', 'status' => true, ...$stamp]);
        foreach (['UC47-B-0', 'UC47-B-0', 'UC47-B-1'] as $code) {
            $db->table('booking_services')->insert(['booking_id' => $db->table('bookings')->where('booking_code', $code)->value('id'),
                'service_id' => $service, 'service_name' => 'Snapshot service', 'unit' => 'Lần',
                'quantity' => '1.00', 'unit_price' => '12.75', 'amount' => '12.75', ...$stamp]);
        }
        return compact('actors', 'invoices');
    }

    /** Append a synthetic invoice at an exact UTC moment to an existing fixture. */
    public static function addInvoice(Application $app, array $manifest, ?string $paidAt, string $amount, string $payment = 'PAID'): int
    {
        Uc47MySqlEnvironment::assertApplication($app, $manifest);
        $db = $app['db']->connection();
        $booking = (array) $db->table('bookings')->orderBy('id')->first();
        unset($booking['id']);
        $booking['booking_code'] = 'UC47-TIME-'.bin2hex(random_bytes(8));
        $bookingId = $db->table('bookings')->insertGetId($booking);
        $invoice = (array) $db->table('invoices')->orderBy('id')->first();
        unset($invoice['id']);
        $invoice['booking_id'] = $bookingId;
        $invoice['invoice_code'] = 'UC47-TIME-'.bin2hex(random_bytes(8));
        $invoice['paid_at'] = $paidAt;
        $invoice['payment_status'] = $payment;
        $invoice['total_amount'] = $amount;
        return $db->table('invoices')->insertGetId($invoice);
    }

}
