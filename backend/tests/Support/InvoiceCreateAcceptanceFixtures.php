<?php

namespace Tests\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Valid stored Booking inputs and literal financial oracles, independent of production calculation/mapping. */
final class InvoiceCreateAcceptanceFixtures
{
    public array $actors = [];
    public int $bookingId;
    public int $roomId;
    public int $propertyId;
    public array $serviceIds = [];
    public array $bookingServiceIds = [];
    public string $total;
    public array $items;

    public function create(string $scenario = 'services'): self
    {
        if (!in_array($scenario, ['services', 'room', 'zero', 'maximum', 'overflow'], true)) {
            throw new InvalidArgumentException('Unknown create fixture');
        }
        foreach (['H_A' => 'HOST', 'H_B' => 'HOST', 'C_A' => 'CUSTOMER', 'C_B' => 'CUSTOMER', 'ADMIN' => 'ADMIN'] as $label => $role) {
            $id = DB::table('users')->insertGetId(['name' => 'Snapshot '.$label,
                'email' => strtolower($label).'@invoice-create.example.test', 'phone' => '0900000000',
                'password' => 'synthetic-not-a-login', 'role' => $role, 'status' => 'ACTIVE']);
            $this->actors[$label] = User::findOrFail($id);
        }
        foreach (['H_A', 'H_B'] as $label) {
            $property = DB::table('properties')->insertGetId(['host_id' => $this->actors[$label]->id,
                'name' => 'Current property '.$label, 'type' => 'HOTEL', 'address' => 'Synthetic fixture', 'status' => 'ACTIVE']);
            $type = DB::table('room_types')->insertGetId(['property_id' => $property, 'name' => 'QA type']);
            $room = DB::table('rooms')->insertGetId(['property_id' => $property, 'room_type_id' => $type,
                'room_number' => $label, 'name' => 'Current room '.$label, 'capacity' => 2,
                'price_per_night' => '900.00', 'status' => 'ACTIVE']);
            if ($label === 'H_A') { $this->propertyId = $property; $this->roomId = $room; }
        }
        $nights = in_array($scenario, ['maximum', 'overflow'], true) ? 1 : 2;
        $price = match ($scenario) { 'zero' => '0.00', 'maximum', 'overflow' => '9999999999.99', default => '100.25' };
        $roomTotal = match ($scenario) { 'zero' => '0.00', 'maximum', 'overflow' => '9999999999.99', default => '200.50' };
        $this->total = match ($scenario) { 'services' => '225.78', 'room' => '200.50', 'zero' => '0.00',
            'maximum' => '9999999999.99', 'overflow' => '10000000000.00' };
        $this->bookingId = DB::table('bookings')->insertGetId(['booking_code' => 'CREATE-'.$scenario,
            'customer_id' => $this->actors['C_A']->id, 'room_id' => $this->roomId,
            'check_in_date' => '2026-11-10', 'check_out_date' => $nights === 1 ? '2026-11-11' : '2026-11-12',
            'guest_count' => 1, 'number_of_nights' => $nights, 'price_per_night' => $price,
            'room_total' => $roomTotal, 'status' => 'COMPLETED']);
        $this->items = [['item_type' => 'ROOM', 'item_name' => 'Current room H_A',
            'description' => 'Tiền phòng ('.$nights.' đêm × '.$price.')', 'quantity' => $nights.'.00',
            'unit_price' => $price, 'amount' => $roomTotal]];
        $services = match ($scenario) {
            'services' => [['1.00', '12.75', '12.75'], ['1.25', '10.02', '12.53']],
            'overflow' => [['1.00', '0.01', '0.01']], default => [],
        };
        foreach ($services as $i => [$quantity, $unitPrice, $amount]) {
            $service = DB::table('services')->insertGetId(['property_id' => $this->propertyId,
                'name' => 'Changed catalog '.$i, 'unit' => 'Lần', 'price' => '888.88', 'status' => true]);
            $this->serviceIds[] = $service;
            $name = 'Booked service '.$i;
            $this->bookingServiceIds[] = DB::table('booking_services')->insertGetId(['booking_id' => $this->bookingId,
                'service_id' => $service, 'service_name' => $name, 'unit' => 'Lần', 'quantity' => $quantity,
                'unit_price' => $unitPrice, 'amount' => $amount]);
            $this->items[] = ['item_type' => 'SERVICE', 'item_name' => $name, 'description' => null,
                'quantity' => $quantity, 'unit_price' => $unitPrice, 'amount' => $amount];
        }
        return $this;
    }

    public function url(): string
    {
        return '/api/host/bookings/'.$this->bookingId.'/invoice';
    }

    /** Stable hashes only; include output tables when proving a rejected request wrote nothing. */
    public function fingerprint(bool $includeOutputs = false): array
    {
        $hashes = [];
        foreach (DB::select('SELECT TABLE_NAME AS name FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = ? ORDER BY TABLE_NAME', ['BASE TABLE']) as $table) {
            if (!$includeOutputs && in_array($table->name, ['invoices', 'invoice_items'], true)) continue;
            $rows = array_map(static fn ($row) => json_encode($row, JSON_THROW_ON_ERROR), DB::table($table->name)->get()->all());
            sort($rows, SORT_STRING);
            $hashes[$table->name] = hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
        }
        return $hashes;
    }
}
