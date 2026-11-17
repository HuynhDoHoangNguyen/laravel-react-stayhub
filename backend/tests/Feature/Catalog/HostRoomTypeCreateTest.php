<?php

namespace Tests\Feature\Catalog;

use App\DTOs\Catalog\Requests\CreateHostRoomTypeRequest;
use App\Models\Property;
use App\Models\User;
use App\Services\Catalog\HostRoomTypeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;

final class HostRoomTypeCreateTest extends TestCase
{
    use RefreshDatabase { beginDatabaseTransaction as beginTestTransaction; }

    public function beginDatabaseTransaction()
    {
        if (!in_array('room-type-real-commit', $this->groups(), true)) {
            $this->beginTestTransaction();
        }
    }

    protected function tearDown(): void
    {
        if (in_array('room-type-real-commit', $this->groups(), true)) {
            RefreshDatabaseState::$migrated = false;
        }
        parent::tearDown();
    }

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
            && $database === getenv('STAYHUB_ROOM_TYPE_CREATE_TEST_DATABASE')
            && preg_match('/\Astayhub_rt02_test_[a-f0-9]{32}\z/', $database) === 1;
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
        return Property::create(['host_id' => $owner->id, 'name' => 'Fixture',
            'type' => 'HOTEL', 'address' => 'Fixture', 'status' => $status]);
    }

    private function endpoint(int|string $id): string
    {
        return '/api/host/properties/'.$id.'/room-types';
    }

    public static function statuses(): array
    {
        return [['ACTIVE'], ['INACTIVE'], ['PENDING'], ['BLOCKED']];
    }

    #[DataProvider('statuses')]
    public function test_create_keeps_projection_order_and_property_status(string $status): void
    {
        $host = $this->actor();
        $property = $this->property($host, $status);
        $z = $property->roomTypes()->create(['name' => 'Zulu']);
        $this->actingAs($host);
        $response = $this->postJson($this->endpoint($property->id), ['name' => ' Alpha ', 'description' => "  Dòng 1\nDòng 2  "])->assertCreated();
        $id = $response->json('data.id');
        $expected = ['id' => $id, 'name' => 'Alpha', 'description' => "Dòng 1\nDòng 2"];
        $response->assertExactJson(['success' => true, 'message' => 'Thêm loại phòng thành công.', 'data' => $expected]);
        $this->assertDatabaseHas('room_types', $expected + ['property_id' => $property->id]);
        $this->getJson($this->endpoint($property->id))->assertJsonPath('data', [
            $expected, ['id' => $z->id, 'name' => 'Zulu', 'description' => null],
        ]);
        $this->assertDatabaseHas('properties', ['id' => $property->id, 'status' => $status]);
        $this->assertDatabaseCount('rooms', 0);
    }

    public function test_boundaries_and_nullable_description(): void
    {
        $host = $this->actor();
        $this->actingAs($host);
        $url = $this->endpoint($this->property($host)->id);
        $name = str_repeat('ộ', 255);
        $description = str_repeat('ộ', 10000);
        $this->postJson($url, compact('name', 'description'))->assertCreated()
            ->assertJsonPath('data.name', $name)->assertJsonPath('data.description', $description);
        foreach ([['name' => 'Omitted'], ['name' => 'Null', 'description' => null], ['name' => 'Blank', 'description' => '  ']] as $body) {
            $this->postJson($url, $body)->assertCreated()->assertJsonPath('data.description', null);
        }
    }

    public function test_invalid_payload_and_query_do_not_insert(): void
    {
        $host = $this->actor();
        $this->actingAs($host);
        $url = $this->endpoint($this->property($host)->id);
        foreach ([[], ['name' => '  '], ['name' => 12], ['name' => str_repeat('a', 256)]] as $body) {
            $this->postJson($url, $body)->assertUnprocessable()->assertJsonValidationErrors('name');
        }
        foreach ([123, str_repeat('a', 10001)] as $description) {
            $this->postJson($url, ['name' => 'Suite', 'description' => $description])
                ->assertUnprocessable()->assertJsonValidationErrors('description');
        }
        foreach (['id', 'property_id', 'host_id', 'status'] as $field) {
            $this->postJson($url, ['name' => 'Suite', $field => 1])->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->postJson($url.'?name=Query', [])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson($url.'?page=2', ['name' => 'Suite'])->assertUnprocessable()->assertJsonValidationErrors('page');
        $this->assertDatabaseCount('room_types', 0);
    }

    public function test_duplicate_uses_property_scope_and_database_collation(): void
    {
        $host = $this->actor();
        $this->actingAs($host);
        $url = $this->endpoint($this->property($host)->id);
        $this->postJson($url, ['name' => ' Suite '])->assertCreated();
        $this->postJson($url, ['name' => 'Suite'])->assertConflict()
            ->assertExactJson(['message' => 'Cơ sở đã có loại phòng cùng tên.']);
        if (DB::getDriverName() === 'mysql') {
            $this->postJson($url, ['name' => 'suite'])->assertConflict();
        }
        $this->postJson($this->endpoint($this->property($host)->id), ['name' => 'Suite'])->assertCreated();
        $this->assertDatabaseCount('room_types', 2);
    }

    public function test_auth_validation_and_privacy_precedence(): void
    {
        $bad = $this->endpoint('01').'?page=2';
        $this->postJson($bad)->assertUnauthorized();
        foreach (['CUSTOMER', 'ADMIN'] as $role) {
            $actor = $this->actor($role);
            $this->actingAs($actor)->postJson($bad)->assertForbidden();
            try {
                $this->app->make(HostRoomTypeService::class)->create($actor, new CreateHostRoomTypeRequest(1, 'Suite', null));
                $this->fail('Expected denial.');
            } catch (HttpResponseException $error) {
                $this->assertSame(403, $error->getResponse()->getStatusCode());
            }
        }
        foreach (['BLOCKED', 'INACTIVE'] as $status) {
            $this->actingAs($this->actor('HOST', $status))->postJson($bad)->assertForbidden()
                ->assertJsonPath('code', 'ACCOUNT_'.$status);
        }
        $this->actingAs($this->actor());
        foreach (['0', '01', 'abc', '9223372036854775808'] as $id) {
            $this->postJson($this->endpoint($id).'?page=2')->assertNotFound()
                ->assertExactJson(['message' => 'Không tìm thấy cơ sở lưu trú.']);
        }
        $other = $this->property($this->actor());
        foreach ([$other->id, 9999] as $id) {
            $this->postJson($this->endpoint($id))->assertUnprocessable();
            $this->postJson($this->endpoint($id), ['name' => 'Suite'])->assertNotFound()
                ->assertExactJson(['message' => 'Không tìm thấy cơ sở lưu trú.']);
        }
        $this->assertDatabaseCount('room_types', 0);
    }

    public function test_projection_failure_rolls_back_without_success_log(): void
    {
        $host = $this->actor();
        $property = $this->property($host);
        $mapper = $this->createMock(ObjectMapperInterface::class);
        $mapper->expects($this->once())->method('map')->willReturnCallback(function (): never {
            $this->assertDatabaseCount('room_types', 1);
            throw new RuntimeException('Projection failed');
        });
        $this->app->instance(ObjectMapperInterface::class, $mapper);
        Log::spy();

        try {
            $this->app->make(HostRoomTypeService::class)->create($host, new CreateHostRoomTypeRequest($property->id, 'Suite', null));
            $this->fail('Expected projection failure.');
        } catch (RuntimeException $error) {
            $this->assertSame('Projection failed', $error->getMessage());
        }
        $this->assertDatabaseCount('room_types', 0);
        Log::shouldNotHaveReceived('info');
    }

    #[Group('room-type-real-commit')]
    public function test_success_log_waits_for_real_outer_commit(): void
    {
        $host = $this->actor();
        $property = $this->property($host);
        Log::spy();
        DB::beginTransaction();
        try {
            $result = $this->app->make(HostRoomTypeService::class)
                ->create($host, new CreateHostRoomTypeRequest($property->id, 'Suite', null));
            Log::shouldNotHaveReceived('info');
            DB::commit();
            $this->assertFalse(DB::connection()->getPdo()->inTransaction());
            $this->assertDatabaseHas('room_types', ['id' => $result->id]);
            Log::shouldHaveReceived('info')->with('Thêm loại phòng thành công.', [
                'property_id' => $property->id, 'room_type_id' => $result->id,
            ])->once();
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }
    }
}
