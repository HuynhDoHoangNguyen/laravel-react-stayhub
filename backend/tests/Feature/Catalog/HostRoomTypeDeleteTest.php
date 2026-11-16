<?php

namespace Tests\Feature\Catalog;

use App\DTOs\Catalog\Requests\DeleteHostRoomTypeRequest;
use App\Models\Booking;
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

final class HostRoomTypeDeleteTest extends TestCase
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
            && $database === getenv('STAYHUB_ROOM_TYPE_DELETE_TEST_DATABASE')
            && preg_match('/\Astayhub_rt04b_test_[a-f0-9]{32}\z/', $database) === 1;
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
    public function test_delete_unused_room_type_and_reuse_name(string $status): void
    {
        $host = $this->actor();
        $property = $this->property($host, $status);
        $type = $property->roomTypes()->create(['name' => 'Suite']);
        $this->actingAs($host)->deleteJson($this->endpoint($type->id))->assertOk()->assertExactJson([
            'success' => true, 'message' => 'Xóa loại phòng thành công.',
            'data' => ['deleted_room_type_id' => $type->id],
        ]);
        $this->assertDatabaseMissing('room_types', ['id' => $type->id]);
        $list = '/api/host/properties/'.$property->id.'/room-types';
        $this->getJson($list)->assertOk()->assertJsonPath('data', []);
        $this->deleteJson($this->endpoint($type->id))->assertNotFound()
            ->assertExactJson(['message' => 'Không tìm thấy loại phòng.']);
        $this->postJson($list, ['name' => 'Suite'])->assertCreated();
        $this->assertDatabaseHas('properties', ['id' => $property->id, 'status' => $status]);
    }

    public static function references(): array
    {
        return [['ACTIVE', false, false], ['INACTIVE', false, false], ['MAINTENANCE', false, false],
            ['ACTIVE', true, false], ['ACTIVE', false, true]];
    }

    #[DataProvider('references')]
    public function test_any_reference_blocks_deletion_and_preserves_dependencies(string $status, bool $booked, bool $legacy): void
    {
        $host = $this->actor();
        $property = $this->property($host);
        $type = $property->roomTypes()->create(['name' => 'Suite']);
        $location = $legacy ? $this->property($this->actor()) : $property;
        $room = $location->rooms()->create(['room_type_id' => $type->id, 'room_number' => '01',
            'name' => 'Fixture', 'capacity' => 2, 'price_per_night' => '100.00', 'status' => $status]);
        DB::table('room_images')->insert(['room_id' => $room->id, 'image_path' => 'fixture.png']);
        $amenity = DB::table('amenities')->insertGetId(['name' => 'Fixture amenity']);
        DB::table('room_amenities')->insert(['room_id' => $room->id, 'amenity_id' => $amenity]);
        DB::table('maintenances')->insert(['room_id' => $room->id, 'start_date' => '2026-12-01', 'end_date' => '2026-12-02']);
        if ($booked) {
            Booking::create(['booking_code' => 'FIXTURE', 'customer_id' => $this->actor('CUSTOMER')->id,
                'room_id' => $room->id, 'check_in_date' => '2026-12-10', 'check_out_date' => '2026-12-11',
                'guest_count' => 1, 'number_of_nights' => 1, 'price_per_night' => '100.00', 'room_total' => '100.00']);
        }
        $snapshot = fn () => json_encode(array_map(fn ($table) => DB::table($table)->get(),
            ['room_types', 'rooms', 'room_images', 'room_amenities', 'maintenances', 'bookings']));
        $before = $snapshot();
        $this->actingAs($host)->deleteJson($this->endpoint($type->id))->assertConflict()->assertExactJson([
            'message' => 'Loại phòng đang được sử dụng và không thể xóa.',
        ]);
        $this->assertSame($before, $snapshot());
    }

    public function test_role_input_and_privacy_precedence(): void
    {
        $bad = $this->endpoint('01').'?page=2';
        $this->deleteJson($bad)->assertUnauthorized();
        foreach (['ADMIN', 'CUSTOMER'] as $role) {
            $actor = $this->actor($role);
            $this->actingAs($actor)->deleteJson($bad)->assertForbidden();
            try {
                $this->app->make(HostRoomTypeService::class)->delete($actor, new DeleteHostRoomTypeRequest(1));
                $this->fail('Expected role denial');
            } catch (HttpResponseException $error) {
                $this->assertSame(403, $error->getResponse()->getStatusCode());
            }
        }
        foreach (['BLOCKED', 'INACTIVE'] as $status) {
            $this->actingAs($this->actor('HOST', $status))->deleteJson($bad)->assertForbidden()->assertJsonPath('code', 'ACCOUNT_'.$status);
        }
        $type = $this->property($this->actor())->roomTypes()->create(['name' => 'Other']);
        $host = $this->actor();
        $owned = $this->property($host)->roomTypes()->create(['name' => 'Owned']);
        $this->actingAs($host);
        foreach (['0', '01', 'word', '9223372036854775808'] as $id) {
            $this->deleteJson($this->endpoint($id).'?page=2')->assertNotFound()
                ->assertExactJson(['message' => 'Không tìm thấy loại phòng.']);
        }
        foreach ([$type->id, 9999] as $id) {
            $this->deleteJson($this->endpoint($id))->assertNotFound()
                ->assertExactJson(['message' => 'Không tìm thấy loại phòng.']);
            $this->deleteJson($this->endpoint($id).'?page=2')->assertUnprocessable()->assertJsonValidationErrors('page');
        }
        $this->deleteJson($this->endpoint($owned->id), ['id' => 99])->assertUnprocessable()->assertJsonValidationErrors('id');
        $this->assertDatabaseCount('room_types', 2);
    }

    #[Group('room-type-real-commit')]
    public function test_outer_rollback_restores_row_and_log_waits_for_commit(): void
    {
        $host = $this->actor();
        $property = $this->property($host);
        $type = $property->roomTypes()->create(['name' => 'Suite']);
        $service = $this->app->make(HostRoomTypeService::class);
        $request = new DeleteHostRoomTypeRequest($type->id);
        Log::spy();
        DB::beginTransaction();
        try {
            $service->delete($host, $request);
            $this->assertDatabaseMissing('room_types', ['id' => $type->id]);
            Log::shouldNotHaveReceived('info');
            DB::rollBack();
            $this->assertDatabaseHas('room_types', ['id' => $type->id]);
            Log::shouldNotHaveReceived('info');

            DB::beginTransaction();
            $service->delete($host, $request);
            Log::shouldNotHaveReceived('info');
            DB::commit();
            $this->assertFalse(DB::connection()->getPdo()->inTransaction());
            $this->assertDatabaseMissing('room_types', ['id' => $type->id]);
            Log::shouldHaveReceived('info')->with('Xóa loại phòng thành công.', [
                'property_id' => $property->id, 'room_type_id' => $type->id,
            ])->once();
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }
    }
}
