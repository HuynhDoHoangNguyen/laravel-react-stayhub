<?php

namespace Tests\Feature\Catalog;

use App\DTOs\Catalog\Requests\CreateHostRoomRequest;
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

final class HostRoomCreateTest extends TestCase
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
            && $database === getenv('STAYHUB_ROOM_CREATE_TEST_DATABASE')
            && preg_match('/\Astayhub_room03_test_[a-f0-9]{32}\z/', $database) === 1;
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

    private function actor(string $role = 'HOST', string $status = 'ACTIVE'): User
    {
        return User::factory()->create(['role' => $role, 'status' => $status]);
    }

    private function property(User $host, string $status = 'ACTIVE'): Property
    {
        return Property::create(['host_id' => $host->id, 'name' => 'Create room fixture',
            'type' => 'HOTEL', 'address' => 'Fixture address', 'status' => $status]);
    }

    private function endpoint(int|string $property): string
    {
        return '/api/host/properties/'.$property.'/rooms';
    }

    private function payload(RoomType $type, array $overrides = []): array
    {
        return array_replace(['room_type_id' => $type->id, 'room_number' => ' A101 ',
            'name' => ' Fixture Room ', 'description' => ' ', 'capacity' => '2', 'price_per_night' => '350000'], $overrides);
    }

    public static function propertyStatuses(): array
    {
        return [['ACTIVE'], ['INACTIVE'], ['PENDING'], ['BLOCKED']];
    }

    #[DataProvider('propertyStatuses')]
    public function test_create_normalizes_input_and_sets_active_with_exact_projection(string $status): void
    {
        $host = $this->actor();
        $property = $this->property($host, $status);
        $type = $property->roomTypes()->create(['name' => 'Suite']);
        $response = $this->actingAs($host)->postJson($this->endpoint($property->id), $this->payload($type))->assertCreated();
        $id = $response->json('data.id');
        $this->assertIsInt($id);
        $response->assertExactJson(['success' => true, 'message' => 'Thêm phòng thành công.', 'data' => [
            'id' => $id, 'room_number' => 'A101', 'name' => 'Fixture Room', 'description' => null,
            'capacity' => 2, 'price_per_night' => '350000.00', 'status' => 'ACTIVE',
            'room_type' => ['id' => $type->id, 'name' => 'Suite'],
        ]]);
        $this->assertDatabaseHas('rooms', ['id' => $id, 'property_id' => $property->id,
            'room_type_id' => $type->id, 'room_number' => 'A101', 'status' => 'ACTIVE']);
        $room = Room::findOrFail($id);
        $this->assertSame('350000.00', $room->price_per_night);
        $this->assertSame(0, $room->images()->count());
        $this->assertSame(0, $room->amenities()->count());
        $public = $this->getJson('/api/public/properties/'.$property->id);
        if ($status === 'ACTIVE') {
            $public->assertOk()->assertJsonPath('data.rooms.0.id', $id);
        } else {
            $public->assertNotFound();
        }
    }

    public function test_upper_bounds_and_decimal_fraction_are_preserved_without_rounding(): void
    {
        $host = $this->actor();
        $property = $this->property($host);
        $type = $property->roomTypes()->create(['name' => 'Suite']);
        $this->actingAs($host)->postJson($this->endpoint($property->id), $this->payload($type, [
            'room_number' => str_repeat('A', 50), 'name' => str_repeat('B', 255),
            'description' => str_repeat('á', 10000), 'capacity' => 2147483647,
            'price_per_night' => '9999999999.99',
        ]))->assertCreated()->assertJsonPath('data.price_per_night', '9999999999.99')
            ->assertJsonPath('data.capacity', 2147483647);
        $this->postJson($this->endpoint($property->id), $this->payload($type, [
            'price_per_night' => '0.1',
        ]))->assertCreated()->assertJsonPath('data.price_per_night', '0.10');
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

    #[DataProvider('invalidInputs')]
    public function test_invalid_payload_never_creates_a_room(string $field, mixed $value): void
    {
        $host = $this->actor();
        $property = $this->property($host);
        $type = $property->roomTypes()->create(['name' => 'Suite']);
        $this->actingAs($host)->call('POST', $this->endpoint($property->id), [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
        ], json_encode($this->payload($type, [$field => $value]), JSON_PRESERVE_ZERO_FRACTION))
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('rooms', 0);
    }

    public function test_duplicate_conflicts_within_property_but_other_property_can_reuse_number(): void
    {
        $host = $this->actor();
        $first = $this->property($host);
        $firstType = $first->roomTypes()->create(['name' => 'Suite']);
        $second = $this->property($host);
        $secondType = $second->roomTypes()->create(['name' => 'Suite']);
        $this->actingAs($host)->postJson($this->endpoint($first->id), $this->payload($firstType))->assertCreated();
        $this->postJson($this->endpoint($first->id), $this->payload($firstType))->assertConflict()
            ->assertExactJson(['message' => 'Cơ sở đã có phòng cùng số.']);
        $this->postJson($this->endpoint($second->id), $this->payload($secondType))->assertCreated();
        $this->assertDatabaseCount('rooms', 2);
    }

    public function test_number_comparison_uses_the_effective_database_collation(): void
    {
        $host = $this->actor();
        $property = $this->property($host);
        $type = $property->roomTypes()->create(['name' => 'Suite']);
        $this->actingAs($host)->postJson($this->endpoint($property->id), $this->payload($type))->assertCreated();
        $expected = DB::connection()->getDriverName() === 'mysql' ? 409 : 201;
        $this->postJson($this->endpoint($property->id), $this->payload($type, ['room_number' => 'a101']))->assertStatus($expected);
    }

    public function test_foreign_or_missing_room_type_has_the_same_field_error(): void
    {
        $host = $this->actor();
        $property = $this->property($host);
        foreach ([$this->property($host), $this->property($this->actor())] as $other) {
            $type = $other->roomTypes()->create(['name' => 'Private type']);
            $this->actingAs($host)->postJson($this->endpoint($property->id), $this->payload($type))
                ->assertUnprocessable()->assertJsonPath('errors.room_type_id', [
                    'Loại phòng không thuộc cơ sở này hoặc không còn tồn tại.',
                ]);
        }
        $type = $property->roomTypes()->create(['name' => 'Deleted type']);
        $payload = $this->payload($type);
        $type->delete();
        $this->postJson($this->endpoint($property->id), $payload)->assertUnprocessable()
            ->assertJsonPath('errors.room_type_id', ['Loại phòng không thuộc cơ sở này hoặc không còn tồn tại.']);
        $this->assertDatabaseCount('rooms', 0);
    }

    public function test_privacy_id_and_query_error_order(): void
    {
        $host = $this->actor();
        $owned = $this->property($host);
        $type = $owned->roomTypes()->create(['name' => 'Suite']);
        $other = $this->property($this->actor());
        $this->actingAs($host);
        foreach ([$other->id, '999999999', '01', '0', 'abc'] as $id) {
            $this->postJson($this->endpoint($id), $this->payload($type))->assertNotFound()
                ->assertExactJson(['message' => 'Không tìm thấy cơ sở lưu trú.']);
        }
        $this->postJson($this->endpoint($owned->id).'?page=1', $this->payload($type))
            ->assertUnprocessable()->assertJsonValidationErrors('page');
        $this->postJson($this->endpoint('01').'?page=1', [])->assertNotFound();
        $this->assertDatabaseCount('rooms', 0);
    }

    public function test_guest_is_denied_before_payload(): void
    {
        $this->postJson($this->endpoint('01'), [])->assertUnauthorized();
    }

    public static function deniedActors(): array
    {
        return [['ADMIN', 'ACTIVE'], ['CUSTOMER', 'ACTIVE'], ['HOST', 'BLOCKED'], ['HOST', 'INACTIVE']];
    }

    #[DataProvider('deniedActors')]
    public function test_role_or_account_is_denied_before_payload(string $role, string $status): void
    {
        $this->actingAs($this->actor($role, $status))->postJson($this->endpoint('01'), [])->assertForbidden();
        $this->assertDatabaseCount('rooms', 0);
    }

    public function test_direct_service_enforces_host_role(): void
    {
        try {
            $this->app->make(HostRoomService::class)->create($this->actor('CUSTOMER'),
                new CreateHostRoomRequest(1, 1, '101', 'Fixture', null, 2, '1.00'));
            $this->fail('Expected role denial.');
        } catch (HttpResponseException $error) {
            $this->assertSame(403, $error->getResponse()->getStatusCode());
        }
    }

    public function test_failure_after_insert_rolls_back_and_does_not_log_success(): void
    {
        $host = $this->actor();
        $property = $this->property($host);
        $type = $property->roomTypes()->create(['name' => 'Suite']);
        $input = $this->app->make(CatalogValidator::class)
            ->validateHostRoomCreate($host, (string) $property->id, $this->payload($type), []);
        Log::spy();
        Room::created(fn () => throw new RuntimeException('Fixture failure after INSERT'));
        try {
            $this->app->make(HostRoomService::class)->create($host, $input);
            $this->fail('Expected fixture failure.');
        } catch (RuntimeException $error) {
            $this->assertSame('Fixture failure after INSERT', $error->getMessage());
        } finally {
            Room::flushEventListeners();
        }
        $this->assertDatabaseCount('rooms', 0);
        Log::shouldNotHaveReceived('info');
    }

    public function test_mapping_failure_also_rolls_back_the_insert(): void
    {
        $host = $this->actor();
        $property = $this->property($host);
        $type = $property->roomTypes()->create(['name' => 'Suite']);
        $input = $this->app->make(CatalogValidator::class)
            ->validateHostRoomCreate($host, (string) $property->id, $this->payload($type), []);
        $engine = $this->createMock(ObjectMapperInterface::class);
        $engine->expects($this->once())->method('map')->willThrowException(new RuntimeException('Fixture mapping failure'));
        $this->app->instance(ObjectMapperInterface::class, $engine);
        Log::spy();
        try {
            $this->app->make(HostRoomService::class)->create($host, $input);
            $this->fail('Expected mapping failure.');
        } catch (RuntimeException $error) {
            $this->assertSame('Fixture mapping failure', $error->getMessage());
        }
        $this->assertDatabaseCount('rooms', 0);
        Log::shouldNotHaveReceived('info');
    }

    public static function uploadedFileCollisionFields(): array
    {
        return array_map(fn (string $field) => [$field],
            ['room_type_id', 'room_number', 'name', 'description', 'capacity', 'price_per_night']);
    }

    #[DataProvider('uploadedFileCollisionFields')]
    public function test_file_with_same_key_as_valid_scalar_is_rejected(string $field): void
    {
        $host = $this->actor();
        $property = $this->property($host);
        $type = $property->roomTypes()->create(['name' => 'Suite']);
        Log::spy();
        $this->actingAs($host)->call('POST', $this->endpoint($property->id), $this->payload($type), [], [
            $field => \Illuminate\Http\UploadedFile::fake()->create('fixture.txt', 1, 'text/plain'),
        ], ['HTTP_ACCEPT' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('rooms', 0);
        Log::shouldNotHaveReceived('info');
    }

    public function test_nested_and_multiple_files_cannot_hide_behind_scalar(): void
    {
        $host = $this->actor();
        $property = $this->property($host);
        $type = $property->roomTypes()->create(['name' => 'Suite']);
        Log::spy();
        foreach (['nested', 'multiple'] as $shape) {
            $first = \Illuminate\Http\UploadedFile::fake()->create('first.txt', 1, 'text/plain');
            $files = $shape === 'nested' ? ['name' => ['child' => $first]] : ['name' => [
                $first, \Illuminate\Http\UploadedFile::fake()->create('second.txt', 1, 'text/plain'),
            ]];
            $this->actingAs($host)->call('POST', $this->endpoint($property->id), $this->payload($type), [], $files,
                ['HTTP_ACCEPT' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('name');
            $this->assertDatabaseCount('rooms', 0);
        }
        Log::shouldNotHaveReceived('info');
    }

    public function test_file_only_field_and_unknown_upload_key_are_rejected(): void
    {
        $host = $this->actor();
        $property = $this->property($host);
        $type = $property->roomTypes()->create(['name' => 'Suite']);
        Log::spy();
        foreach (['name', 'photo'] as $field) {
            $body = $this->payload($type);
            if ($field === 'name') unset($body['name']);
            $this->actingAs($host)->call('POST', $this->endpoint($property->id), $body, [], [
                $field => \Illuminate\Http\UploadedFile::fake()->create('fixture.txt', 1, 'text/plain'),
            ], ['HTTP_ACCEPT' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors($field);
            $this->assertDatabaseCount('rooms', 0);
        }
        Log::shouldNotHaveReceived('info');
    }

    public function test_multipart_without_files_keeps_exact_create_contract(): void
    {
        $host = $this->actor();
        $property = $this->property($host);
        $type = $property->roomTypes()->create(['name' => 'Suite']);
        $response = $this->actingAs($host)->call('POST', $this->endpoint($property->id), $this->payload($type),
            [], [], ['HTTP_ACCEPT' => 'application/json'])->assertCreated();
        $id = $response->json('data.id');
        $response->assertExactJson(['success' => true, 'message' => 'Thêm phòng thành công.', 'data' => [
            'id' => $id, 'room_number' => 'A101', 'name' => 'Fixture Room', 'description' => null,
            'capacity' => 2, 'price_per_night' => '350000.00', 'status' => 'ACTIVE',
            'room_type' => ['id' => $type->id, 'name' => 'Suite'],
        ]]);
        $this->assertDatabaseCount('rooms', 1);
    }

}
