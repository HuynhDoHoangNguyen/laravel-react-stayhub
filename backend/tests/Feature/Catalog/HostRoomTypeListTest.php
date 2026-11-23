<?php

namespace Tests\Feature\Catalog;

use App\DTOs\Catalog\Requests\HostRoomTypeListQuery;
use App\DTOs\Catalog\Responses\HostRoomTypeResponse;
use App\Mappers\Catalog\CatalogMapper;
use App\Models\Property;
use App\Models\RoomType;
use App\Models\User;
use App\Services\Catalog\HostRoomTypeQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use App\Exceptions\AppException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\ObjectMapper\ObjectMapper;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;

final class HostRoomTypeListTest extends TestCase
{
    use RefreshDatabase;

    public function createApplication()
    {
        foreach (['APP_ENV' => 'testing',
            'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'] as $key => $expected) {
            if (($_ENV[$key] ?? getenv($key)) !== $expected) {
                throw new RuntimeException('Host room type tests require isolated in-memory testing configuration.');
            }
        }
        if (is_file(dirname(__DIR__, 3).'/bootstrap/cache/config.php')) {
            throw new RuntimeException('Cached configuration is unsafe for Host room type tests.');
        }
        $driver = $_ENV['DB_CONNECTION'] ?? getenv('DB_CONNECTION');
        $database = $_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE');
        $sqlite = $driver === 'sqlite' && $database === ':memory:';
        $mysql = $driver === 'mysql' && is_string($database)
            && $database === getenv('STAYHUB_ROOM_TYPE_TEST_DATABASE')
            && preg_match('/\Astayhub_rt01_test_[a-f0-9]{32}\z/', $database) === 1;
        if (!$sqlite && !$mysql) {
            throw new RuntimeException('Host room type tests require an explicitly isolated test database.');
        }
        $app = parent::createApplication();
        $config = $app['config'];
        $connection = $config->get("database.connections.{$driver}");
        if ($config->get('database.default') !== $driver
            || ($connection['database'] ?? null) !== $database
            || !empty($connection['url'])
            || ($mysql && (($connection['host'] ?? null) !== '127.0.0.1' || !empty($connection['unix_socket'])))
            || $config->get('cache.default') !== 'array' || $config->get('session.driver') !== 'array') {
            throw new RuntimeException('Effective Host room type database configuration is unsafe.');
        }
        if ($mysql && $app['db']->connection()->selectOne('SELECT DATABASE() AS selected_database')->selected_database !== $database) {
            throw new RuntimeException('Selected MySQL database is not the owned test schema.');
        }

        return $app;
    }

    private function actor(string $role = 'HOST', string $status = 'ACTIVE'): User
    {
        return User::factory()->create(['role' => $role, 'status' => $status]);
    }

    private function property(User $owner, string $status = 'ACTIVE'): Property
    {
        return Property::create([
            'host_id' => $owner->id, 'name' => 'Fixture property',
            'type' => 'HOTEL', 'address' => 'Fixture address', 'status' => $status,
        ]);
    }

    private function endpoint(int|string $property): string
    {
        return '/api/host/properties/'.$property.'/room-types';
    }

    public static function statuses(): array
    {
        return [['ACTIVE'], ['INACTIVE'], ['PENDING'], ['BLOCKED']];
    }

    #[DataProvider('statuses')]
    public function test_owned_list_preserves_exact_contract_and_order(string $status): void
    {
        $host = $this->actor();
        $property = $this->property($host, $status);
        $create = fn (string $name, ?string $description) => $property->roomTypes()->create(compact('name', 'description'));
        $z = $create('Zulu', null);
        $a = $create('Alpha', 'Mô tả');
        $tie = $create('Alpha', null);
        $this->property($host)->roomTypes()->create(['name' => 'Private sibling']);
        $this->property($this->actor())->roomTypes()->create(['name' => 'Private other host']);

        $this->actingAs($host)->getJson($this->endpoint($property->id))->assertOk()->assertExactJson([
            'success' => true,
            'message' => 'Danh sách loại phòng của cơ sở lưu trú.',
            'data' => [
                ['id' => $a->id, 'name' => 'Alpha', 'description' => 'Mô tả'],
                ['id' => $tie->id, 'name' => 'Alpha', 'description' => null],
                ['id' => $z->id, 'name' => 'Zulu', 'description' => null],
            ],
        ]);
        $this->assertDatabaseCount('room_types', 5);
    }

    public function test_owned_empty_list_returns_an_array(): void
    {
        $host = $this->actor();
        $this->actingAs($host)->getJson($this->endpoint($this->property($host)->id))->assertOk()->assertExactJson([
            'success' => true, 'message' => 'Danh sách loại phòng của cơ sở lưu trú.', 'data' => [],
        ]);
    }

    public function test_privacy_validation_and_error_precedence(): void
    {
        $host = $this->actor();
        $other = $this->property($this->actor());
        $badQuery = '?page=2';
        $this->getJson($this->endpoint('01').$badQuery)->assertUnauthorized();
        $this->actingAs($host);
        foreach (['0', '01', 'abc', '9223372036854775808'] as $id) {
            $this->getJson($this->endpoint($id).$badQuery)->assertNotFound()
                ->assertExactJson(['message' => 'Không tìm thấy cơ sở lưu trú.']);
        }
        foreach ([$other->id, 9999] as $id) {
            $this->getJson($this->endpoint($id))->assertNotFound()
                ->assertExactJson(['message' => 'Không tìm thấy cơ sở lưu trú.']);
            $this->getJson($this->endpoint($id).$badQuery)->assertUnprocessable()->assertJsonValidationErrors('page');
        }
        $this->getJson($this->endpoint($this->property($host)->id).'?search=x')
            ->assertUnprocessable()->assertJsonValidationErrors('search');
    }

    public static function roles(): array
    {
        return [['ADMIN'], ['CUSTOMER']];
    }

    #[DataProvider('roles')]
    public function test_wrong_role_is_denied_before_input_and_by_direct_service(string $role): void
    {
        $actor = $this->actor($role);
        $this->actingAs($actor)->getJson($this->endpoint('01').'?page=2')->assertForbidden()
            ->assertExactJson(['message' => 'Bạn không có quyền truy cập.']);
        try {
            $this->app->make(HostRoomTypeQueryService::class)->listing($actor, new HostRoomTypeListQuery(1));
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
    public function test_account_status_is_checked_before_input(string $status, string $code): void
    {
        $this->actingAs($this->actor('HOST', $status))->getJson($this->endpoint('01').'?page=2')
            ->assertForbidden()->assertJsonPath('code', $code);
    }

    public function test_container_delegates_exact_projection_to_symfony_without_queries_or_mutation(): void
    {
        $symfony = $this->app->make(ObjectMapperInterface::class);
        $this->assertInstanceOf(ObjectMapper::class, $symfony);
        $spy = $this->createMock(ObjectMapperInterface::class);
        $spy->expects($this->once())->method('map')->willReturnCallback(
            function (object $source, string $target) use ($symfony): object {
                $this->assertSame(['id' => 7, 'name' => 'Fixture', 'description' => null], get_object_vars($source));
                $this->assertSame(HostRoomTypeResponse::class, $target);
                return $symfony->map($source, $target);
            },
        );
        $this->app->instance(ObjectMapperInterface::class, $spy);
        $roomType = new RoomType();
        $roomType->setRawAttributes(['id' => 7, 'name' => 'Fixture', 'description' => null, 'property_id' => 9], true);
        $before = $roomType->getAttributes();
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $dto = $this->app->make(CatalogMapper::class)->toHostRoomType($roomType);
            $this->assertSame('{"id":7,"name":"Fixture","description":null}', json_encode($dto));
            $this->assertSame([], DB::getQueryLog());
            $this->assertSame($before, $roomType->getAttributes());
            $this->assertFalse($roomType->isDirty());
        } finally {
            DB::disableQueryLog();
        }
    }
}
