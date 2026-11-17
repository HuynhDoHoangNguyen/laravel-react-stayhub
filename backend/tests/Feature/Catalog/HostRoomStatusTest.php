<?php

namespace Tests\Feature\Catalog;

use App\DTOs\Catalog\Requests\ChangeHostRoomStatusRequest;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Enums\RoomStatus;
use App\Services\Catalog\HostRoomService;
use App\Validators\Catalog\CatalogValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;

final class HostRoomStatusTest extends TestCase
{
    use RefreshDatabase;

    public function createApplication()
    {
        foreach (['APP_ENV' => 'testing', 'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'] as $key => $expected) {
            if (($_ENV[$key] ?? getenv($key)) !== $expected) {
                throw new RuntimeException('Host Room tests require isolated testing configuration.');
            }
        }
        if (is_file(dirname(__DIR__, 3).'/bootstrap/cache/config.php')) {
            throw new RuntimeException('Host Room tests refuse cached configuration.');
        }
        $driver = $_ENV['DB_CONNECTION'] ?? getenv('DB_CONNECTION');
        $database = $_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE');
        $sqlite = $driver === 'sqlite' && $database === ':memory:';
        $mysql = $driver === 'mysql' && is_string($database)
            && $database === getenv('STAYHUB_ROOM_STATUS_TEST_DATABASE')
            && preg_match('/\Astayhub_room05b_test_[a-f0-9]{32}\z/', $database) === 1;
        if (!$sqlite && !$mysql) {
            throw new RuntimeException('Host Room tests require an explicitly isolated database.');
        }
        $app = parent::createApplication();
        $config = $app['config'];
        $connection = $config->get("database.connections.{$driver}");
        if ($config->get('database.default') !== $driver || ($connection['database'] ?? null) !== $database
            || !empty($connection['url']) || $config->get('cache.default') !== 'array'
            || $config->get('session.driver') !== 'array'
            || ($mysql && (($connection['host'] ?? null) !== '127.0.0.1' || !empty($connection['unix_socket'])))) {
            throw new RuntimeException('Effective Host Room database configuration is unsafe.');
        }
        if ($mysql && $app['db']->connection()->selectOne('SELECT DATABASE() AS selected_database')->selected_database !== $database) {
            throw new RuntimeException('Selected database differs from the owned test schema.');
        }

        return $app;
    }


    private function fixture(string $roomStatus = 'ACTIVE', string $propertyStatus = 'ACTIVE'): array
    {
        $host = User::factory()->create(['role' => 'HOST', 'status' => 'ACTIVE']);
        $property = Property::create(['host_id' => $host->id, 'name' => 'Update property',
            'type' => 'HOTEL', 'address' => 'Fixture address', 'status' => $propertyStatus]);
        $type = $property->roomTypes()->create(['name' => 'Suite']);
        $room = $property->rooms()->create(['room_type_id' => $type->id, 'room_number' => '001',
            'name' => 'Before update', 'description' => 'Before', 'capacity' => 4,
            'price_per_night' => '100.00', 'status' => $roomStatus]);

        return [$host, $property, $type, $room->fresh()];
    }

    private function endpoint(int|string $id): string
    {
        return '/api/host/rooms/'.$id.'/status';
    }


    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-15T23:30:00Z'));
    }

    private function booking(Room $room, string $status, string $start = '2026-11-16', string $end = '2026-11-17'): void
    {
        $customer = User::factory()->create(['role' => 'CUSTOMER']);
        DB::table('bookings')->insert(['booking_code' => 'STATUS-'.bin2hex(random_bytes(8)), 'customer_id' => $customer->id,
            'room_id' => $room->id, 'check_in_date' => $start, 'check_out_date' => $end, 'guest_count' => 2,
            'number_of_nights' => 1, 'price_per_night' => '100.25', 'room_total' => '100.25', 'status' => $status]);
    }

    private function maintenance(Room $room, string $status, string $start = '2026-11-15', string $end = '2026-11-16'): void
    {
        DB::table('maintenances')->insert(['room_id' => $room->id, 'status' => $status,
            'start_date' => $start, 'end_date' => $end, 'reason' => 'Fixture']);
    }

    public static function transitions(): array
    {
        $oracle = [
            'ACTIVE>ACTIVE' => [200,200,200,200], 'ACTIVE>INACTIVE' => [200,409,200,409],
            'ACTIVE>MAINTENANCE' => [200,409,200,409], 'INACTIVE>ACTIVE' => [200,200,409,409],
            'INACTIVE>INACTIVE' => [200,200,200,200], 'INACTIVE>MAINTENANCE' => [200,409,200,409],
            'MAINTENANCE>ACTIVE' => [200,200,409,409], 'MAINTENANCE>INACTIVE' => [200,409,409,409],
            'MAINTENANCE>MAINTENANCE' => [200,200,200,200],
        ];
        $cases = [];
        foreach ($oracle as $pair => $expected) {
            [$from, $to] = explode('>', $pair);
            foreach ([[false,false], [true,false], [false,true], [true,true]] as $i => [$b, $m]) {
                $cases[$pair.'-'.$i] = [$from, $to, $b, $m, $expected[$i]];
            }
        }

        return $cases;
    }

    #[DataProvider('transitions')]
    public function test_exact_matrix_contract_and_conflict_priority(string $from, string $to, bool $b, bool $m, int $expected): void
    {
        [$host, $property, $type, $room] = $this->fixture($from);
        if ($b) $this->booking($room, 'CONFIRMED');
        if ($m) $this->maintenance($room, 'IN_PROGRESS', '2026-11-01', '2026-11-02');
        $response = $this->actingAs($host)->patchJson($this->endpoint($room->id), ['status' => $to])->assertStatus($expected);
        if ($expected === 200) {
            $response->assertExactJson(['success' => true, 'message' => 'Cập nhật trạng thái phòng thành công.', 'data' => [
                'id' => $room->id, 'room_number' => '001', 'name' => 'Before update', 'description' => 'Before',
                'capacity' => 4, 'price_per_night' => '100.00', 'status' => $to,
                'room_type' => ['id' => $type->id, 'name' => 'Suite'],
            ]]);
        } else {
            $response->assertExactJson(['message' => $b && in_array($to, ['INACTIVE', 'MAINTENANCE'], true)
                ? 'Phòng còn booking đang giữ chỗ hoặc đang lưu trú.'
                : 'Phòng còn lịch bảo trì đang có hiệu lực hoặc chưa hoàn tất.']);
        }
        $this->assertSame($expected === 200 ? $to : $from, $room->fresh()->status->value);
        $this->assertSame($property->id, $room->fresh()->property_id);
    }

    public static function dateCases(): array
    {
        $dates = [
            'past' => ['2026-11-13','2026-11-14',false,false],
            'ends_today' => ['2026-11-14','2026-11-15',false,false],
            'starts_today' => ['2026-11-15','2026-11-16',true,true],
            'future' => ['2026-11-16','2026-11-17',true,false],
            'zero_length' => ['2026-11-15','2026-11-15',true,true],
            'reversed' => ['2026-11-16','2026-11-15',true,true],
            'zero_start' => ['0000-00-00','2026-11-16',true,true],
            'zero_end' => ['2026-11-15','0000-00-00',true,true],
            'zero_month' => ['2026-00-01','2026-00-02',true,true],
            'zero_day' => ['2026-11-00','2026-11-14',true,true],
            'zero_year' => ['0000-11-01','0000-11-02',true,true],
            'invalid_calendar' => ['2026-02-30','2026-03-01',true,true],
        ];
        $cases = [];
        foreach (['booking' => ['PENDING','CONFIRMED','CHECKED_IN','COMPLETED','CANCELLED','REJECTED'],
            'maintenance' => ['SCHEDULED','IN_PROGRESS','COMPLETED','CANCELLED']] as $kind => $statuses) {
            foreach ($statuses as $status) {
                foreach ($dates as $label => [$start, $end, $b, $m]) {
                    $blocked = $kind === 'booking' ? ($status === 'CHECKED_IN' || (in_array($status, ['PENDING','CONFIRMED'], true) && $b))
                        : ($status === 'IN_PROGRESS' || ($status === 'SCHEDULED' && $m));
                    $cases[$kind.'-'.$status.'-'.$label] = [$kind, $status, $start, $end, $blocked];
                }
            }
        }

        return $cases;
    }

    #[DataProvider('dateCases')]
    public function test_date_cases_through_real_status_endpoint(string $kind, string $status, string $start, string $end, bool $blocked): void
    {
        [$host, , , $room] = $this->fixture($kind === 'booking' ? 'ACTIVE' : 'INACTIVE');
        $mode = DB::connection()->getDriverName() === 'mysql' ? DB::selectOne('SELECT @@session.sql_mode AS mode')->mode : null;
        if ($mode !== null) {
            $legacy = implode(',', array_diff(explode(',', $mode), ['NO_ZERO_DATE','NO_ZERO_IN_DATE'])).',ALLOW_INVALID_DATES';
            DB::statement('SET SESSION sql_mode = ?', [$legacy]);
        }
        try {
            $this->$kind($room, $status, $start, $end);
            $to = $kind === 'booking' ? 'INACTIVE' : 'ACTIVE';
            $this->actingAs($host)->patchJson($this->endpoint($room->id), ['status' => $to])->assertStatus($blocked ? 409 : 200);
            $this->assertSame($blocked ? $room->status->value : $to, $room->fresh()->status->value);
        } finally {
            if ($mode !== null) DB::statement('SET SESSION sql_mode = ?', [$mode]);
        }
    }

    public static function invalidStatuses(): array
    {
        return [[null], [false], [true], [1], [[]], [['ACTIVE']], ['active'], ['BOOKED'], [' ACTIVE '], [''], ['0']];
    }

    #[DataProvider('invalidStatuses')]
    public function test_invalid_raw_status_is_rejected_without_coercion(mixed $status): void
    {
        [$host, , , $room] = $this->fixture();
        $before = $room->getAttributes();
        $this->actingAs($host)->patchJson($this->endpoint($room->id), ['status' => $status])
            ->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertSame($before, $room->fresh()->getAttributes());
    }

    public function test_payload_query_file_and_error_precedence(): void
    {
        [$host, , , $room] = $this->fixture();
        $this->actingAs($host)->patchJson($this->endpoint($room->id), [])->assertUnprocessable()->assertJsonValidationErrors('status');
        foreach (['expected_status', 'property_id', 'room_number'] as $field) {
            $this->patchJson($this->endpoint($room->id), ['status' => 'INACTIVE', $field => 'ACTIVE'])
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->patchJson($this->endpoint($room->id).'?page=1', ['status' => 'INACTIVE'])
            ->assertUnprocessable()->assertJsonValidationErrors('page');
        $this->call('PATCH', $this->endpoint($room->id), [], [], [
            'status' => \Illuminate\Http\UploadedFile::fake()->create('fixture.txt', 1),
        ], ['HTTP_ACCEPT' => 'application/json'], '{"status":"INACTIVE"}')
            ->assertUnprocessable()->assertJsonValidationErrors('status');
        foreach (['01', '0', 'abc', '9223372036854775808'] as $id) {
            $this->patchJson($this->endpoint($id).'?page=1', [])->assertNotFound()->assertExactJson(['message' => 'Không tìm thấy phòng.']);
        }
        $this->patchJson($this->endpoint(999999), [])->assertUnprocessable();
        $this->patchJson($this->endpoint(999999), ['status' => 'ACTIVE'])->assertNotFound();
    }

    public function test_guards_privacy_and_direct_service_role(): void
    {
        [$host, , , $room] = $this->fixture();
        $this->patchJson($this->endpoint('01'), [])->assertUnauthorized();
        foreach ([['CUSTOMER','ACTIVE'], ['ADMIN','ACTIVE'], ['HOST','BLOCKED'], ['HOST','INACTIVE']] as [$role,$status]) {
            $actor = User::factory()->create(['role' => $role, 'status' => $status]);
            $this->actingAs($actor)->patchJson($this->endpoint('01'), [])->assertForbidden();
        }
        $other = User::factory()->create(['role' => 'HOST', 'status' => 'ACTIVE']);
        $this->actingAs($other)->patchJson($this->endpoint($room->id), ['status' => 'INACTIVE'])->assertNotFound()
            ->assertExactJson(['message' => 'Không tìm thấy phòng.']);
        try {
            $this->app->make(HostRoomService::class)->changeStatus(User::factory()->create(['role' => 'CUSTOMER']),
                new ChangeHostRoomStatusRequest($room->id, RoomStatus::INACTIVE));
            $this->fail('Expected role denial');
        } catch (HttpResponseException $e) {
            $this->assertSame(403, $e->getResponse()->getStatusCode());
        }
    }

    public static function propertyStates(): array
    {
        return [['ACTIVE'], ['INACTIVE'], ['PENDING'], ['BLOCKED']];
    }

    #[DataProvider('propertyStates')]
    public function test_property_states_legacy_type_and_public_visibility(string $state): void
    {
        [$host, $property, , $room] = $this->fixture('ACTIVE', $state);
        [, , $foreignType] = $this->fixture();
        $room->update(['room_type_id' => $foreignType->id]);
        $this->actingAs($host)->patchJson($this->endpoint($room->id), ['status' => 'INACTIVE'])
            ->assertOk()->assertJsonPath('data.room_type', null);
        $this->assertSame($state, $property->fresh()->status->value);
    }

    public function test_noop_never_saves_logs_or_reads_facts_even_with_blockers(): void
    {
        [$host, , , $room] = $this->fixture('MAINTENANCE');
        $this->booking($room, 'CHECKED_IN', '2026-11-01', '2026-11-02');
        $this->maintenance($room, 'IN_PROGRESS', '2026-11-01', '2026-11-02');
        $before = $room->getAttributes();
        Log::spy();
        $queries = [];
        DB::listen(function ($query) use (&$queries): void { $queries[] = $query->sql; });
        Room::saving(fn () => throw new RuntimeException('No-op attempted save'));
        try {
            $this->actingAs($host)->patchJson($this->endpoint($room->id), ['status' => 'MAINTENANCE'])->assertOk();
        } finally {
            Room::flushEventListeners();
        }
        $this->assertSame($before, $room->fresh()->getAttributes());
        $this->assertSame([], array_values(array_filter($queries, fn ($sql) => preg_match('/\b(bookings|maintenances)\b/i', $sql))));
        Log::shouldNotHaveReceived('info');
    }

    public function test_other_room_facts_and_multiple_records_and_public_projection(): void
    {
        [$host, $property, , $room] = $this->fixture();
        [, , , $other] = $this->fixture();
        $this->booking($other, 'CHECKED_IN');
        $this->maintenance($other, 'IN_PROGRESS');
        $this->actingAs($host)->patchJson($this->endpoint($room->id), ['status' => 'INACTIVE'])->assertOk();
        $this->getJson('/api/public/properties/'.$property->id)->assertOk()->assertJsonPath('data.rooms', []);
        $this->patchJson($this->endpoint($room->id), ['status' => 'ACTIVE'])->assertOk();
        $this->getJson('/api/public/properties/'.$property->id)->assertOk()->assertJsonPath('data.rooms.0.id', $room->id);
        $this->booking($room, 'CANCELLED');
        $this->booking($room, 'PENDING');
        $this->patchJson($this->endpoint($room->id), ['status' => 'INACTIVE'])->assertConflict();
    }

    public function test_persistence_and_mapping_failures_roll_back_without_log(): void
    {
        [$host, , , $room] = $this->fixture();
        $before = $room->getAttributes();
        $input = new ChangeHostRoomStatusRequest($room->id, RoomStatus::INACTIVE);
        Log::spy();
        Room::updated(fn () => throw new RuntimeException('Fixture after status UPDATE'));
        try {
            $this->app->make(HostRoomService::class)->changeStatus($host, $input);
            $this->fail('Expected persistence failure');
        } catch (RuntimeException $e) {
            $this->assertSame('Fixture after status UPDATE', $e->getMessage());
        } finally {
            Room::flushEventListeners();
        }
        $this->assertSame($before, $room->fresh()->getAttributes());
        $engine = $this->createMock(ObjectMapperInterface::class);
        $engine->expects($this->once())->method('map')->willThrowException(new RuntimeException('Fixture status map'));
        $this->app->instance(ObjectMapperInterface::class, $engine);
        try {
            $this->app->make(HostRoomService::class)->changeStatus($host, $input);
            $this->fail('Expected mapping failure');
        } catch (RuntimeException $e) {
            $this->assertSame('Fixture status map', $e->getMessage());
        }
        $this->assertSame($before, $room->fresh()->getAttributes());
        Log::shouldNotHaveReceived('info');
    }

    public function test_related_snapshots_are_unchanged_and_utc_date_ignores_database_timezone(): void
    {
        [$host, $property, $type, $room] = $this->fixture('INACTIVE');
        $this->booking($room, 'CONFIRMED');
        $booking = \App\Models\Booking::firstOrFail();
        \App\Models\Invoice::create(['invoice_code' => 'STATUS-INVOICE', 'booking_id' => $booking->id,
            'customer_name' => 'Fixture', 'customer_email' => 'fixture@example.test', 'customer_phone' => '0900000000',
            'property_name' => 'Fixture', 'room_name' => 'Fixture', 'subtotal' => '100.25',
            'discount_amount' => '0.00', 'extra_fee' => '0.00', 'total_amount' => '100.25', 'payment_status' => 'UNPAID']);
        $room->images()->create(['image_path' => 'rooms/fixture.png', 'is_primary' => true]);
        $amenity = \App\Models\Amenity::create(['name' => 'Fixture', 'status' => false]);
        $room->amenities()->attach($amenity->id);
        // UTC still Nov 15, Bangkok Nov 16. A scheduled Nov 16 maintenance must not block UTC activation.
        $this->maintenance($room, 'SCHEDULED', '2026-11-16', '2026-11-17');
        $tables = ['bookings','invoices','invoice_items','booking_services','maintenances','room_images','room_amenities','properties','room_types'];
        $before = [];
        foreach ($tables as $table) $before[$table] = DB::table($table)->get()->toJson();
        $zone = DB::connection()->getDriverName() === 'mysql' ? DB::selectOne('SELECT @@session.time_zone AS zone')->zone : null;
        if ($zone !== null) DB::statement("SET SESSION time_zone = '+07:00'");
        try {
            $this->actingAs($host)->patchJson($this->endpoint($room->id), ['status' => 'ACTIVE'])->assertOk();
        } finally {
            if ($zone !== null) DB::statement('SET SESSION time_zone = ?', [$zone]);
        }
        foreach ($tables as $table) $this->assertSame($before[$table], DB::table($table)->get()->toJson(), $table);
        $this->assertSame('100.00', $room->fresh()->price_per_night);
        $this->assertSame(4, $room->fresh()->capacity);
    }
}
