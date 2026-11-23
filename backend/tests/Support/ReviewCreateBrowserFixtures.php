<?php

namespace Tests\Support;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/** Owned stored Booking inputs; real Review mutations are audited against their request journal. */
final class ReviewCreateBrowserFixtures
{
    public static function assertScenario(array $manifest, mixed $optIn): void
    {
        if (($manifest['scenario'] ?? null) !== 'ReviewCreate' || $optIn !== '1') {
            throw new RuntimeException('Review create browser requires its own scenario and explicit opt-in.');
        }
    }

    public static function kinds(): array
    {
        return ['MAIN', 'MIN', 'MAX', 'CROSS_HOST', 'DUPLICATE', 'FOREIGN', 'DOUBLE', 'UNKNOWN', 'HTML',
            'PENDING', 'CONFIRMED', 'CHECKED_IN', 'CANCELLED', 'REJECTED'];
    }

    public static function payload(string $kind): array
    {
        if (!in_array($kind, self::kinds(), true)) throw new RuntimeException('Unknown Review browser recipe.');
        return ['rating' => $kind === 'MIN' ? 1 : 5, 'comment' => match ($kind) {
            'MIN' => 'abcde', 'MAX' => str_repeat('Đ', 1000),
            'HTML' => '<script>alert("QA")</script> Phòng sạch.',
            default => 'Phòng sạch sẽ - '.$kind,
        }];
    }

    public static function create(Application $app, array $manifest, string $password): array
    {
        self::assertScenario($manifest, getenv('STAYHUB_TV3_AC_REVIEW_CREATE_BROWSER_OPT_IN'));
        if (strlen($password) < 24 || DB::transactionLevel() !== 0) throw new RuntimeException('Strong temporary password and committed fixture required.');
        return DB::transaction(function () use ($password): array {
            $base = (new ReviewCreateAcceptanceFixtures())->create();
            $actors = [];
            foreach ($base->actors as $label => $actor) {
                DB::table('users')->where('id', $actor->id)->update(['password' => Hash::make($password)]);
                $actors[$label] = ['id' => $actor->id, 'email' => $actor->email,
                    'role' => $actor->role->value, 'status' => $actor->status->value];
            }
            $bookings = [];
            foreach (['P', 'UI'] as $family) foreach (self::kinds() as $kind) {
                $property = $kind === 'CROSS_HOST' || $kind === 'FOREIGN' ? 'P_B' : 'P_A';
                $customer = $kind === 'FOREIGN' ? 'C_B' : 'C_A';
                $status = in_array($kind, ['PENDING', 'CONFIRMED', 'CHECKED_IN', 'CANCELLED', 'REJECTED'], true) ? $kind : 'COMPLETED';
                $room = DB::table('rooms')->where('property_id', $base->properties[$property])->first();
                $code = 'RCUI-'.$family.'-'.$kind;
                $id = DB::table('bookings')->insertGetId(['booking_code' => $code, 'customer_id' => $actors[$customer]['id'],
                    'room_id' => $room->id, 'check_in_date' => '2026-10-10', 'check_out_date' => '2026-10-12',
                    'guest_count' => 1, 'number_of_nights' => 2, 'price_per_night' => '100.25', 'room_total' => '200.50', 'status' => $status]);
                $spec = ['id' => $id, 'booking_code' => $code, 'customer_id' => $actors[$customer]['id'],
                    'property_id' => $base->properties[$property], 'property_name' => 'Cơ sở '.$property,
                    'room_name' => $room->name, 'status' => $status, 'kind' => $kind, 'family' => $family,
                    'mutable' => $status === 'COMPLETED' && !in_array($kind, ['DUPLICATE', 'FOREIGN'], true),
                    'payload' => self::payload($kind)];
                if ($kind === 'DUPLICATE') DB::table('reviews')->insert(['booking_id' => $id,
                    'customer_id' => $spec['customer_id'], 'property_id' => $spec['property_id'], 'rating' => 4,
                    'comment' => 'Existing '.$family.' duplicate', 'created_at' => '2026-11-01 01:00:00', 'updated_at' => '2026-11-01 01:00:00']);
                $bookings[$family.'-'.$kind] = $spec;
            }
            return ['actors' => $actors, 'bookings' => $bookings, 'missing_id' => (int) DB::table('bookings')->max('id') + 10000,
                'entry_scope' => 'Synthetic QA parent; unchanged production ReviewCreateModal/service/API/mapper.'];
        });
    }

    public static function allowsRequest(string $method, string $path, array $bookings, int $missingId, string $family): bool
    {
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) return true;
        if ($method !== 'POST') return false;
        if (in_array($path, ['/login', '/logout'], true)) return true;
        if (!preg_match('~\A/api/customer/bookings/([1-9][0-9]*)/review\z~', $path, $match)) return false;
        $id = (int) $match[1];
        if ($id === $missingId) return true;
        foreach ($bookings as $spec) if ($spec['id'] === $id && $spec['family'] === $family) return true;
        return false;
    }

    public static function snapshot(array $attempts = []): array
    {
        $source = (new ReviewReadAcceptanceFixtures())->fingerprint();
        unset($source['reviews'], $source['sessions']);
        return ['source' => $source, 'reviews' => DB::table('reviews')->orderBy('id')->get()->map(static fn ($r) => (array) $r)->all(),
            'attempts' => $attempts, 'session_users' => DB::table('sessions')->whereNotNull('user_id')
                ->selectRaw('user_id, COUNT(*) AS count')->groupBy('user_id')->orderBy('user_id')->get()->map(static fn ($r) => (array) $r)->all()];
    }

    private static function same(mixed $a, mixed $b): bool
    {
        return InvoiceReadBrowserFixtures::canonical($a) === InvoiceReadBrowserFixtures::canonical($b);
    }

    public static function verifyDelta(array $before, array $after, array $bookings, array $allowedIds): array
    {
        if (!self::same($before['attempts'], array_slice($after['attempts'], 0, count($before['attempts'])))) throw new RuntimeException('Existing request journal changed.');
        if (!self::same($before['source'], $after['source'])) throw new RuntimeException('Review QA source records changed.');
        $old = array_column($before['reviews'], null, 'id'); $rows = array_column($after['reviews'], null, 'id');
        if (count($rows) !== count($after['reviews']) || count(array_unique(array_column($after['reviews'], 'booking_id'))) !== count($rows)) {
            throw new RuntimeException('Duplicate Review identifier or Booking.');
        }
        foreach ($old as $id => $row) if (!isset($rows[$id]) || !self::same($row, $rows[$id])) throw new RuntimeException('Existing Review changed or disappeared.');
        $specs = array_column($bookings, null, 'id'); $created = [];
        foreach (array_diff_key($rows, $old) as $id => $row) {
            $spec = $specs[$row['booking_id']] ?? null;
            if (!$spec || !$spec['mutable'] || !in_array($row['booking_id'], $allowedIds, true)) throw new RuntimeException('Review outside allowed mutation set.');
            $attempts = array_values(array_filter($after['attempts'], static fn ($a) => $a['status'] === 201 && $a['review_id'] === $id && $a['booking_id'] === $row['booking_id']));
            if (count($attempts) !== 1) throw new RuntimeException('Review requires exactly one successful production request journal.');
            $a = $attempts[0];
            if ($a['actor_id'] !== $spec['customer_id'] || !is_int($a['rating']) || $a['rating'] < 1 || $a['rating'] > 5
                || !is_string($a['comment']) || mb_strlen($a['comment']) < 5 || mb_strlen($a['comment']) > 1000) throw new RuntimeException('Invalid successful request facts.');
            $expected = ['id' => $id, 'booking_id' => $spec['id'], 'customer_id' => $spec['customer_id'],
                'property_id' => $spec['property_id'], 'rating' => $a['rating'], 'comment' => $a['comment'],
                'created_at' => $row['created_at'], 'updated_at' => $row['updated_at']];
            if (!is_int($id) || $id < 1 || !is_string($row['created_at']) || !preg_match('/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\z/', $row['created_at'])
                || $row['created_at'] !== $row['updated_at'] || !self::same($expected, $row)) throw new RuntimeException('Persisted Review differs from request and fixture oracle.');
            $created[$spec['id']] = $id;
        }
        foreach ($after['attempts'] as $a) if ($a['status'] === 201 && !isset($rows[$a['review_id']])) throw new RuntimeException('Successful request has no persisted Review.');
        foreach (array_slice($after['attempts'], count($before['attempts'])) as $a) {
            if ($a['status'] === 201 && ($created[$a['booking_id']] ?? null) !== $a['review_id']) throw new RuntimeException('New success must match a newly persisted Review.');
        }
        return ['matched' => true, 'new_reviews' => count($created), 'created' => $created, 'source_unchanged' => true];
    }

    public static function verifyResponse(array $response, array $fixture, array $snapshot, int $bookingId, ?array $literalPayload = null): void
    {
        $spec = array_column($fixture['bookings'], null, 'id')[$bookingId] ?? throw new RuntimeException('Unknown response Booking.');
        $rows = array_values(array_filter($snapshot['reviews'], static fn ($r) => $r['booking_id'] === $bookingId));
        if (count($rows) !== 1) throw new RuntimeException('Expected one persisted Review.');
        $row = $rows[0];
        $a = array_values(array_filter($snapshot['attempts'], static fn ($a) => $a['status'] === 201 && $a['review_id'] === $row['id']));
        if (count($a) !== 1) throw new RuntimeException('Missing successful request facts.');
        if ($literalPayload !== null && ($a[0]['rating'] !== $literalPayload['rating'] || $a[0]['comment'] !== $literalPayload['comment'])) throw new RuntimeException('Request differs from independent literal payload.');
        $iso = static fn ($s) => str_replace(' ', 'T', $s).'.000000Z';
        $expected = ['data' => ['id' => $row['id'], 'booking_id' => $spec['id'], 'booking_code' => $spec['booking_code'],
            'property_id' => $spec['property_id'], 'property_name' => $spec['property_name'], 'room_name' => $spec['room_name'],
            'rating' => $a[0]['rating'], 'comment' => $a[0]['comment'], 'created_at' => $iso($row['created_at']), 'updated_at' => $iso($row['updated_at'])]];
        if (!self::same($expected, $response)) throw new RuntimeException('Customer response differs from independent oracle.');
    }
}
