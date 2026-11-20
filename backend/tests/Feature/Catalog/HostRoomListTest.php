<?php

namespace Tests\Feature\Catalog;

use App\DTOs\Catalog\Requests\HostRoomListQuery;
use App\DTOs\Catalog\Responses\HostRoomResponse;
use App\Mappers\Catalog\CatalogMapper;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Services\Catalog\HostRoomQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use App\Exceptions\AppException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\ObjectMapper\ObjectMapper;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;

final class HostRoomListTest extends TestCase
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
            && $database === getenv('STAYHUB_ROOM_TEST_DATABASE')
            && preg_match('/\Astayhub_room01_test_[a-f0-9]{32}\z/', $database) === 1;
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

    private function property(User $owner, string $status = 'ACTIVE'): Property
    {
        return Property::create(['host_id' => $owner->id, 'name' => 'Room list fixture',
            'type' => 'HOTEL', 'address' => 'Fixture address', 'status' => $status]);
    }

    private function room(Property $property, RoomType $type, string $number, string $status = 'ACTIVE'): Room
    {
        return $property->rooms()->create(['room_type_id' => $type->id, 'room_number' => $number,
            'name' => 'Room '.$number, 'description' => null, 'capacity' => 2,
            'price_per_night' => '123456.78', 'status' => $status]);
    }

    private function endpoint(int|string $property): string
    {
        return '/api/host/properties/'.$property.'/rooms';
    }

    public static function propertyStatuses(): array
    {
        return [['ACTIVE'], ['INACTIVE'], ['PENDING'], ['BLOCKED']];
    }

    #[DataProvider('propertyStatuses')]
    public function test_owned_list_has_exact_projection_all_room_states_and_stable_order(string $status): void
    {
        $host = $this->actor();
        $property = $this->property($host, $status);
        $type = $property->roomTypes()->create(['name' => 'Suite', 'description' => null]);
        $last = $this->room($property, $type, '102', 'INACTIVE');
        $first = $this->room($property, $type, '101', 'MAINTENANCE');
        $tie = $this->room($property, $type, '101');
        $sibling = $this->property($host);
        $this->room($sibling, $sibling->roomTypes()->create(['name' => 'Sibling']), '001');
        $other = $this->property($this->actor());
        $this->room($other, $other->roomTypes()->create(['name' => 'Other']), '001');

        $expected = array_map(fn (Room $room) => [
            'id' => $room->id, 'room_number' => $room->room_number, 'name' => $room->name,
            'description' => null, 'capacity' => 2, 'price_per_night' => '123456.78',
            'status' => $room->status->value, 'room_type' => ['id' => $type->id, 'name' => 'Suite'],
        ], [$first, $tie, $last]);

        $this->actingAs($host)->getJson($this->endpoint($property->id))->assertOk()->assertExactJson([
            'success' => true, 'message' => 'Danh sách phòng của cơ sở lưu trú.', 'data' => $expected,
        ]);
        $this->assertDatabaseCount('rooms', 5);
    }

    public function test_empty_owned_property_returns_an_array(): void
    {
        $host = $this->actor();
        $this->actingAs($host)->getJson($this->endpoint($this->property($host)->id))
            ->assertOk()->assertExactJson([
                'success' => true, 'message' => 'Danh sách phòng của cơ sở lưu trú.', 'data' => [],
            ]);
    }

    public function test_mismatched_room_type_is_null_and_does_not_hide_the_owned_room(): void
    {
        $host = $this->actor();
        $property = $this->property($host);
        $other = $this->property($this->actor());
        $private = $other->roomTypes()->create(['name' => 'Private foreign type']);
        $room = $this->room($property, $private, '101');

        $response = $this->actingAs($host)->getJson($this->endpoint($property->id))->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $room->id)
            ->assertJsonPath('data.0.room_type', null);
        $this->assertStringNotContainsString('Private foreign type', $response->getContent());
        $this->assertDatabaseHas('rooms', ['id' => $room->id, 'room_type_id' => $private->id]);
    }

    public function test_privacy_canonical_ids_and_query_error_precedence(): void
    {
        $host = $this->actor();
        $other = $this->property($this->actor());
        $this->actingAs($host);
        foreach (['0', '-1', '01', '1.0', '1e2', 'abc', '9223372036854775808', '999999999'] as $id) {
            $this->getJson($this->endpoint($id))->assertNotFound()
                ->assertExactJson(['message' => 'Không tìm thấy cơ sở lưu trú.']);
        }
        $this->getJson($this->endpoint($other->id))->assertNotFound()
            ->assertExactJson(['message' => 'Không tìm thấy cơ sở lưu trú.']);
        $this->getJson($this->endpoint('01').'?page=1')->assertNotFound();
        $this->getJson($this->endpoint($other->id).'?page=1')->assertUnprocessable()
            ->assertExactJson([
                'message' => 'Danh sách phòng không nhận tham số truy vấn.',
                'errors' => ['page' => ['Danh sách phòng không nhận tham số truy vấn.']],
            ]);
        $this->getJson($this->endpoint($this->property($host)->id).'?status=ACTIVE&search=x')
            ->assertUnprocessable()->assertJsonValidationErrors(['status', 'search']);
    }

    public function test_guest_is_denied_before_malformed_input(): void
    {
        $this->getJson($this->endpoint('01').'?page=1')->assertUnauthorized();
    }

    public static function wrongRoles(): array
    {
        return [['ADMIN'], ['CUSTOMER']];
    }

    #[DataProvider('wrongRoles')]
    public function test_role_gate_precedes_input_and_protects_direct_service(string $role): void
    {
        $actor = $this->actor($role);
        $this->actingAs($actor)->getJson($this->endpoint('01').'?page=1')->assertForbidden()
            ->assertExactJson(['message' => 'Bạn không có quyền truy cập.']);
        try {
            $this->app->make(HostRoomQueryService::class)->listing($actor, new HostRoomListQuery(1));
            $this->fail('Expected role denial.');
        } catch (AppException $error) {
            $this->assertSame(403, $error->status);
        }
    }

    public static function deniedAccounts(): array
    {
        return [['BLOCKED', 'ACCOUNT_BLOCKED'], ['INACTIVE', 'ACCOUNT_INACTIVE']];
    }

    #[DataProvider('deniedAccounts')]
    public function test_account_status_gate_precedes_input(string $status, string $code): void
    {
        $this->actingAs($this->actor('HOST', $status))->getJson($this->endpoint('01').'?page=1')
            ->assertForbidden()->assertJsonPath('code', $code);
    }

    public function test_query_count_is_constant_and_service_cannot_read_other_property(): void
    {
        $host = $this->actor();
        $property = $this->property($host);
        $type = $property->roomTypes()->create(['name' => 'Suite']);
        $this->room($property, $type, '001');
        $query = $this->app->make(HostRoomQueryService::class);
        DB::enableQueryLog();
        try {
            DB::flushQueryLog();
            $this->assertCount(1, $query->listing($host, new HostRoomListQuery($property->id)));
            $small = count(DB::getQueryLog());
            DB::disableQueryLog();
            foreach (range(2, 20) as $number) {
                $this->room($property, $type, (string) $number);
            }
            $other = $this->property($this->actor());
            DB::enableQueryLog();
            DB::flushQueryLog();
            $this->assertCount(20, $query->listing($host, new HostRoomListQuery($property->id)));
            $this->assertSame($small, count(DB::getQueryLog()));
            $this->assertLessThanOrEqual(3, $small);
            $this->assertNull($query->listing($host, new HostRoomListQuery($other->id)));
        } finally {
            DB::disableQueryLog();
        }
    }

    public function test_container_maps_explicit_loaded_projection_through_symfony_without_queries_or_writes(): void
    {
        $symfony = $this->app->make(ObjectMapperInterface::class);
        $this->assertInstanceOf(ObjectMapper::class, $symfony);
        $spy = $this->createMock(ObjectMapperInterface::class);
        $spy->expects($this->once())->method('map')->willReturnCallback(
            function (object $source, string $target) use ($symfony): object {
                $this->assertSame(['id', 'room_number', 'name', 'description', 'capacity',
                    'price_per_night', 'status', 'room_type'], array_keys(get_object_vars($source)));
                $this->assertSame(HostRoomResponse::class, $target);

                return $symfony->map($source, $target);
            },
        );
        $this->app->instance(ObjectMapperInterface::class, $spy);
        $type = new RoomType();
        $type->setRawAttributes(['id' => 4, 'property_id' => 2, 'name' => 'Suite'], true);
        $room = new Room();
        $room->setRawAttributes(['id' => 7, 'property_id' => 2, 'room_type_id' => 4,
            'room_number' => '101', 'name' => 'Fixture', 'description' => null, 'capacity' => 2,
            'price_per_night' => '0.10', 'status' => 'MAINTENANCE'], true);
        $room->setRelation('roomType', $type);
        $before = $room->getAttributes();
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $dto = $this->app->make(CatalogMapper::class)->toHostRoom($room);
            $this->assertSame([
                'id' => 7, 'room_number' => '101', 'name' => 'Fixture', 'description' => null,
                'capacity' => 2, 'price_per_night' => '0.10', 'status' => 'MAINTENANCE',
                'room_type' => ['id' => 4, 'name' => 'Suite'],
            ], $dto->jsonSerialize());
            $this->assertSame([], DB::getQueryLog());
            $this->assertSame($before, $room->getAttributes());
            $this->assertFalse($room->isDirty());
        } finally {
            DB::disableQueryLog();
        }
    }
}
