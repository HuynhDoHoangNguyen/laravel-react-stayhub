<?php

namespace Tests\Feature\Catalog;

use App\DTOs\Catalog\Requests\UpdateHostRoomRequest;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
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

final class HostRoomUpdateTest extends TestCase
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
            && $database === getenv('STAYHUB_ROOM_UPDATE_TEST_DATABASE')
            && preg_match('/\Astayhub_room04_test_[a-f0-9]{32}\z/', $database) === 1;
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
        return '/api/host/rooms/'.$id;
    }

    private function payload(RoomType $type, array $extra = []): array
    {
        return array_replace(['room_type_id' => (string) $type->id, 'room_number' => ' A101 ',
            'name' => ' Updated room ', 'description' => ' ', 'capacity' => '2', 'price_per_night' => '350000.25'], $extra);
    }

    public static function states(): array
    {
        $states = [];
        foreach (['ACTIVE', 'INACTIVE', 'MAINTENANCE'] as $room) {
            foreach (['ACTIVE', 'INACTIVE', 'PENDING', 'BLOCKED'] as $property) {
                $states[] = [$room, $property];
            }
        }

        return $states;
    }

    #[DataProvider('states')]
    public function test_exact_update_contract_preserves_all_states(string $status, string $propertyStatus): void
    {
        [$host, $property, $type, $room] = $this->fixture($status, $propertyStatus);
        $this->actingAs($host)->putJson($this->endpoint($room->id), $this->payload($type))
            ->assertOk()->assertExactJson(['success' => true, 'message' => 'Cập nhật phòng thành công.', 'data' => [
                'id' => $room->id, 'room_number' => 'A101', 'name' => 'Updated room', 'description' => null,
                'capacity' => 2, 'price_per_night' => '350000.25', 'status' => $status,
                'room_type' => ['id' => $type->id, 'name' => 'Suite'],
            ]]);
        $this->assertSame($property->id, $room->fresh()->property_id);
        $this->assertSame($propertyStatus, $property->fresh()->status->value);
        $this->assertDatabaseCount('rooms', 1);
    }

    #[DataProvider('invalidInputs')]
    public function test_raw_invalid_input_does_not_modify_room(string $field, mixed $value): void
    {
        [$host, , $type, $room] = $this->fixture();
        $before = $room->getAttributes();
        $this->actingAs($host)->call('PUT', $this->endpoint($room->id), [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
        ], json_encode($this->payload($type, [$field => $value]), JSON_PRESERVE_ZERO_FRACTION))
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertSame($before, $room->fresh()->getAttributes());
    }

    public function test_put_requires_all_five_fields_and_rejects_query_files_and_extra_fields(): void
    {
        [$host, , $type, $room] = $this->fixture();
        $this->actingAs($host);
        foreach (['room_type_id', 'room_number', 'name', 'capacity', 'price_per_night'] as $field) {
            $payload = $this->payload($type);
            unset($payload[$field]);
            $this->putJson($this->endpoint($room->id), $payload)->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        foreach (['id', 'created_at', 'updated_at', 'amenities'] as $field) {
            $this->putJson($this->endpoint($room->id), $this->payload($type, [$field => 1]))
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->putJson($this->endpoint($room->id).'?page=1', $this->payload($type))
            ->assertUnprocessable()->assertJsonValidationErrors('page');
        $this->call('PUT', $this->endpoint($room->id), $this->payload($type), [], [
            'photo' => \Illuminate\Http\UploadedFile::fake()->create('fixture.txt', 1),
        ], ['HTTP_ACCEPT' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('photo');
        $this->call('PUT', $this->endpoint($room->id), $this->payload($type), [], [
            'name' => \Illuminate\Http\UploadedFile::fake()->create('fixture.txt', 1),
        ], ['HTTP_ACCEPT' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->assertSame('Before update', $room->fresh()->name);
    }

    public function test_description_omitted_clears_and_numeric_bounds_are_exact(): void
    {
        [$host, , $type, $room] = $this->fixture();
        $payload = $this->payload($type, ['capacity' => 2147483647, 'room_number' => str_repeat('A', 50),
            'name' => str_repeat('B', 255), 'price_per_night' => '9999999999.99']);
        unset($payload['description']);
        $this->actingAs($host)->putJson($this->endpoint($room->id), $payload)->assertOk()
            ->assertJsonPath('data.description', null)->assertJsonPath('data.capacity', 2147483647)
            ->assertJsonPath('data.price_per_night', '9999999999.99');
        $this->putJson($this->endpoint($room->id), $this->payload($type, ['description' => str_repeat('á', 10000),
            'price_per_night' => '0.1']))->assertOk()->assertJsonPath('data.price_per_night', '0.10');
    }

    public function test_noop_excludes_self_and_does_not_save_change_timestamp_or_log(): void
    {
        [$host, , $type, $room] = $this->fixture();
        $before = $room->getAttributes();
        Log::spy();
        Room::saving(fn () => throw new RuntimeException('No-op must not save'));
        $this->travel(5)->minutes();
        try {
            $this->actingAs($host)->putJson($this->endpoint($room->id), $this->payload($type, [
                'room_number' => ' 001 ', 'name' => ' Before update ', 'description' => ' Before ',
                'capacity' => 4, 'price_per_night' => '100',
            ]))->assertOk();
        } finally {
            Room::flushEventListeners();
            $this->travelBack();
        }
        $this->assertSame($before, $room->fresh()->getAttributes());
        Log::shouldNotHaveReceived('info');
    }

    public function test_duplicate_and_case_collation_exclude_self_and_scope_property(): void
    {
        [$host, $property, $type, $room] = $this->fixture();
        $other = $property->rooms()->create(['room_type_id' => $type->id, 'room_number' => 'TAKEN',
            'name' => 'Other room', 'capacity' => 1, 'price_per_night' => '10.00', 'status' => 'ACTIVE']);
        $this->actingAs($host)->putJson($this->endpoint($room->id), $this->payload($type, ['room_number' => 'TAKEN']))
            ->assertConflict()->assertExactJson(['message' => 'Cơ sở đã có phòng cùng số.']);
        $this->putJson($this->endpoint($room->id), $this->payload($type, ['room_number' => 'taken']))
            ->assertStatus(DB::connection()->getDriverName() === 'mysql' ? 409 : 200);
        $this->assertSame('Other room', $other->fresh()->name);
        [, , , $foreign] = $this->fixture();
        $foreign->update(['room_number' => 'OTHER-PROPERTY']);
        $this->putJson($this->endpoint($room->id), $this->payload($type, ['room_number' => 'OTHER-PROPERTY']))->assertOk();
    }

    public function test_room_type_must_match_property_and_legacy_mismatch_can_be_repaired(): void
    {
        [$host, $property, $type, $room] = $this->fixture();
        [$otherHost, $otherProperty, $otherType] = $this->fixture();
        $sameHostProperty = Property::create(['host_id' => $host->id, 'name' => 'Other owned property',
            'type' => 'HOTEL', 'address' => 'Fixture', 'status' => 'ACTIVE']);
        $sameHostType = $sameHostProperty->roomTypes()->create(['name' => 'Same owner other property']);
        foreach ([$otherType->id, $sameHostType->id, 999999] as $id) {
            $this->actingAs($host)->putJson($this->endpoint($room->id), $this->payload($type, ['room_type_id' => $id]))
                ->assertUnprocessable()->assertJsonPath('errors.room_type_id', [
                    'Loại phòng không thuộc cơ sở này hoặc không còn tồn tại.',
                ]);
        }
        $room->update(['room_type_id' => $otherType->id]);
        $this->putJson($this->endpoint($room->id), $this->payload($type))->assertOk()->assertJsonPath('data.room_type.id', $type->id);
    }

    public function test_privacy_and_error_precedence(): void
    {
        [$host, , $type, $room] = $this->fixture();
        [, , , $foreign] = $this->fixture();
        $this->actingAs($host);
        foreach ([$foreign->id, 999999, '01', '0', 'abc'] as $id) {
            $this->putJson($this->endpoint($id), $this->payload($type))->assertNotFound()
                ->assertExactJson(['message' => 'Không tìm thấy phòng.']);
        }
        $this->putJson($this->endpoint('01').'?page=1', [])->assertNotFound();
        $this->putJson($this->endpoint($foreign->id), [])->assertUnprocessable();
    }

    public function test_guest_role_and_account_guards_run_before_payload(): void
    {
        $this->putJson($this->endpoint('01'), [])->assertUnauthorized();
        foreach ([['ADMIN', 'ACTIVE'], ['CUSTOMER', 'ACTIVE'], ['HOST', 'BLOCKED'], ['HOST', 'INACTIVE']] as [$role, $status]) {
            $actor = User::factory()->create(['role' => $role, 'status' => $status]);
            $this->actingAs($actor)->putJson($this->endpoint('01'), [])->assertForbidden();
        }
        try {
            $this->app->make(HostRoomService::class)->update($actor = User::factory()->create(['role' => 'CUSTOMER']),
                new UpdateHostRoomRequest(1, 1, '101', 'Fixture', null, 2, '1.00'));
            $this->fail('Expected role denial');
        } catch (HttpResponseException $error) {
            $this->assertSame(403, $error->getResponse()->getStatusCode());
        }
    }

    public function test_booking_invoice_maintenance_images_amenities_and_other_room_are_unchanged(): void
    {
        [$host, , $type, $room] = $this->fixture('MAINTENANCE');
        [, , , $other] = $this->fixture();
        $customer = User::factory()->create(['role' => 'CUSTOMER']);
        $booking = \App\Models\Booking::create(['booking_code' => 'UPDATE-SNAPSHOT', 'customer_id' => $customer->id,
            'room_id' => $room->id, 'check_in_date' => '2026-11-15', 'check_out_date' => '2026-11-16',
            'guest_count' => 4, 'number_of_nights' => 1, 'price_per_night' => '100.00', 'room_total' => '100.00', 'status' => 'CONFIRMED']);
        \App\Models\Invoice::create(['invoice_code' => 'UPDATE-INVOICE', 'booking_id' => $booking->id,
            'customer_name' => 'Fixture customer', 'customer_phone' => '0900000000', 'customer_email' => 'fixture@example.test',
            'property_name' => 'Update property', 'room_name' => 'Before update',
            'subtotal' => '100.00', 'discount_amount' => '0.00', 'extra_fee' => '0.00',
            'total_amount' => '100.00', 'payment_status' => 'UNPAID']);
        $room->images()->create(['image_path' => 'rooms/fixture.png', 'is_primary' => true]);
        $amenity = \App\Models\Amenity::create(['name' => 'Fixture amenity', 'status' => false]);
        $room->amenities()->attach($amenity);
        $room->maintenances()->create(['start_date' => '2026-11-15', 'end_date' => '2026-11-16',
            'reason' => 'Fixture', 'status' => 'SCHEDULED']);
        $tables = ['bookings', 'invoices', 'maintenances', 'room_images', 'room_amenities', 'properties'];
        $before = [];
        foreach ($tables as $table) $before[$table] = DB::table($table)->get()->toJson();
        $otherBefore = $other->getAttributes();
        $this->actingAs($host)->putJson($this->endpoint($room->id), $this->payload($type, ['capacity' => 1]))->assertOk();
        foreach ($tables as $table) $this->assertSame($before[$table], DB::table($table)->get()->toJson(), $table);
        $this->assertSame($otherBefore, $other->fresh()->getAttributes());
        $this->assertSame('MAINTENANCE', $room->fresh()->status->value);
    }

    public function test_persistence_or_mapping_failure_rolls_back_and_never_logs(): void
    {
        [$host, , $type, $room] = $this->fixture();
        $before = $room->getAttributes();
        $input = $this->app->make(CatalogValidator::class)->validateHostRoomUpdate($host, (string) $room->id, $this->payload($type), []);
        Log::spy();
        Room::updated(fn () => throw new RuntimeException('Fixture after UPDATE'));
        try {
            $this->app->make(HostRoomService::class)->update($host, $input);
            $this->fail('Expected failure');
        } catch (RuntimeException $error) {
            $this->assertSame('Fixture after UPDATE', $error->getMessage());
        } finally {
            Room::flushEventListeners();
        }
        $this->assertSame($before, $room->fresh()->getAttributes());
        $engine = $this->createMock(ObjectMapperInterface::class);
        $engine->expects($this->once())->method('map')->willThrowException(new RuntimeException('Fixture mapping'));
        $this->app->instance(ObjectMapperInterface::class, $engine);
        try {
            $this->app->make(HostRoomService::class)->update($host, $input);
            $this->fail('Expected mapping failure');
        } catch (RuntimeException $error) {
            $this->assertSame('Fixture mapping', $error->getMessage());
        }
        $this->assertSame($before, $room->fresh()->getAttributes());
        Log::shouldNotHaveReceived('info');
    }
    public static function invalidInputs(): array
    {
        return [
            ['room_type_id', null], ['room_type_id', true], ['room_type_id', 1.0],
            ['room_type_id', '01'], ['room_type_id', '1e2'], ['room_type_id', []],
            ['room_number', null], ['room_number', ' '], ['room_number', 101], ['room_number', str_repeat('A', 51)],
            ['name', ' '], ['name', []], ['name', str_repeat('A', 256)],
            ['description', false], ['description', str_repeat('a', 10001)],
            ['capacity', 0], ['capacity', -1], ['capacity', true], ['capacity', 1.0],
            ['capacity', '01'], ['capacity', '1e2'], ['capacity', '2147483648'],
            ['price_per_night', 100], ['price_per_night', 0.1], ['price_per_night', '0'],
            ['price_per_night', '-1'], ['price_per_night', '1e2'], ['price_per_night', '1,000'],
            ['price_per_night', '1.001'], ['price_per_night', '10000000000.00'],
            ['status', 'INACTIVE'], ['property_id', 99], ['host_id', 99], ['images', []],
        ];
    }

}
