<?php

namespace Tests\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Directly stored eligible/ineligible Booking fixtures, never a Booking workflow simulation. */
final class ReviewCreateAcceptanceFixtures
{
    public const STAMP = '2026-11-20 03:04:05';
    public const ISO = '2026-11-20T03:04:05.000000Z';
    public array $actors = [];
    public array $properties = [];
    public array $bookings = [];

    public function create(): self
    {
        foreach (['H_A' => 'HOST', 'H_B' => 'HOST', 'C_A' => 'CUSTOMER', 'C_B' => 'CUSTOMER',
            'ADMIN' => 'ADMIN', 'C_BLOCKED' => 'CUSTOMER', 'C_INACTIVE' => 'CUSTOMER'] as $label => $role) {
            $id = DB::table('users')->insertGetId(['name' => 'Create QA '.$label,
                'email' => strtolower($label).'@review-create.example.test', 'phone' => '0900000000',
                'password' => 'synthetic-not-a-login', 'role' => $role,
                'status' => match ($label) { 'C_BLOCKED' => 'BLOCKED', 'C_INACTIVE' => 'INACTIVE', default => 'ACTIVE' }]);
            $this->actors[$label] = User::findOrFail($id);
        }
        $rooms = [];
        foreach (['P_A' => 'H_A', 'P_B' => 'H_B'] as $label => $host) {
            $id = DB::table('properties')->insertGetId(['host_id' => $this->actors[$host]->id,
                'name' => 'Cơ sở '.$label, 'type' => 'HOTEL', 'address' => 'Synthetic QA', 'status' => 'ACTIVE']);
            $this->properties[$label] = $id;
            $type = DB::table('room_types')->insertGetId(['property_id' => $id, 'name' => 'QA type']);
            $rooms[$label] = DB::table('rooms')->insertGetId(['property_id' => $id, 'room_type_id' => $type,
                'room_number' => $label, 'name' => 'Phòng '.$label, 'capacity' => 2, 'price_per_night' => '100.25', 'status' => 'ACTIVE']);
        }
        $specs = ['SUCCESS_A' => ['C_A', 'P_A', 'COMPLETED'], 'SUCCESS_B' => ['C_B', 'P_B', 'COMPLETED'],
            'CROSS_HOST' => ['C_A', 'P_B', 'COMPLETED'], 'DUPLICATE_A' => ['C_A', 'P_A', 'COMPLETED'],
            'FOREIGN_B' => ['C_B', 'P_B', 'COMPLETED']];
        foreach (['PENDING', 'CONFIRMED', 'CHECKED_IN', 'CANCELLED', 'REJECTED'] as $status) {
            $specs[$status] = ['C_A', 'P_A', $status];
        }
        foreach ($specs as $label => [$customer, $property, $status]) {
            $code = 'CREATE-'.$label;
            $id = DB::table('bookings')->insertGetId(['booking_code' => $code, 'customer_id' => $this->actors[$customer]->id,
                'room_id' => $rooms[$property], 'check_in_date' => '2026-10-10', 'check_out_date' => '2026-10-12',
                'guest_count' => 1, 'number_of_nights' => 2, 'price_per_night' => '100.25', 'room_total' => '200.50', 'status' => $status]);
            $this->bookings[$label] = ['id' => $id, 'code' => $code, 'customer_id' => $this->actors[$customer]->id,
                'property_id' => $this->properties[$property], 'property_name' => 'Cơ sở '.$property, 'room_name' => 'Phòng '.$property];
        }
        foreach (['DUPLICATE_A' => 4, 'FOREIGN_B' => 3] as $label => $rating) {
            $b = $this->bookings[$label];
            DB::table('reviews')->insert(['booking_id' => $b['id'], 'customer_id' => $b['customer_id'],
                'property_id' => $b['property_id'], 'rating' => $rating, 'comment' => 'Existing '.$label,
                'created_at' => '2026-11-01 01:00:00', 'updated_at' => '2026-11-01 01:00:00']);
        }
        return $this;
    }

    public function expectedStored(string $label, int $id, int $rating, string $comment): array
    {
        $b = $this->bookings[$label];
        return ['id' => $id, 'booking_id' => $b['id'], 'customer_id' => $b['customer_id'],
            'property_id' => $b['property_id'], 'rating' => $rating, 'comment' => $comment,
            'created_at' => self::STAMP, 'updated_at' => self::STAMP];
    }

    public function expectedCustomer(string $label, int $id, int $rating, string $comment): array
    {
        $b = $this->bookings[$label];
        return ['id' => $id, 'booking_id' => $b['id'], 'booking_code' => $b['code'],
            'property_id' => $b['property_id'], 'property_name' => $b['property_name'], 'room_name' => $b['room_name'],
            'rating' => $rating, 'comment' => $comment, 'created_at' => self::ISO, 'updated_at' => self::ISO];
    }

    public function sourceFingerprint(): array
    {
        $hashes = (new ReviewReadAcceptanceFixtures())->fingerprint();
        unset($hashes['reviews']);
        return $hashes;
    }

    public function reviewRows(): array
    {
        return array_map(static fn ($row) => (array) $row, DB::table('reviews')->orderBy('id')->get()->all());
    }
}
