<?php

namespace Tests\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Synthetic stored read fixtures. Never calls Invoice creation, pricing or response mapping. */
final class InvoiceReadAcceptanceFixtures
{
    public const HEADER_KEYS = ['id', 'invoice_code', 'booking_id', 'customer_name', 'customer_email',
        'customer_phone', 'property_name', 'room_name', 'subtotal', 'discount_amount', 'extra_fee',
        'total_amount', 'payment_status', 'paid_at', 'created_at'];

    public array $actors = [];
    public array $headers = [];
    public array $details = [];
    public array $properties = [];
    public array $rooms = [];

    public function create(): self
    {
        foreach (['H_A' => 'HOST', 'H_B' => 'HOST', 'H_EMPTY' => 'HOST',
            'C_A' => 'CUSTOMER', 'C_B' => 'CUSTOMER', 'C_EMPTY' => 'CUSTOMER', 'ADMIN' => 'ADMIN'] as $label => $role) {
            $id = DB::table('users')->insertGetId(['name' => 'Live '.$label,
                'email' => strtolower($label).'@invoice-read.example.test', 'phone' => '0900000000',
                'password' => 'synthetic-not-a-login', 'role' => $role, 'status' => 'ACTIVE']);
            $this->actors[$label] = User::findOrFail($id);
        }
        foreach (['P_A1' => 'H_A', 'P_A2' => 'H_A', 'P_B' => 'H_B'] as $label => $owner) {
            $property = DB::table('properties')->insertGetId(['host_id' => $this->actors[$owner]->id,
                'name' => 'Current catalog '.$label, 'type' => 'HOTEL', 'address' => 'Synthetic QA address', 'status' => 'ACTIVE']);
            $this->properties[$label] = $property;
            $type = DB::table('room_types')->insertGetId(['property_id' => $property, 'name' => 'QA type']);
            $this->rooms[$label] = DB::table('rooms')->insertGetId(['property_id' => $property,
                'room_type_id' => $type, 'room_number' => $label, 'name' => 'Current room '.$label,
                'capacity' => 2, 'price_per_night' => '777.77', 'status' => 'ACTIVE']);
        }

        // Explicit expected membership/order is declared in the test, not obtained from a query under test.
        $specs = [
            'I01' => ['P_A1', 'C_A', 'UNPAID', '213.25', '10.00', '5.25', '208.50', '2026-11-01 01:00:00', null],
            'I02' => ['P_A1', 'C_B', 'PAID', '100.25', '0.00', '0.00', '100.25', '2026-11-02 01:00:00', '2026-11-03 01:00:00'],
            'I03' => ['P_B', 'C_A', 'REFUNDED', '300.75', '0.00', '0.00', '300.75', '2026-11-03 01:00:00', '2026-11-04 01:00:00'],
            'I04' => ['P_A2', 'C_A', 'PAID', '0.00', '0.00', '0.00', '0.00', '2026-11-04 01:00:00', '2026-11-05 01:00:00'],
            'I05' => ['P_B', 'C_B', 'UNPAID', '9999999999.99', '0.00', '0.00', '9999999999.99', '2026-11-05 01:00:00', null],
            'I06' => ['P_A2', 'C_B', 'REFUNDED', '25.00', '0.00', '0.00', '25.00', '2026-11-02 01:00:00', null],
        ];
        foreach ($specs as $label => [$property, $customer, $status, $subtotal, $discount, $fee, $total, $created, $paid]) {
            $booking = DB::table('bookings')->insertGetId(['booking_code' => 'READ-'.$label,
                'customer_id' => $this->actors[$customer]->id, 'room_id' => $this->rooms[$property],
                'check_in_date' => '2026-10-20', 'check_out_date' => '2026-10-22', 'guest_count' => 2,
                'number_of_nights' => 2, 'price_per_night' => '100.25', 'room_total' => '200.50', 'status' => 'COMPLETED']);
            $common = in_array($label, ['I01', 'I03'], true) ? 'COMMON-' : '';
            $header = ['invoice_code' => 'INV-READ-'.$common.$label, 'booking_id' => $booking,
                'customer_name' => $label === 'I01' ? 'Khách snapshot Nguyễn Alpha' : 'Khách snapshot '.$customer,
                'customer_email' => 'snapshot-'.$label.'@example.test', 'customer_phone' => '0912345678',
                'property_name' => 'Cơ sở snapshot '.$property, 'room_name' => 'Phòng snapshot '.$property,
                'subtotal' => $subtotal, 'discount_amount' => $discount, 'extra_fee' => $fee, 'total_amount' => $total,
                'payment_status' => $status, 'paid_at' => $paid, 'created_at' => $created];
            $id = DB::table('invoices')->insertGetId([...$header, 'updated_at' => $created]);
            // ISO oracle uses fixed UTC literals, independently of Eloquent/Carbon/production DTO serialization.
            $this->headers[$label] = ['id' => $id, ...$header,
                'paid_at' => $paid === null ? null : str_replace(' ', 'T', $paid).'.000000Z',
                'created_at' => str_replace(' ', 'T', $created).'.000000Z'];
            $items = $label === 'I04' ? [] : [[
                'item_type' => 'ROOM', 'item_name' => 'Room snapshot '.$label,
                'description' => $label === 'I01' ? 'Hai đêm theo snapshot' : null,
                'quantity' => $label === 'I01' ? '2.00' : '1.00',
                'unit_price' => $label === 'I01' ? '100.25' : $subtotal,
                'amount' => $label === 'I01' ? '200.50' : $subtotal,
            ]];
            if ($label === 'I01') {
                $items[] = ['item_type' => 'SERVICE', 'item_name' => 'Dịch vụ snapshot', 'description' => null,
                    'quantity' => '1.00', 'unit_price' => '12.75', 'amount' => '12.75'];
                $service = DB::table('services')->insertGetId(['property_id' => $this->properties[$property],
                    'name' => 'Current catalog service', 'unit' => 'Lần', 'price' => '888.88', 'status' => true]);
                DB::table('booking_services')->insert(['booking_id' => $booking, 'service_id' => $service,
                    'service_name' => 'Dịch vụ snapshot', 'unit' => 'Lần', 'quantity' => '1.00',
                    'unit_price' => '12.75', 'amount' => '12.75']);
            }
            $expectedItems = [];
            foreach ($items as $item) {
                $itemId = DB::table('invoice_items')->insertGetId(['invoice_id' => $id, ...$item]);
                $expectedItems[] = ['id' => $itemId, 'invoice_id' => $id, ...$item];
            }
            $this->details[$label] = [...$this->headers[$label], 'items' => $expectedItems];
        }
        return $this;
    }

    public function id(string $label): int
    {
        return $this->headers[$label]['id'];
    }

    /** All tables in the guarded schema, stable record ordering; only hashes leave memory. */
    public function fingerprint(): array
    {
        $tables = DB::select('SELECT TABLE_NAME AS name FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = ? ORDER BY TABLE_NAME', ['BASE TABLE']);
        $hashes = [];
        foreach ($tables as $table) {
            // Cache/password-reset tables do not all have an id; sort canonical row strings instead.
            $rows = array_map(static fn ($row) => json_encode($row, JSON_THROW_ON_ERROR),
                DB::table($table->name)->get()->all());
            sort($rows, SORT_STRING);
            $hashes[$table->name] = hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
        }
        return $hashes;
    }
}
