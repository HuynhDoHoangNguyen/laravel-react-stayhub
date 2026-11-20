<?php

namespace Tests\Feature\Catalog;

use App\DTOs\Catalog\Responses\HostPropertyResponse;
use App\DTOs\Catalog\Requests\HostPropertyDetailQuery;
use App\DTOs\Catalog\Requests\CreateHostPropertyRequest;
use App\DTOs\Catalog\Requests\UpdateHostPropertyRequest;
use App\DTOs\Catalog\Requests\ChangeHostPropertyStatusRequest;
use App\Exceptions\GlobalExceptionHandler;
use App\Enums\PropertyStatus;
use App\DTOs\Catalog\Responses\HostPropertyDetailResponse;
use App\Mappers\Catalog\CatalogMapper;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\User;
use App\Services\Catalog\HostPropertyQueryService;
use App\Services\Catalog\HostPropertyService;
use App\Repositories\Catalog\CatalogRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Exceptions\AppException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Symfony\Component\ObjectMapper\ObjectMapper;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;

class HostPropertiesTest extends TestCase
{
    use RefreshDatabase { beginDatabaseTransaction as beginTestTransaction; }

    public function beginDatabaseTransaction()
    {
        if (!in_array('host-property-real-commit', $this->groups(), true)) {
            $this->beginTestTransaction();
        }
    }

    public function createApplication()
    {
        foreach (['APP_ENV' => 'testing',
            'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'] as $key => $expected) {
            if (($_ENV[$key] ?? getenv($key)) !== $expected) {
                throw new RuntimeException('Host property tests require isolated in-memory testing configuration.');
            }
        }
        if (is_file(dirname(__DIR__, 3).'/bootstrap/cache/config.php')) {
            throw new RuntimeException('Cached configuration is unsafe for Host property tests.');
        }
        $driver = $_ENV['DB_CONNECTION'] ?? getenv('DB_CONNECTION');
        $database = $_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE');
        $sqlite = $driver === 'sqlite' && $database === ':memory:';
        $mysql = $driver === 'mysql' && is_string($database)
            && $database === getenv('STAYHUB_HOST_PROPERTY_TEST_DATABASE')
            && preg_match('/\Astayhub_mgmt02_test_[a-f0-9]{32}\z/', $database) === 1;
        if (!$sqlite && !$mysql) {
            throw new RuntimeException('Host property tests require an explicitly isolated test database.');
        }
        $app = parent::createApplication();
        $config = $app['config'];
        $connection = $config->get("database.connections.{$driver}");
        if ($config->get('database.default') !== $driver
            || ($connection['database'] ?? null) !== $database
            || !empty($connection['url'])
            || ($mysql && (($connection['host'] ?? null) !== '127.0.0.1' || !empty($connection['unix_socket'])))
            || $config->get('cache.default') !== 'array' || $config->get('session.driver') !== 'array') {
            throw new RuntimeException('Effective Host property database configuration is unsafe.');
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

    public function test_guest_is_rejected(): void
    {
        $this->getJson('/api/host/properties')->assertUnauthorized();
        $this->getJson('/api/host/properties/01')->assertUnauthorized();
        $this->postJson('/api/host/properties', [])->assertUnauthorized();
        $this->putJson('/api/host/properties/01', [])->assertUnauthorized();
        $this->patchJson('/api/host/properties/01/status', [])->assertUnauthorized();
    }

    public static function deniedRoles(): array
    {
        return [['CUSTOMER'], ['ADMIN']];
    }

    #[DataProvider('deniedRoles')]
    public function test_wrong_roles_are_rejected_before_the_service_queries(string $role): void
    {
        $actor = $this->actor($role);
        $this->actingAs($actor)->getJson('/api/host/properties')->assertForbidden()
            ->assertExactJson(['message' => 'Bạn không có quyền truy cập.']);
        $this->getJson('/api/host/properties/01')->assertForbidden()
            ->assertExactJson(['message' => 'Bạn không có quyền truy cập.']);
        $this->postJson('/api/host/properties', [])->assertForbidden();
        $this->patchJson('/api/host/properties/01/status', [])->assertForbidden()->assertExactJson(['message' => 'Bạn không có quyền truy cập.']);
        $this->putJson('/api/host/properties/01', [])->assertForbidden()->assertExactJson(['message' => 'Bạn không có quyền truy cập.']);
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $this->app->make(HostPropertyQueryService::class)->listForHost($actor);
            $this->fail('Expected role rejection.');
        } catch (AppException $exception) {
            $this->assertSame(403, $exception->status);
            $this->assertSame([], DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
        $this->expectException(AppException::class);
        $this->app->make(HostPropertyQueryService::class)->detailForHost($actor, new HostPropertyDetailQuery(1));
    }

    public static function inactiveStatuses(): array
    {
        return [['BLOCKED', 'ACCOUNT_BLOCKED'], ['INACTIVE', 'ACCOUNT_INACTIVE']];
    }

    public function test_catalog_role_denial_keeps_the_real_login_session(): void
    {
        config(['sanctum.stateful' => ['localhost:5173']]);
        $customer = $this->actor('CUSTOMER');
        $cookie = null;
        $browser = function (string $method, string $uri, array $data = []) use (&$cookie) {
            Auth::forgetGuards();
            Auth::shouldUse('web');
            $this->withCredentials();
            if ($cookie !== null) {
                $this->withCookie(config('session.cookie'), $cookie);
            }
            $response = $this->json($method, $uri, $data, [
                'Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/',
            ]);
            if ($session = $response->getCookie(config('session.cookie'))) {
                $cookie = $session->getValue();
            }

            return $response;
        };

        $browser('POST', '/login', ['email' => $customer->email, 'password' => 'password'])->assertOk();
        $this->assertNotNull($cookie);
        $browser('GET', '/api/host/properties')->assertForbidden()
            ->assertExactJson(['message' => 'Bạn không có quyền truy cập.']);
        $browser('GET', '/api/me')->assertOk()->assertJsonPath('user.id', $customer->id);
    }

    public function test_property_status_conflict_keeps_json_for_html_requests(): void
    {
        $host = $this->actor();
        $property = $this->ownedProperty($host, 'BLOCKED');

        $this->actingAs($host)->withHeader('Accept', 'text/html')
            ->patch('/api/host/properties/'.$property->id.'/status', ['status' => 'ACTIVE', 'expected_status' => 'BLOCKED'])
            ->assertConflict()->assertHeader('Content-Type', 'application/json')->assertExactJson([
                'message' => 'Host không thể thay đổi trạng thái cơ sở đang chờ duyệt hoặc đã bị khóa.',
            ]);
        $this->assertSame(PropertyStatus::BLOCKED, $property->fresh()->status);
    }

    #[DataProvider('inactiveStatuses')]
    public function test_account_status_is_enforced(string $status, string $code): void
    {
        $this->actingAs($this->actor('HOST', $status))->getJson('/api/host/properties')
            ->assertForbidden()->assertJsonPath('code', $code);
        $this->actingAs($this->actor('HOST', $status))->getJson('/api/host/properties/01')
            ->assertForbidden()->assertJsonPath('code', $code);
        $this->actingAs($this->actor('HOST', $status))->postJson('/api/host/properties', [])
            ->assertForbidden()->assertJsonPath('code', $code);
        $this->putJson('/api/host/properties/01', [])->assertForbidden()->assertJsonPath('code', $code);
        $this->patchJson('/api/host/properties/01/status', [])->assertForbidden()->assertJsonPath('code', $code);
    }

    public function test_empty_owned_collection_keeps_the_baseline_envelope(): void
    {
        $this->actingAs($this->actor())->getJson('/api/host/properties')->assertOk()->assertExactJson([
            'success' => true, 'message' => 'Danh sách cơ sở lưu trú của host.', 'data' => [],
        ]);
    }

    public function test_each_host_gets_only_its_own_properties_in_all_statuses_sorted_by_name(): void
    {
        $host = $this->actor();
        $other = $this->actor();
        $expected = [];
        foreach (['PENDING', 'BLOCKED', 'INACTIVE', 'ACTIVE'] as $index => $status) {
            $property = Property::create([
                'host_id' => $host->id, 'name' => 'Property '.(4 - $index), 'type' => 'HOTEL',
                'address' => 'Private address', 'status' => $status,
            ]);
            $expected[] = ['id' => $property->id, 'name' => $property->name];
        }
        $private = Property::create([
            'host_id' => $other->id, 'name' => 'Other host', 'type' => 'HOTEL', 'address' => 'Other address',
        ]);
        $this->actingAs($host)->getJson('/api/host/properties?host_id='.$other->id.'&status=ACTIVE')
            ->assertOk()->assertExactJson([
                'success' => true, 'message' => 'Danh sách cơ sở lưu trú của host.', 'data' => array_reverse($expected),
            ]);
        $this->actingAs($other)->getJson('/api/host/properties')->assertOk()->assertExactJson([
            'success' => true, 'message' => 'Danh sách cơ sở lưu trú của host.',
            'data' => [['id' => $private->id, 'name' => $private->name]],
        ]);
    }

    public function test_container_maps_host_projection_through_real_symfony_without_queries(): void
    {
        $symfony = $this->app->make(ObjectMapperInterface::class);
        $this->assertInstanceOf(ObjectMapper::class, $symfony);
        $spy = $this->createMock(ObjectMapperInterface::class);
        $spy->expects($this->once())->method('map')->willReturnCallback(
            function (object $source, string $target) use ($symfony): object {
                $this->assertSame(['id', 'name'], array_keys(get_object_vars($source)));
                $this->assertSame(HostPropertyResponse::class, $target);
                return $symfony->map($source, $target);
            },
        );
        $this->app->instance(ObjectMapperInterface::class, $spy);
        $property = new Property();
        $property->setRawAttributes(['id' => 7, 'name' => 'Fixture', 'host_id' => 123]);
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $dto = $this->app->make(CatalogMapper::class)->toHostProperty($property);
            $this->assertSame('{"id":7,"name":"Fixture"}', json_encode($dto));
            $this->assertSame([], DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
    }

    public static function propertyStatuses(): array
    {
        return [['ACTIVE'], ['INACTIVE'], ['PENDING'], ['BLOCKED']];
    }

    #[DataProvider('propertyStatuses')]
    public function test_owned_detail_keeps_exact_contract_for_every_status(string $status): void
    {
        $host = $this->actor();
        $property = Property::create([
            'host_id' => $host->id, 'name' => 'Owned fixture', 'type' => 'HOMESTAY',
            'address' => 'Property address', 'description' => null, 'phone' => null, 'status' => $status,
        ]);
        PropertyImage::create(['property_id' => $property->id, 'image_path' => 'https://example.com/second.jpg']);
        PropertyImage::create(['property_id' => $property->id, 'image_path' => 'https://example.com/first.jpg', 'is_primary' => true]);
        PropertyImage::create(['property_id' => $property->id, 'image_path' => 'private/hidden.jpg', 'is_primary' => true]);
        $this->actingAs($host)->getJson('/api/host/properties/'.$property->id.'?host_id=999')->assertOk()->assertExactJson([
            'success' => true, 'message' => 'Chi tiết cơ sở lưu trú của host.',
            'data' => [
                'id' => $property->id, 'name' => 'Owned fixture', 'type' => 'HOMESTAY',
                'address' => 'Property address', 'description' => null, 'phone' => null,
                'check_in_time' => '14:00:00', 'check_out_time' => '12:00:00', 'status' => $status,
                'images' => ['https://example.com/first.jpg', 'https://example.com/second.jpg'],
            ],
        ]);
    }

    public function test_missing_other_owner_and_invalid_ids_have_the_same_404(): void
    {
        $host = $this->actor();
        $other = $this->actor();
        $property = Property::create(['host_id' => $other->id, 'name' => 'Other private', 'type' => 'HOTEL', 'address' => 'Private']);
        $this->actingAs($host);
        foreach ([(string) $property->id, '999999', '0', '01', '-1', 'abc', str_repeat('9', 40)] as $id) {
            $this->getJson('/api/host/properties/'.$id)->assertNotFound()
                ->assertExactJson(['message' => 'Không tìm thấy cơ sở lưu trú.']);
        }
    }

    public function test_detail_maps_and_serializes_through_real_symfony_without_queries(): void
    {
        $host = $this->actor();
        $property = Property::create(['host_id' => $host->id, 'name' => 'Owned', 'type' => 'HOTEL', 'address' => 'Address']);
        $loaded = $this->app->make(\App\Repositories\Catalog\CatalogRepository::class)->findHostProperty($host->id, $property->id);
        $symfony = $this->app->make(ObjectMapperInterface::class);
        $spy = $this->createMock(ObjectMapperInterface::class);
        $spy->expects($this->once())->method('map')->willReturnCallback(
            function (object $source, string $target) use ($symfony): object {
                $this->assertSame(HostPropertyDetailResponse::class, $target);
                $this->assertFalse(property_exists($source, 'host_id'));
                return $symfony->map($source, $target);
            },
        );
        $this->app->instance(ObjectMapperInterface::class, $spy);
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $dto = $this->app->make(CatalogMapper::class)->toHostPropertyDetail($loaded);
            $body = json_decode(json_encode($dto, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame([], $body['images']);
            $this->assertCount(10, $body);
            $this->assertSame([], DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
    }

    private function createPayload(array $overrides = []): array
    {
        return array_replace(['name' => 'New fixture', 'type' => 'HOTEL', 'address' => 'Fixture address'], $overrides);
    }

    public function test_create_assigns_actor_active_defaults_and_matches_detail(): void
    {
        $host = $this->actor();
        $other = $this->actor();
        $this->actingAs($host);
        $response = $this->postJson('/api/host/properties', $this->createPayload(['name' => ' New fixture ']))->assertCreated();
        $id = $response->json('data.id');
        $expected = [
            'id' => $id, 'name' => 'New fixture', 'type' => 'HOTEL', 'address' => 'Fixture address',
            'description' => null, 'phone' => null, 'check_in_time' => '14:00:00',
            'check_out_time' => '12:00:00', 'status' => 'ACTIVE', 'images' => [],
        ];
        $response->assertExactJson(['success' => true, 'message' => 'Tạo cơ sở lưu trú thành công.', 'data' => $expected]);
        $this->assertDatabaseHas('properties', ['id' => $id, 'host_id' => $host->id, 'status' => 'ACTIVE']);
        $this->getJson('/api/host/properties/'.$id)->assertJsonPath('data', $expected);
        $this->actingAs($other)->getJson('/api/host/properties/'.$id)->assertNotFound();
    }

    public function test_create_accepts_boundaries_nullable_contacts_and_times(): void
    {
        $this->actingAs($this->actor())->postJson('/api/host/properties', $this->createPayload([
            'name' => str_repeat('N', 255), 'address' => str_repeat('A', 500), 'type' => 'HOMESTAY',
            'description' => str_repeat('M', 10000), 'phone' => '+0123456789012345678',
            'check_in_time' => '00:00', 'check_out_time' => '23:59',
        ]))->assertCreated()->assertJsonPath('data.phone', '+0123456789012345678')
            ->assertJsonPath('data.check_in_time', '00:00:00')->assertJsonPath('data.check_out_time', '23:59:00');
    }

    public static function invalidCreatePayloads(): array
    {
        return [
            [['name' => '   '], 'name'], [['name' => str_repeat('n', 256)], 'name'],
            [['name' => []], 'name'], [['type' => 'OTHER'], 'type'],
            [['address' => ' '], 'address'], [['address' => str_repeat('a', 501)], 'address'],
            [['description' => str_repeat('m', 10001)], 'description'], [['phone' => 123], 'phone'],
            [['phone' => str_repeat('1', 21)], 'phone'], [['check_in_time' => '24:00'], 'check_in_time'],
            [['check_out_time' => '12:00:00'], 'check_out_time'], [['check_out_time' => null], 'check_out_time'],
            [['host_id' => 999], 'host_id'], [['status' => 'PENDING'], 'status'],
            [['id' => 123], 'id'], [['images' => []], 'images'], [['unknown' => true], 'unknown'],
        ];
    }

    #[DataProvider('invalidCreatePayloads')]
    public function test_invalid_create_never_inserts(array $overrides, string $field): void
    {
        $this->actingAs($this->actor())->postJson('/api/host/properties', $this->createPayload($overrides))
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('properties', 0);
    }

    public function test_create_required_fields_are_reported_together(): void
    {
        $this->actingAs($this->actor())->postJson('/api/host/properties', [])
            ->assertUnprocessable()->assertJsonValidationErrors(['name', 'type', 'address']);
        $this->assertDatabaseCount('properties', 0);
    }

    public static function transactionOutcomes(): array
    {
        return ['commit' => [true], 'rollback' => [false]];
    }

    #[Group('host-property-real-commit')]
    #[DataProvider('transactionOutcomes')]
    public function test_create_success_log_waits_for_outer_commit_and_rollback_discards_it(bool $commit): void
    {
        $host = $this->actor();
        $connection = DB::connection();
        $this->assertSame(0, $connection->transactionLevel());
        Log::spy();
        $command = new CreateHostPropertyRequest('Commit fixture', 'HOTEL', 'Address', null, null, '14:00:00', '12:00:00');
        try {
            $connection->beginTransaction();
            $dto = $this->app->make(HostPropertyService::class)->create($host, $command);
            Log::shouldNotHaveReceived('info');
            $this->assertTrue($connection->getPdo()->inTransaction());
            if ($commit) {
                $connection->commit();
                $this->assertFalse($connection->getPdo()->inTransaction());
                Log::shouldHaveReceived('info')->once()->with('Tạo cơ sở lưu trú thành công.', ['property_id' => $dto->id]);
            } else {
                $connection->rollBack();
                $this->assertDatabaseMissing('properties', ['id' => $dto->id]);
                $connection->transaction(static fn () => null);
                Log::shouldNotHaveReceived('info');
            }
        } finally {
            if ($connection->transactionLevel() > 0) $connection->rollBack(0);
            $connection->table('properties')->where('host_id', $host->id)->delete();
            $connection->table('users')->where('id', $host->id)->delete();
            // Real commits advance identity sequences; rebuild the isolated schema for the next test.
            RefreshDatabaseState::$migrated = false;
        }
    }

    public function test_failure_after_insert_rolls_back_and_has_no_success_log(): void
    {
        $host = $this->actor();
        $real = $this->app->make(CatalogRepository::class);
        $repo = $this->createMock(CatalogRepository::class);
        $failure = new RuntimeException('Controlled post-insert failure.');
        $repo->expects($this->once())->method('createHostProperty')->willReturnCallback(
            function (array $attributes) use ($real, $failure): Property {
                $real->createHostProperty($attributes);
                $this->assertDatabaseCount('properties', 1);
                throw $failure;
            },
        );
        $this->app->instance(CatalogRepository::class, $repo);
        Log::spy();
        try {
            $this->app->make(HostPropertyService::class)->create($host,
                new CreateHostPropertyRequest('Failure fixture', 'HOTEL', 'Address', null, null, '14:00:00', '12:00:00'));
            $this->fail('Expected failure.');
        } catch (RuntimeException $caught) {
            $this->assertSame($failure, $caught);
        }
        $this->assertDatabaseCount('properties', 0);
        Log::shouldNotHaveReceived('info');
    }
    private function updatePayload(array $overrides = []): array
    {
        return array_replace(['name' => ' Updated fixture ', 'type' => 'HOMESTAY', 'address' => ' Updated address ',
            'description' => null, 'phone' => ' +0901234567 ', 'check_in_time' => '15:30', 'check_out_time' => '11:45'], $overrides);
    }

    private function ownedProperty(User $host, string $status = 'ACTIVE'): Property
    {
        return Property::create(['host_id' => $host->id, 'name' => 'Before', 'type' => 'HOTEL',
            'address' => 'Before address', 'status' => $status])->refresh();
    }

    #[DataProvider('propertyStatuses')]
    public function test_update_preserves_owner_status_images_and_exact_detail_contract(string $status): void
    {
        $host = $this->actor();
        $property = $this->ownedProperty($host, $status);
        $untouched = $this->ownedProperty($host)->getAttributes();
        $image = PropertyImage::create(['property_id' => $property->id, 'image_path' => 'https://example.com/kept.jpg', 'is_primary' => true]);
        $imageBefore = $image->refresh()->getAttributes();
        $expected = ['id' => $property->id, 'name' => 'Updated fixture', 'type' => 'HOMESTAY', 'address' => 'Updated address',
            'description' => null, 'phone' => '+0901234567', 'check_in_time' => '15:30:00',
            'check_out_time' => '11:45:00', 'status' => $status, 'images' => ['https://example.com/kept.jpg']];
        $this->actingAs($host)->putJson('/api/host/properties/'.$property->id, $this->updatePayload())->assertOk()
            ->assertExactJson(['success' => true, 'message' => 'Cập nhật cơ sở lưu trú thành công.', 'data' => $expected]);
        $this->getJson('/api/host/properties/'.$property->id)->assertJsonPath('data', $expected);
        $this->assertDatabaseHas('properties', ['id' => $property->id, 'host_id' => $host->id, 'status' => $status]);
        $this->assertSame($imageBefore, $image->fresh()->getAttributes());
        $this->assertSame($untouched, Property::findOrFail($untouched['id'])->getAttributes());
    }

    #[DataProvider('invalidCreatePayloads')]
    public function test_invalid_update_preserves_all_persisted_attributes(array $overrides, string $field): void
    {
        $host = $this->actor();
        $property = $this->ownedProperty($host);
        $before = $property->getAttributes();
        $this->actingAs($host)->putJson('/api/host/properties/'.$property->id, $this->updatePayload($overrides))
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertSame($before, $property->fresh()->getAttributes());
    }

    public function test_update_requires_all_seven_keys_without_create_defaults(): void
    {
        $host = $this->actor();
        $property = $this->ownedProperty($host);
        $this->actingAs($host)->putJson('/api/host/properties/'.$property->id, [])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'type', 'address', 'description', 'phone', 'check_in_time', 'check_out_time']);
        $this->assertSame('Before', $property->fresh()->name);
    }

    public function test_update_accepts_boundaries_and_explicit_nullable_values(): void
    {
        $host = $this->actor();
        $property = $this->ownedProperty($host);
        $this->actingAs($host)->putJson('/api/host/properties/'.$property->id, $this->updatePayload([
            'name' => str_repeat('N', 255), 'address' => str_repeat('A', 500),
            'description' => str_repeat('M', 10000), 'phone' => '+0123456789012345678',
            'check_in_time' => '00:00', 'check_out_time' => '23:59',
        ]))->assertOk()->assertJsonPath('data.phone', '+0123456789012345678')->assertJsonPath('data.check_out_time', '23:59:00');
        $this->putJson('/api/host/properties/'.$property->id, $this->updatePayload(['phone' => null, 'description' => null]))
            ->assertOk()->assertJsonPath('data.phone', null)->assertJsonPath('data.description', null);
    }

    public function test_update_missing_other_owner_and_bad_ids_share_privacy_404(): void
    {
        $host = $this->actor();
        $property = $this->ownedProperty($this->actor());
        $before = $property->getAttributes();
        $this->actingAs($host);
        foreach ([(string) $property->id, '999999', '0', '01', '-1', 'abc', str_repeat('9', 40)] as $id) {
            $this->putJson('/api/host/properties/'.$id, $this->updatePayload())->assertNotFound()
                ->assertExactJson(['message' => 'Không tìm thấy cơ sở lưu trú.']);
        }
        $this->assertSame($before, $property->fresh()->getAttributes());
        $this->assertNull($this->app->make(HostPropertyService::class)->update($host, $property->id, $this->updateCommand()));
        // Valid canonical IDs validate input before the ownership lookup.
        $this->putJson('/api/host/properties/'.$property->id, [])->assertUnprocessable();
        $this->putJson('/api/host/properties/999999', [])->assertUnprocessable();
    }

    private function updateCommand(): UpdateHostPropertyRequest
    {
        return new UpdateHostPropertyRequest('Updated fixture', 'HOMESTAY', 'Updated address', null, null, '15:30:00', '11:45:00');
    }

    #[DataProvider('deniedRoles')]
    public function test_direct_update_service_denies_wrong_role_without_queries(string $role): void
    {
        $actor = $this->actor($role);
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $this->app->make(HostPropertyService::class)->update($actor, 1, $this->updateCommand());
            $this->fail('Expected role denial.');
        } catch (AppException $exception) {
            $this->assertSame(403, $exception->status);
            $this->assertSame([], DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
    }

    public function test_failure_after_update_rolls_back_without_success_log(): void
    {
        $host = $this->actor();
        $property = $this->ownedProperty($host, 'BLOCKED');
        $before = $property->getAttributes();
        $real = $this->app->make(CatalogRepository::class);
        $repo = $this->createMock(CatalogRepository::class);
        $repo->method('lockHostProperty')->willReturnCallback(fn (int $hostId, int $id) => $real->lockHostProperty($hostId, $id));
        $failure = new RuntimeException('Controlled post-update failure.');
        $repo->expects($this->once())->method('updateHostProperty')->willReturnCallback(
            function (Property $candidate, array $attributes) use ($real, $failure): Property {
                $real->updateHostProperty($candidate, $attributes);
                $this->assertDatabaseHas('properties', ['id' => $candidate->id, 'name' => 'Updated fixture']);
                throw $failure;
            });
        $this->app->instance(CatalogRepository::class, $repo);
        Log::spy();
        try {
            $this->app->make(HostPropertyService::class)->update($host, $property->id, $this->updateCommand());
            $this->fail('Expected failure.');
        } catch (RuntimeException $caught) {
            $this->assertSame($failure, $caught);
        }
        $this->assertSame($before, $property->fresh()->getAttributes());
        Log::shouldNotHaveReceived('info');
    }

    #[Group('host-property-real-commit')]
    #[DataProvider('transactionOutcomes')]
    public function test_update_log_waits_for_real_outer_commit_and_rollback_discards_it(bool $commit): void
    {
        $host = $this->actor();
        $property = $this->ownedProperty($host, 'PENDING');
        $connection = DB::connection();
        $this->assertSame(0, $connection->transactionLevel());
        Log::spy();
        try {
            $connection->beginTransaction();
            $result = $this->app->make(HostPropertyService::class)->update($host, $property->id, $this->updateCommand());
            $this->assertSame($property->id, $result->id);
            $this->assertTrue($connection->getPdo()->inTransaction());
            Log::shouldNotHaveReceived('info');
            if ($commit) {
                $connection->commit();
                $this->assertFalse($connection->getPdo()->inTransaction());
                Log::shouldHaveReceived('info')->once()->with('Cập nhật cơ sở lưu trú thành công.', ['property_id' => $property->id]);
                $this->assertSame('Updated fixture', $property->fresh()->name);
            } else {
                $connection->rollBack();
                $this->assertSame('Before', $property->fresh()->name);
                $connection->transaction(static fn () => null);
                Log::shouldNotHaveReceived('info');
            }
            $this->assertSame('PENDING', $property->fresh()->status->value);
        } finally {
            if ($connection->transactionLevel() > 0) $connection->rollBack(0);
            $connection->table('properties')->where('host_id', $host->id)->delete();
            $connection->table('users')->where('id', $host->id)->delete();
            RefreshDatabaseState::$migrated = false;
        }
    }

    public static function hostStatusTransitions(): array
    {
        return [
            ['ACTIVE', 'INACTIVE', 200], ['INACTIVE', 'ACTIVE', 200],
            ['ACTIVE', 'ACTIVE', 200], ['INACTIVE', 'INACTIVE', 200],
            ['PENDING', 'ACTIVE', 409], ['PENDING', 'INACTIVE', 409],
            ['BLOCKED', 'ACTIVE', 409], ['BLOCKED', 'INACTIVE', 409],
        ];
    }

    #[DataProvider('hostStatusTransitions')]
    public function test_status_transition_matrix_preserves_other_attributes_and_projection(string $from, string $to, int $status): void
    {
        $host = $this->actor();
        $property = $this->ownedProperty($host, $from);
        $other = $this->ownedProperty($host)->getAttributes();
        $image = PropertyImage::create(['property_id' => $property->id, 'image_path' => 'https://example.com/kept.jpg', 'is_primary' => true])->refresh();
        $before = $property->getAttributes();
        $imageBefore = $image->getAttributes();
        $this->travel(2)->seconds();
        Log::spy();
        $response = $this->actingAs($host)->patchJson('/api/host/properties/'.$property->id.'/status',
            ['status' => $to, 'expected_status' => $from])->assertStatus($status);
        if ($status === 200) {
            $expected = ['id' => $property->id, 'name' => 'Before', 'type' => 'HOTEL', 'address' => 'Before address',
                'description' => null, 'phone' => null, 'check_in_time' => '14:00:00', 'check_out_time' => '12:00:00',
                'status' => $to, 'images' => ['https://example.com/kept.jpg']];
            $response->assertExactJson(['success' => true, 'message' => 'Cập nhật trạng thái cơ sở lưu trú thành công.', 'data' => $expected]);
            $this->getJson('/api/host/properties/'.$property->id)->assertJsonPath('data', $expected);
            $after = $property->fresh()->getAttributes();
            if ($from === $to) {
                $this->assertSame($before, $after);
            } else {
                $this->assertNotSame($before['updated_at'], $after['updated_at']);
                $after['status'] = $before['status'];
                $after['updated_at'] = $before['updated_at'];
                $this->assertSame($before, $after);
            }
        } else {
            $response->assertExactJson(['message' => 'Host không thể thay đổi trạng thái cơ sở đang chờ duyệt hoặc đã bị khóa.']);
            $this->assertSame($before, $property->fresh()->getAttributes());
        }
        $this->assertSame($imageBefore, $image->fresh()->getAttributes());
        $this->assertSame($other, Property::findOrFail($other['id'])->getAttributes());
        if ($status !== 200 || $from === $to) Log::shouldNotHaveReceived('info');
    }

    public static function invalidStatusPayloads(): array
    {
        return [
            [[], ['status', 'expected_status']],
            [['status' => null, 'expected_status' => null], ['status', 'expected_status']],
            [['status' => [], 'expected_status' => 123], ['status', 'expected_status']],
            [['status' => 'PENDING', 'expected_status' => 'ACTIVE'], ['status']],
            [['status' => 'BLOCKED', 'expected_status' => 'ACTIVE'], ['status']],
            [['status' => 'OTHER', 'expected_status' => 'OTHER'], ['status', 'expected_status']],
            [['status' => 'INACTIVE', 'expected_status' => 'ACTIVE', 'host_id' => 99], ['host_id']],
            [['status' => 'INACTIVE', 'expected_status' => 'ACTIVE', 'name' => 'Injected'], ['name']],
            [['status' => 'INACTIVE', 'expected_status' => 'ACTIVE', 'images' => []], ['images']],
        ];
    }

    #[DataProvider('invalidStatusPayloads')]
    public function test_invalid_status_payload_never_writes(array $input, array $fields): void
    {
        $host = $this->actor();
        $property = $this->ownedProperty($host);
        $before = $property->getAttributes();
        $this->actingAs($host)->patchJson('/api/host/properties/'.$property->id.'/status', $input)
            ->assertUnprocessable()->assertJsonValidationErrors($fields);
        $this->assertSame($before, $property->fresh()->getAttributes());
    }

    public function test_stale_status_precondition_is_checked_before_noop_and_transition(): void
    {
        $host = $this->actor();
        $property = $this->ownedProperty($host);
        $this->actingAs($host)->patchJson('/api/host/properties/'.$property->id.'/status',
            ['status' => 'INACTIVE', 'expected_status' => 'ACTIVE'])->assertOk();
        $this->patchJson('/api/host/properties/'.$property->id.'/status',
            ['status' => 'INACTIVE', 'expected_status' => 'ACTIVE'])->assertConflict()
            ->assertExactJson(['message' => 'Trạng thái cơ sở đã thay đổi. Vui lòng tải lại chi tiết.']);
        $this->assertSame('INACTIVE', $property->fresh()->status->value);
        $property->status = PropertyStatus::BLOCKED;
        $property->save();
        $this->patchJson('/api/host/properties/'.$property->id.'/status',
            ['status' => 'ACTIVE', 'expected_status' => 'INACTIVE'])->assertConflict()
            ->assertExactJson(['message' => 'Trạng thái cơ sở đã thay đổi. Vui lòng tải lại chi tiết.']);
    }

    public function test_status_ownership_privacy_and_direct_service_checks(): void
    {
        $host = $this->actor();
        $property = $this->ownedProperty($this->actor());
        $before = $property->getAttributes();
        $this->actingAs($host);
        foreach ([(string) $property->id, '999999', '0', '01', '-1', 'abc', str_repeat('9', 40)] as $id) {
            $this->patchJson('/api/host/properties/'.$id.'/status', ['status' => 'INACTIVE', 'expected_status' => 'ACTIVE'])
                ->assertNotFound()->assertExactJson(['message' => 'Không tìm thấy cơ sở lưu trú.']);
        }
        $command = new ChangeHostPropertyStatusRequest(PropertyStatus::INACTIVE, PropertyStatus::ACTIVE);
        $this->assertNull($this->app->make(HostPropertyService::class)->changeStatus($host, $property->id, $command));
        $this->assertSame($before, $property->fresh()->getAttributes());
        foreach (['CUSTOMER', 'ADMIN'] as $role) {
            $actor = $this->actor($role);
            DB::enableQueryLog(); DB::flushQueryLog();
            try {
                $this->app->make(HostPropertyService::class)->changeStatus($actor, $property->id, $command);
                $this->fail('Expected role denial.');
            } catch (AppException $exception) {
                $this->assertSame(403, $exception->status);
                $this->assertSame([], DB::getQueryLog());
            } finally { DB::disableQueryLog(); }
        }
        try {
            $this->app->make(HostPropertyService::class)->changeStatus($property->host, $property->id,
                new ChangeHostPropertyStatusRequest(PropertyStatus::BLOCKED, PropertyStatus::ACTIVE));
            $this->fail('Expected an invalid property status target.');
        } catch (AppException $exception) {
            $this->assertSame('CATALOG_PROPERTY_STATUS_INVALID', $exception->errorCode);
            $this->assertSame(422, $exception->status);
            $response = app(GlobalExceptionHandler::class)->render($exception, Request::create('/api/host/properties/'.$property->id.'/status', 'PATCH'));
            $this->assertNotNull($response);
            $this->assertSame(422, $response->getStatusCode());
            $this->assertSame(['message' => 'Trạng thái đích phải là ACTIVE hoặc INACTIVE.'], $response->getData(true));
        }
    }

    public function test_status_change_updates_public_visibility_and_preserves_host_list(): void
    {
        $host = $this->actor();
        $property = $this->ownedProperty($host);
        $this->getJson('/api/public/properties/'.$property->id)->assertOk();
        $this->getJson('/api/public/properties')->assertJsonCount(1, 'data');
        $this->actingAs($host)->patchJson('/api/host/properties/'.$property->id.'/status',
            ['status' => 'INACTIVE', 'expected_status' => 'ACTIVE'])->assertOk();
        $this->getJson('/api/public/properties/'.$property->id)->assertNotFound();
        $this->getJson('/api/public/properties')->assertJsonCount(0, 'data');
        $this->getJson('/api/host/properties')->assertJsonCount(1, 'data');
        $this->patchJson('/api/host/properties/'.$property->id.'/status',
            ['status' => 'ACTIVE', 'expected_status' => 'INACTIVE'])->assertOk();
        $this->getJson('/api/public/properties/'.$property->id)->assertOk();
        $this->getJson('/api/public/properties')->assertJsonCount(1, 'data');
    }

    public function test_failure_after_status_write_rolls_back_without_success_log(): void
    {
        $host = $this->actor();
        $property = $this->ownedProperty($host);
        $before = $property->getAttributes();
        $real = $this->app->make(CatalogRepository::class);
        $repo = $this->createMock(CatalogRepository::class);
        $repo->method('lockHostProperty')->willReturnCallback(fn (int $hostId, int $id) => $real->lockHostProperty($hostId, $id));
        $failure = new RuntimeException('Controlled post-status-write failure.');
        $repo->expects($this->once())->method('changeHostPropertyStatus')->willReturnCallback(
            function (Property $candidate, PropertyStatus $status) use ($real, $failure): Property {
                $real->changeHostPropertyStatus($candidate, $status);
                $this->assertDatabaseHas('properties', ['id' => $candidate->id, 'status' => 'INACTIVE']);
                throw $failure;
            });
        $this->app->instance(CatalogRepository::class, $repo);
        Log::spy();
        try {
            $this->app->make(HostPropertyService::class)->changeStatus($host, $property->id,
                new ChangeHostPropertyStatusRequest(PropertyStatus::INACTIVE, PropertyStatus::ACTIVE));
            $this->fail('Expected failure.');
        } catch (RuntimeException $caught) { $this->assertSame($failure, $caught); }
        $this->assertSame($before, $property->fresh()->getAttributes());
        Log::shouldNotHaveReceived('info');
    }

    public static function statusTransactionOutcomes(): array
    {
        return ['commit' => [true, false], 'rollback' => [false, false], 'noop commit' => [true, true]];
    }

    #[Group('host-property-real-commit')]
    #[DataProvider('statusTransactionOutcomes')]
    public function test_status_log_waits_for_real_commit_and_noop_never_logs(bool $commit, bool $noop): void
    {
        $host = $this->actor();
        $property = $this->ownedProperty($host);
        $before = $property->getAttributes();
        $connection = DB::connection();
        $this->assertSame(0, $connection->transactionLevel());
        Log::spy();
        try {
            $connection->beginTransaction();
            $this->app->make(HostPropertyService::class)->changeStatus($host, $property->id,
                new ChangeHostPropertyStatusRequest($noop ? PropertyStatus::ACTIVE : PropertyStatus::INACTIVE, PropertyStatus::ACTIVE));
            $this->assertTrue($connection->getPdo()->inTransaction());
            Log::shouldNotHaveReceived('info');
            if ($commit) {
                $connection->commit();
                $this->assertFalse($connection->getPdo()->inTransaction());
                if ($noop) {
                    $this->assertSame($before, $property->fresh()->getAttributes());
                    Log::shouldNotHaveReceived('info');
                } else {
                    $this->assertSame('INACTIVE', $property->fresh()->status->value);
                    Log::shouldHaveReceived('info')->once()->with('Cập nhật trạng thái cơ sở lưu trú thành công.',
                        ['property_id' => $property->id, 'from_status' => 'ACTIVE', 'to_status' => 'INACTIVE']);
                }
            } else {
                $connection->rollBack();
                $this->assertSame($before, $property->fresh()->getAttributes());
                $connection->transaction(static fn () => null);
                Log::shouldNotHaveReceived('info');
            }
        } finally {
            if ($connection->transactionLevel() > 0) $connection->rollBack(0);
            $connection->table('properties')->where('host_id', $host->id)->delete();
            $connection->table('users')->where('id', $host->id)->delete();
            RefreshDatabaseState::$migrated = false;
        }
    }

}
