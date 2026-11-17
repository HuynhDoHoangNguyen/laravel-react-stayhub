<?php

namespace Tests\Feature\Catalog;

use App\DTOs\Catalog\Requests\HostRoomDetailQuery;
use App\DTOs\Catalog\Responses\HostRoomDetailResponse;
use App\DTOs\Catalog\Responses\HostRoomResponse;
use App\Mappers\Catalog\CatalogMapper;
use App\Models\Amenity;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use App\Repositories\Catalog\CatalogRepository;
use App\Services\Catalog\HostRoomQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\ObjectMapper\ObjectMapper;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;

final class HostRoomDetailTest extends TestCase
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
            && $database === getenv('STAYHUB_ROOM_DETAIL_TEST_DATABASE')
            && preg_match('/\Astayhub_room02_test_[a-f0-9]{32}\z/', $database) === 1;
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

    private function room(User $owner, string $roomStatus = 'ACTIVE', string $propertyStatus = 'ACTIVE'): Room
    {
        $property = Property::create(['host_id' => $owner->id, 'name' => 'Detail property',
            'type' => 'HOTEL', 'address' => 'Fixture address', 'status' => $propertyStatus]);
        $type = $property->roomTypes()->create(['name' => 'Suite']);

        return $property->rooms()->create(['room_type_id' => $type->id, 'room_number' => '001',
            'name' => 'Detail room', 'description' => null, 'capacity' => 2,
            'price_per_night' => '0.10', 'status' => $roomStatus]);
    }

    private function endpoint(int|string $room): string
    {
        return '/api/host/rooms/'.$room;
    }

    public static function states(): array
    {
        return [['ACTIVE', 'ACTIVE'], ['MAINTENANCE', 'INACTIVE'], ['INACTIVE', 'PENDING'], ['ACTIVE', 'BLOCKED']];
    }

    #[DataProvider('states')]
    public function test_exact_contract_for_owned_room_and_property_states(string $roomStatus, string $propertyStatus): void
    {
        $host = $this->actor();
        $room = $this->room($host, $roomStatus, $propertyStatus);
        $before = $room->fresh()->getAttributes();
        $this->actingAs($host)->getJson($this->endpoint($room->id))->assertOk()->assertExactJson([
            'success' => true, 'message' => 'Chi tiết phòng của cơ sở lưu trú.',
            'data' => [
                'id' => $room->id, 'room_number' => '001', 'name' => 'Detail room', 'description' => null,
                'capacity' => 2, 'price_per_night' => '0.10', 'status' => $roomStatus,
                'room_type' => ['id' => $room->room_type_id, 'name' => 'Suite'],
                'property' => ['id' => $room->property_id, 'name' => 'Detail property', 'status' => $propertyStatus],
                'images' => [], 'amenities' => [],
            ],
        ]);
        $this->assertSame($before, $room->fresh()->getAttributes());
    }

    public function test_gallery_and_assigned_amenities_are_sorted_and_private_urls_are_removed(): void
    {
        Storage::fake('public');
        $host = $this->actor();
        $room = $this->room($host);
        $room->images()->create(['image_path' => 'rooms/secondary.png', 'is_primary' => false]);
        $room->images()->create(['image_path' => 'rooms/primary.png', 'is_primary' => true]);
        foreach (['profile-avatars/1/private.png', 'javascript:alert(1)',
            'https://fixture:credential@example.test/photo.png', 'http://localhost/api/profile/avatar?v=fixture'] as $path) {
            $room->images()->create(['image_path' => $path, 'is_primary' => false]);
        }
        $z = Amenity::create(['name' => 'Zulu', 'status' => true]);
        $a = Amenity::create(['name' => 'Alpha', 'status' => false]);
        $tie = Amenity::create(['name' => 'Alpha', 'status' => true]);
        $room->amenities()->attach([$z->id, $a->id, $tie->id]);
        Amenity::create(['name' => 'Unassigned private fixture', 'status' => true]);

        $response = $this->actingAs($host)->getJson($this->endpoint($room->id))->assertOk()
            ->assertJsonPath('data.images', [
                Storage::disk('public')->url('rooms/primary.png'),
                Storage::disk('public')->url('rooms/secondary.png'),
            ])->assertJsonPath('data.amenities', [
                ['id' => $a->id, 'name' => 'Alpha', 'status' => false],
                ['id' => $tie->id, 'name' => 'Alpha', 'status' => true],
                ['id' => $z->id, 'name' => 'Zulu', 'status' => true],
            ]);
        foreach (['profile-avatars', 'credential', 'javascript:', 'api/profile/avatar', 'Unassigned private fixture'] as $private) {
            $this->assertStringNotContainsString($private, $response->getContent());
        }
    }

    public function test_mismatched_room_type_is_null_without_leaking_foreign_type(): void
    {
        $host = $this->actor();
        $room = $this->room($host);
        $other = $this->room($this->actor());
        $other->roomType->update(['name' => 'Foreign private type']);
        $room->update(['room_type_id' => $other->room_type_id]);

        $response = $this->actingAs($host)->getJson($this->endpoint($room->id))->assertOk()
            ->assertJsonPath('data.room_type', null)->assertJsonPath('data.property.id', $room->property_id);
        $this->assertStringNotContainsString('Foreign private type', $response->getContent());
    }

    public function test_other_owner_missing_room_and_noncanonical_ids_have_same_privacy_response(): void
    {
        $host = $this->actor();
        $other = $this->room($this->actor());
        $this->actingAs($host);
        foreach ([$other->id, '999999999', '0', '-1', '01', '1.0', '1e2', 'abc', '9223372036854775808'] as $id) {
            $this->getJson($this->endpoint($id))->assertNotFound()->assertExactJson(['message' => 'Không tìm thấy phòng.']);
        }
        $this->assertNull($this->app->make(HostRoomQueryService::class)->detail($host, new HostRoomDetailQuery($other->id)));
    }

    public function test_query_errors_follow_role_and_canonical_id_before_ownership_lookup(): void
    {
        $host = $this->actor();
        $room = $this->room($host);
        $this->actingAs($host);
        $this->getJson($this->endpoint('01').'?page=1')->assertNotFound();
        foreach ([$room->id, '999999999'] as $id) {
            $this->getJson($this->endpoint($id).'?page=1')->assertUnprocessable()->assertExactJson([
                'message' => 'Chi tiết phòng không nhận tham số truy vấn.',
                'errors' => ['page' => ['Chi tiết phòng không nhận tham số truy vấn.']],
            ]);
        }
    }

    public function test_guest_is_denied_before_input(): void
    {
        $this->getJson($this->endpoint('01').'?page=1')->assertUnauthorized();
    }

    public static function wrongRoles(): array
    {
        return [['ADMIN'], ['CUSTOMER']];
    }

    #[DataProvider('wrongRoles')]
    public function test_role_gate_precedes_input_and_protects_direct_query(string $role): void
    {
        $actor = $this->actor($role);
        $this->actingAs($actor)->getJson($this->endpoint('01').'?page=1')->assertForbidden();
        try {
            $this->app->make(HostRoomQueryService::class)->detail($actor, new HostRoomDetailQuery(1));
            $this->fail('Expected role denial.');
        } catch (HttpResponseException $error) {
            $this->assertSame(403, $error->getResponse()->getStatusCode());
        }
    }

    public static function deniedAccounts(): array
    {
        return [['BLOCKED', 'ACCOUNT_BLOCKED'], ['INACTIVE', 'ACCOUNT_INACTIVE']];
    }

    #[DataProvider('deniedAccounts')]
    public function test_account_status_precedes_input(string $status, string $code): void
    {
        $this->actingAs($this->actor('HOST', $status))->getJson($this->endpoint('01').'?page=1')
            ->assertForbidden()->assertJsonPath('code', $code);
    }

    public function test_eager_loading_query_count_is_constant_when_relations_grow(): void
    {
        $host = $this->actor();
        $room = $this->room($host);
        $query = $this->app->make(HostRoomQueryService::class);
        DB::enableQueryLog();
        try {
            DB::flushQueryLog();
            $query->detail($host, new HostRoomDetailQuery($room->id));
            $small = count(DB::getQueryLog());
            DB::disableQueryLog();
            foreach (range(1, 10) as $number) {
                $room->images()->create(['image_path' => 'rooms/'.$number.'.png', 'is_primary' => false]);
                $amenity = Amenity::create(['name' => 'Fixture '.$number, 'status' => true]);
                $room->amenities()->attach($amenity->id);
            }
            DB::enableQueryLog();
            DB::flushQueryLog();
            $result = $query->detail($host, new HostRoomDetailQuery($room->id));
            $this->assertSame($small, count(DB::getQueryLog()));
            $this->assertLessThanOrEqual(5, $small);
            $this->assertCount(10, $result->amenities);
            $this->assertCount(10, $result->images);
        } finally {
            DB::disableQueryLog();
        }
    }

    public function test_container_maps_both_dtos_through_symfony_without_queries_or_mutation(): void
    {
        $room = $this->room($this->actor());
        $loaded = $this->app->make(CatalogRepository::class)->findHostRoom($room->property->host_id, $room->id);
        $symfony = $this->app->make(ObjectMapperInterface::class);
        $this->assertInstanceOf(ObjectMapper::class, $symfony);
        $targets = [];
        $spy = $this->createMock(ObjectMapperInterface::class);
        $spy->expects($this->exactly(2))->method('map')->willReturnCallback(
            function (object $source, string $target) use ($symfony, &$targets): object {
                $targets[] = $target;
                return $symfony->map($source, $target);
            },
        );
        $this->app->instance(ObjectMapperInterface::class, $spy);
        $before = $loaded->getAttributes();
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $response = $this->app->make(CatalogMapper::class)->toHostRoomDetail($loaded);
            $this->assertSame([HostRoomResponse::class, HostRoomDetailResponse::class], $targets);
            $this->assertSame([], DB::getQueryLog());
            $this->assertSame($before, $loaded->getAttributes());
            $this->assertFalse($loaded->isDirty());
            $this->assertSame($room->id, $response->jsonSerialize()['id']);
        } finally {
            DB::disableQueryLog();
        }
    }
}
