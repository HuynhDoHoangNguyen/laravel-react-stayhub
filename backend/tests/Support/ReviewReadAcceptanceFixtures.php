<?php

namespace Tests\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Stored read fixtures; expected membership, projections and statistics never call production queries. */
final class ReviewReadAcceptanceFixtures
{
    public array $actors = [];
    public array $properties = [];
    public array $rows = [];
    public array $reviewIds = [];

    public function create(): self
    {
        foreach (['H_A' => 'HOST', 'H_B' => 'HOST', 'H_EMPTY' => 'HOST', 'C_A' => 'CUSTOMER',
            'C_B' => 'CUSTOMER', 'ADMIN' => 'ADMIN', 'H_BLOCKED' => 'HOST', 'H_INACTIVE' => 'HOST'] as $label => $role) {
            $id = DB::table('users')->insertGetId(['name' => match ($label) {
                'C_A' => 'Nguyễn An', 'C_B' => 'Trần Bình', default => 'Review QA '.$label,
            }, 'email' => strtolower($label).'@review-read.example.test', 'phone' => '0900000000',
                'password' => 'synthetic-not-a-login', 'role' => $role,
                'status' => match ($label) { 'H_BLOCKED' => 'BLOCKED', 'H_INACTIVE' => 'INACTIVE', default => 'ACTIVE' }]);
            $this->actors[$label] = User::findOrFail($id);
        }
        $rooms = [];
        foreach (['P_A1' => 'H_A', 'P_A2' => 'H_A', 'P_A0' => 'H_A', 'P_B' => 'H_B'] as $label => $host) {
            $propertyId = DB::table('properties')->insertGetId(['host_id' => $this->actors[$host]->id,
                'name' => 'Cơ sở '.$label, 'type' => 'HOTEL', 'address' => 'Synthetic QA', 'status' => 'ACTIVE']);
            $this->properties[$label] = $propertyId;
            $typeId = DB::table('room_types')->insertGetId(['property_id' => $propertyId, 'name' => 'QA type']);
            $rooms[$label] = DB::table('rooms')->insertGetId(['property_id' => $propertyId, 'room_type_id' => $typeId,
                'room_number' => $label, 'name' => 'Phòng '.$label, 'capacity' => 2, 'price_per_night' => '100.25', 'status' => 'ACTIVE']);
        }
        $ratings = [5, 5, 5, 4, 4, 3, 2, 1, 5, 5, 5, 4, 1];
        foreach ($ratings as $index => $rating) {
            $number = $index + 1;
            $label = sprintf('R%02d', $number);
            $property = $number <= 8 ? 'P_A1' : 'P_A2';
            $customer = in_array($number, [4, 11], true) ? 'C_B' : 'C_A';
            $comment = match ($number) {
                2, 10 => 'QA-COMMENT phục vụ tốt', 3 => 'Phòng biển sạch sẽ', 5 => 'Có 100% hài lòng',
                6 => 'A_B yên tĩnh', 8 => '<script>alert("QA")</script> 0', default => 'Nội dung '.$label,
            };
            $stamp = $number === 1 ? null : sprintf('2026-11-%02d 01:00:00', $number === 12 ? 13 : $number);
            $this->insertReview($label, $property, $rooms[$property], $customer, $rating, $comment, $stamp);
        }
        foreach ([1, 1, 2] as $index => $rating) {
            $this->insertReview('B0'.($index + 1), 'P_B', $rooms['P_B'], 'C_B', $rating,
                'QA-COMMENT Phòng biển của Host khác', '2026-11-'.(20 + $index).' 01:00:00');
        }
        return $this;
    }

    private function insertReview(string $label, string $property, int $roomId, string $customer, int $rating, string $comment, ?string $stamp): void
    {
        $bookingId = DB::table('bookings')->insertGetId(['booking_code' => 'READ-'.$label,
            'customer_id' => $this->actors[$customer]->id, 'room_id' => $roomId, 'check_in_date' => '2026-10-10',
            'check_out_date' => '2026-10-12', 'guest_count' => 1, 'number_of_nights' => 2,
            'price_per_night' => '100.25', 'room_total' => '200.50', 'status' => 'COMPLETED']);
        $id = DB::table('reviews')->insertGetId(['booking_id' => $bookingId, 'customer_id' => $this->actors[$customer]->id,
            'property_id' => $this->properties[$property], 'rating' => $rating, 'comment' => $comment,
            'created_at' => $stamp, 'updated_at' => $stamp]);
        $this->reviewIds[$label] = $id;
        $this->rows[$label] = ['id' => $id, 'booking_id' => $bookingId, 'booking_code' => 'READ-'.$label,
            'property_id' => $this->properties[$property], 'property_name' => 'Cơ sở '.$property,
            'room_name' => 'Phòng '.$property, 'customer_name' => $customer === 'C_A' ? 'Nguyễn An' : 'Trần Bình',
            'rating' => $rating, 'comment' => $comment, 'created_at' => $stamp === null ? null : str_replace(' ', 'T', $stamp).'.000000Z'];
    }

    public static function order(string $scope): array
    {
        return match ($scope) {
            'H_A' => ['R13', 'R12', 'R11', 'R10', 'R09', 'R08', 'R07', 'R06', 'R05', 'R04', 'R03', 'R02', 'R01'],
            'P_A1' => ['R08', 'R07', 'R06', 'R05', 'R04', 'R03', 'R02', 'R01'],
            'P_A2' => ['R13', 'R12', 'R11', 'R10', 'R09'], 'H_B' => ['B03', 'B02', 'B01'],
            'P_A0', 'H_EMPTY' => [],
        };
    }

    public static function summary(string $scope): array
    {
        [$total, $average, $breakdown] = match ($scope) {
            'H_A' => [13, 3.8, [6, 3, 1, 1, 2]], 'P_A1' => [8, 3.6, [3, 2, 1, 1, 1]],
            'P_A2' => [5, 4, [3, 1, 0, 0, 1]], 'H_B' => [3, 1.3, [0, 0, 0, 1, 2]],
            'P_A0', 'H_EMPTY' => [0, null, [0, 0, 0, 0, 0]],
        };
        return ['total_reviews' => $total, 'average_rating' => $average,
            'rating_breakdown' => array_combine([5, 4, 3, 2, 1], $breakdown)];
    }

    /** All base-table contents, including auth/invoice/review tables; no assumed PK or exposed row data. */
    public function fingerprint(): array
    {
        $hashes = [];
        foreach (DB::select('SELECT TABLE_NAME AS name FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = ? ORDER BY TABLE_NAME', ['BASE TABLE']) as $table) {
            $rows = array_map(static fn ($row) => json_encode($row, JSON_THROW_ON_ERROR), DB::table($table->name)->get()->all());
            sort($rows, SORT_STRING);
            $hashes[$table->name] = hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
        }
        return $hashes;
    }
}
