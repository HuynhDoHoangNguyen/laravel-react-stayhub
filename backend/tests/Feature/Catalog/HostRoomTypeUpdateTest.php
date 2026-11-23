<?php

namespace Tests\Feature\Catalog;

use App\DTOs\Catalog\Requests\UpdateHostRoomTypeRequest;
use App\Models\Property;
use App\Models\User;
use App\Services\Catalog\HostRoomTypeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\TestCase;
use App\Exceptions\AppException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;

final class HostRoomTypeUpdateTest extends TestCase
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
            && $database === getenv('STAYHUB_ROOM_TYPE_UPDATE_TEST_DATABASE')
            && preg_match('/\Astayhub_rt03_test_[a-f0-9]{32}\z/', $database) === 1;
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
        return '/api/host/room-types/'.$id;
    }


    public static function statuses(): array
    {
        return [['ACTIVE'], ['INACTIVE'], ['PENDING'], ['BLOCKED']];
    }

    #[DataProvider('statuses')]
    public function test_update_preserves_property_and_room_relation(string $status): void
    {
        $host = $this->actor();
        $property = $this->property($host, $status);
        $type = $property->roomTypes()->create(['name' => 'Zulu', 'description' => 'Old']);
        $room = $property->rooms()->create(['room_type_id' => $type->id, 'room_number' => '01', 'name' => 'Fixture',
            'capacity' => 2, 'price_per_night' => '100.00']);
        $this->actingAs($host)->putJson($this->endpoint($type->id), [
            'name' => '  Alpha  ', 'description' => "  First\nSecond  ",
        ])->assertOk()->assertExactJson(['success' => true, 'message' => 'Cập nhật loại phòng thành công.',
            'data' => ['id' => $type->id, 'name' => 'Alpha', 'description' => "First\nSecond"]]);
        $this->assertDatabaseHas('rooms', ['id' => $room->id, 'room_type_id' => $type->id, 'property_id' => $property->id]);
        $this->assertDatabaseHas('properties', ['id' => $property->id, 'host_id' => $host->id, 'status' => $status]);
        $this->assertDatabaseHas('room_types', ['id' => $type->id, 'property_id' => $property->id, 'name' => 'Alpha']);
        if ($status === 'ACTIVE') {
            $this->getJson('/api/public/properties/'.$property->id)->assertOk()
                ->assertJsonPath('data.rooms.0.room_type', ['id' => $type->id, 'name' => 'Alpha']);
        }
    }

    public function test_full_payload_and_boundaries(): void
    {
        $host = $this->actor();
        $type = $this->property($host)->roomTypes()->create(['name' => 'Old']);
        $url = $this->endpoint($type->id);
        $this->actingAs($host);
        $valid = ['name' => 'Next', 'description' => null];
        $this->putJson($url, ['name' => 'Next'])->assertUnprocessable()->assertJsonValidationErrors('description');
        foreach (['name' => [' ', 22, str_repeat('a', 256)], 'description' => [22, str_repeat('a', 10001)]] as $field => $values) {
            foreach ($values as $value) {
                $this->putJson($url, [$field => $value] + $valid)->assertUnprocessable()->assertJsonValidationErrors($field);
            }
        }
        $this->putJson($url, $valid + ['property_id' => 2])->assertUnprocessable()->assertJsonValidationErrors('property_id');
        $this->putJson($url.'?page=2', $valid)->assertUnprocessable()->assertJsonValidationErrors('page');
        $this->assertSame('Old', $type->fresh()->name);
        $max = ['name' => str_repeat('ộ', 255), 'description' => str_repeat('ộ', 10000)];
        $this->putJson($url, $max)->assertOk()->assertJsonPath('data.name', $max['name'])->assertJsonPath('data.description', $max['description']);
        $this->putJson($url, ['name' => 'Next', 'description' => '  '])->assertOk()->assertJsonPath('data.description', null);
    }

    public function test_conflicts_and_legacy_duplicates(): void
    {
        $host = $this->actor();
        $property = $this->property($host);
        $type = $property->roomTypes()->create(['name' => 'Suite']);
        $property->roomTypes()->create(['name' => 'Suite']);
        $property->roomTypes()->create(['name' => 'Other']);
        $this->property($host)->roomTypes()->create(['name' => 'Separate']);
        $url = $this->endpoint($type->id);
        $this->actingAs($host);
        $this->putJson($url, ['name' => ' Suite ', 'description' => 'Allowed'])->assertOk();
        $this->putJson($url, ['name' => 'Other', 'description' => null])->assertConflict()
            ->assertExactJson(['message' => 'Cơ sở đã có loại phòng cùng tên.']);
        if (DB::getDriverName() === 'mysql') {
            $this->putJson($url, ['name' => 'other', 'description' => null])->assertConflict();
        }
        $this->assertSame('Allowed', $type->fresh()->description);
        $this->putJson($url, ['name' => 'Separate', 'description' => null])->assertOk();
        $this->putJson($url, ['name' => 'separate', 'description' => null])->assertOk();
    }

    public function test_auth_and_privacy_precedence(): void
    {
        $bad = $this->endpoint('01').'?page=2';
        $this->putJson($bad)->assertUnauthorized();
        foreach (['ADMIN', 'CUSTOMER'] as $role) {
            $actor = $this->actor($role);
            $this->actingAs($actor)->putJson($bad)->assertForbidden();
            try {
                $this->app->make(HostRoomTypeService::class)->update($actor, new UpdateHostRoomTypeRequest(1, 'Suite', null));
                $this->fail('Expected role denial');
            } catch (AppException $error) {
                $this->assertSame(403, $error->status);
            }
        }
        foreach (['BLOCKED', 'INACTIVE'] as $status) {
            $this->actingAs($this->actor('HOST', $status))->putJson($bad)->assertForbidden()->assertJsonPath('code', 'ACCOUNT_'.$status);
        }
        $type = $this->property($this->actor())->roomTypes()->create(['name' => 'Other']);
        $this->actingAs($this->actor());
        foreach (['0', '01', 'abc', '9223372036854775808'] as $id) {
            $this->putJson($this->endpoint($id).'?page=2')->assertNotFound()
                ->assertExactJson(['message' => 'Không tìm thấy loại phòng.']);
        }
        foreach ([$type->id, 9999] as $id) {
            $this->putJson($this->endpoint($id))->assertUnprocessable();
            $this->putJson($this->endpoint($id), ['name' => 'Suite', 'description' => null])->assertNotFound()
                ->assertExactJson(['message' => 'Không tìm thấy loại phòng.']);
        }
        $this->assertSame('Other', $type->fresh()->name);
    }

    public function test_projection_failure_rolls_back(): void
    {
        $host = $this->actor();
        $type = $this->property($host)->roomTypes()->create(['name' => 'Old']);
        $mapper = $this->createMock(ObjectMapperInterface::class);
        $mapper->expects($this->once())->method('map')->willReturnCallback(function () use ($type): never {
            $this->assertSame('Next', $type->fresh()->name);
            throw new RuntimeException('Projection failed');
        });
        $this->app->instance(ObjectMapperInterface::class, $mapper);
        Log::spy();
        try {
            $this->app->make(HostRoomTypeService::class)->update($host, new UpdateHostRoomTypeRequest($type->id, 'Next', null));
            $this->fail('Expected projection failure');
        } catch (RuntimeException $error) {
            $this->assertSame('Projection failed', $error->getMessage());
        }
        $this->assertSame('Old', $type->fresh()->name);
        Log::shouldNotHaveReceived('info');
    }

    #[Group('room-type-real-commit')]
    public function test_noop_and_outer_commit_logging(): void
    {
        $host = $this->actor();
        $property = $this->property($host);
        $type = $property->roomTypes()->create(['name' => 'Old', 'description' => null]);
        $before = $type->getRawOriginal('updated_at');
        $this->travel(2)->seconds();
        $service = $this->app->make(HostRoomTypeService::class);
        Log::spy();
        $service->update($host, new UpdateHostRoomTypeRequest($type->id, 'Old', null));
        $this->assertSame($before, $type->fresh()->getRawOriginal('updated_at'));
        Log::shouldNotHaveReceived('info');
        DB::beginTransaction();
        try {
            $service->update($host, new UpdateHostRoomTypeRequest($type->id, 'New', null));
            Log::shouldNotHaveReceived('info');
            DB::commit();
            $this->assertFalse(DB::connection()->getPdo()->inTransaction());
            $this->assertSame('New', $type->fresh()->name);
            Log::shouldHaveReceived('info')->with('Cập nhật loại phòng thành công.', [
                'property_id' => $property->id, 'room_type_id' => $type->id,
            ])->once();
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }
    }
}
