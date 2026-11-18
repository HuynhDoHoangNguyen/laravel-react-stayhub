<?php

namespace Tests\Support;

use Illuminate\Foundation\Application;

/** Synthetic read fixtures, not evidence of the production Booking workflow. */
final class Uc45InvoiceFixtures
{
    public static function create(Application $app, array $manifest): array
    {
        Uc45MySqlEnvironment::assertApplication($app, $manifest);
        $db = $app['db']->connection();
        return $db->transaction(function () use ($db): array {
            $stamp = ['created_at' => '2026-11-18 02:00:00', 'updated_at' => '2026-11-18 02:00:00'];
            $actors = [];
            foreach (['admin' => ['ADMIN', 'ACTIVE'], 'host_a' => ['HOST', 'ACTIVE'],
                'host_b' => ['HOST', 'ACTIVE'], 'customer' => ['CUSTOMER', 'ACTIVE'],
                'blocked' => ['ADMIN', 'BLOCKED'], 'inactive' => ['ADMIN', 'INACTIVE']] as $key => [$role, $status]) {
                $actors[$key] = $db->table('users')->insertGetId([
                    'name' => 'UC45 '.$key, 'email' => bin2hex(random_bytes(8)).'@uc45.example.test',
                    'password' => password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT, ['cost' => 4]),
                    'phone' => '0900000000', 'role' => $role, 'status' => $status, ...$stamp,
                ]);
            }
            $properties = $rooms = $services = [];
            foreach (['Cơ sở hiện tại %_ A', 'Cơ sở hiện tại B', 'Cơ sở hiện tại C', 'Cơ sở không hóa đơn'] as $i => $name) {
                $property = $db->table('properties')->insertGetId(['host_id' => $actors[$i < 2 || $i === 3 ? 'host_a' : 'host_b'],
                    'name' => $name, 'type' => 'HOTEL', 'address' => 'Địa chỉ tổng hợp',
                    'status' => $i === 1 ? 'INACTIVE' : 'ACTIVE', ...$stamp]);
                $properties[] = $property;
                $type = $db->table('room_types')->insertGetId(['property_id' => $property, 'name' => 'UC45 type', ...$stamp]);
                $rooms[] = $db->table('rooms')->insertGetId(['property_id' => $property, 'room_type_id' => $type,
                    'room_number' => '001', 'name' => 'Phòng hiện tại', 'capacity' => 2,
                    'price_per_night' => '999.99', 'status' => 'ACTIVE', ...$stamp]);
                $services[] = $db->table('services')->insertGetId(['property_id' => $property, 'name' => 'Dịch vụ hiện tại',
                    'unit' => 'Lần', 'price' => '999.99', 'status' => true, ...$stamp]);
            }
            $bookings = $invoices = [];
            for ($i = 0; $i < 12; $i++) {
                $propertyIndex = $i % 3;
                $booking = $db->table('bookings')->insertGetId(['booking_code' => 'UC45-B-'.bin2hex(random_bytes(8)),
                    'customer_id' => $actors['customer'], 'room_id' => $rooms[$propertyIndex],
                    'check_in_date' => '2026-11-10', 'check_out_date' => '2026-11-12', 'guest_count' => 2,
                    'number_of_nights' => 2, 'price_per_night' => '100.25', 'room_total' => '200.50',
                    'status' => 'COMPLETED', ...$stamp]);
                $bookings[] = $booking;
                $db->table('booking_services')->insert(['booking_id' => $booking, 'service_id' => $services[$propertyIndex],
                    'service_name' => 'Dịch vụ snapshot', 'unit' => 'Lần', 'quantity' => '1.00',
                    'unit_price' => '12.75', 'amount' => '12.75', ...$stamp]);
                $status = ['UNPAID', 'PAID', 'REFUNDED'][intdiv($i, 4)];
                $invoice = $db->table('invoices')->insertGetId(['invoice_code' => 'UC45-I-'.bin2hex(random_bytes(8)),
                    'booking_id' => $booking, 'customer_name' => 'Khách snapshot %_ '.$i,
                    'customer_email' => 'snapshot@uc45.example.test', 'customer_phone' => '0900000000',
                    'property_name' => 'Cơ sở snapshot '.$propertyIndex, 'room_name' => 'Phòng snapshot',
                    'subtotal' => '213.25', 'discount_amount' => '10.00', 'extra_fee' => '5.25', 'total_amount' => '208.50',
                    'payment_status' => $status, 'paid_at' => $status === 'PAID' ? '2026-11-18 03:00:00' : null, ...$stamp]);
                $invoices[] = $invoice;
                foreach ([['ROOM', 'Phòng snapshot', '2.00', '100.25', '200.50'],
                    ['SERVICE', 'Dịch vụ snapshot', '1.00', '12.75', '12.75']] as [$itemType, $name, $qty, $price, $amount]) {
                    $db->table('invoice_items')->insert(['invoice_id' => $invoice, 'item_type' => $itemType,
                        'item_name' => $name, 'description' => null, 'quantity' => $qty, 'unit_price' => $price,
                        'amount' => $amount, ...$stamp]);
                }
            }
            return compact('actors', 'properties', 'rooms', 'services', 'bookings', 'invoices');
        });
    }
}
