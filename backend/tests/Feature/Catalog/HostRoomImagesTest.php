<?php

namespace Tests\Feature\Catalog;

use App\DTOs\Catalog\Requests\HostRoomImageQuery;
use App\DTOs\Catalog\Requests\UploadHostRoomImageRequest;
use App\Mappers\Catalog\CatalogMapper;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomImage;
use App\Models\User;
use App\Repositories\Catalog\CatalogRepository;
use App\Services\Catalog\HostRoomImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class HostRoomImagesTest extends TestCase
{
    use RefreshDatabase;

    // Upload must exercise a real root commit, not the usual PHPUnit wrapper.
    public function beginDatabaseTransaction() {}

    public function createApplication()
    {
        foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:',
            'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'] as $key => $expected) {
            if (($_ENV[$key] ?? getenv($key)) !== $expected) throw new RuntimeException('Room image tests require isolated SQLite memory configuration.');
        }
        if (is_file(dirname(__DIR__, 3).'/bootstrap/cache/config.php')) throw new RuntimeException('Room image tests refuse cached configuration.');
        $app = parent::createApplication();
        if ($app['config']->get('database.default') !== 'sqlite'
            || $app['config']->get('database.connections.sqlite.database') !== ':memory:'
            || !empty($app['config']->get('database.connections.sqlite.url'))) throw new RuntimeException('Unsafe effective room image database.');

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        RefreshDatabaseState::$migrated = false;
        parent::tearDown();
    }

    private function actor(string $role = 'HOST', string $status = 'ACTIVE'): User
    {
        return User::factory()->create(['role' => $role, 'status' => $status]);
    }

    private function room(User $host): Room
    {
        $property = Property::create(['host_id' => $host->id, 'name' => 'Image fixture', 'type' => 'HOTEL', 'address' => 'Fixture', 'status' => 'ACTIVE']);
        $type = $property->roomTypes()->create(['name' => 'Fixture type']);

        return $property->rooms()->create(['room_type_id' => $type->id, 'room_number' => '001', 'name' => 'Fixture room',
            'capacity' => 2, 'price_per_night' => '100.00', 'status' => 'ACTIVE']);
    }

    private function endpoint(int|string $id): string { return '/api/host/rooms/'.$id.'/images'; }

    private function image(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('untrusted-name.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGMQSTkBAAHQAUHAZ3dkAAAAAElFTkSuQmCC'));
    }

    public function test_list_empty_and_sorted_projection_filters_private_urls_without_writes(): void
    {
        $host = $this->actor(); $room = $this->room($host);
        $this->actingAs($host)->getJson($this->endpoint($room->id))->assertExactJson([
            'success' => true, 'message' => 'Danh sách hình ảnh phòng.', 'data' => [],
        ]);
        $second = $room->images()->create(['image_path' => 'rooms/second.png', 'is_primary' => false]);
        $first = $room->images()->create(['image_path' => 'https://example.test/main.jpg', 'is_primary' => true]);
        $room->images()->create(['image_path' => 'profile-avatars/private.png']);
        $room->images()->create(['image_path' => 'javascript:alert(1)']);
        $before = RoomImage::all()->toArray();
        $this->getJson($this->endpoint($room->id))->assertExactJson([
            'success' => true, 'message' => 'Danh sách hình ảnh phòng.', 'data' => [
                ['id' => $first->id, 'url' => 'https://example.test/main.jpg', 'is_primary' => true],
                ['id' => $second->id, 'url' => Storage::disk('public')->url('rooms/second.png'), 'is_primary' => false],
            ],
        ]);
        $this->assertSame($before, RoomImage::all()->toArray());
    }

    public function test_upload_persists_file_uses_uuid_and_first_image_is_primary_then_preserves_it(): void
    {
        $host = $this->actor(); $room = $this->room($host);
        $before = $room->fresh()->getAttributes();
        $first = $this->actingAs($host)->postJson($this->endpoint($room->id), ['image' => $this->image()])->assertCreated()
            ->assertJsonPath('success', true)->assertJsonPath('data.is_primary', true)->json('data');
        $row = RoomImage::findOrFail($first['id']);
        $this->assertMatchesRegularExpression('~^rooms/'.$room->id.'/[a-f0-9-]{36}\.png$~', $row->image_path);
        Storage::disk('public')->assertExists($row->image_path);
        $this->assertSame(['id', 'url', 'is_primary'], array_keys($first));
        $this->assertSame(Storage::disk('public')->url($row->image_path), $first['url']);
        $this->postJson($this->endpoint($room->id), ['image' => $this->image()])->assertCreated()->assertJsonPath('data.is_primary', false);
        $this->assertSame(1, $room->images()->where('is_primary', true)->count());
        $this->assertSame($before, $room->fresh()->getAttributes());
        $this->getJson('/api/host/rooms/'.$room->id)->assertOk()->assertJsonCount(2, 'data.images');
    }

    public static function roles(): array { return [['CUSTOMER'], ['ADMIN']]; }

    #[DataProvider('roles')]
    public function test_other_roles_cannot_list_or_upload(string $role): void
    {
        $room = $this->room($this->actor());
        $this->actingAs($this->actor($role))->getJson($this->endpoint($room->id))->assertForbidden();
        $this->postJson($this->endpoint($room->id), ['image' => $this->image()])->assertForbidden();
        $this->assertDatabaseCount('room_images', 0);
    }

    public function test_guest_and_blocked_host_are_rejected(): void
    {
        $room = $this->room($this->actor());
        $this->getJson($this->endpoint($room->id))->assertUnauthorized();
        $this->postJson($this->endpoint($room->id), ['image' => $this->image()])->assertUnauthorized();
        $blocked = $this->actor('HOST', 'BLOCKED');
        $this->actingAs($blocked)->getJson($this->endpoint($room->id))->assertForbidden();
        $this->postJson($this->endpoint($room->id), ['image' => $this->image()])->assertForbidden();
    }

    public function test_foreign_missing_and_noncanonical_rooms_are_private_and_do_not_store_files(): void
    {
        $foreign = $this->room($this->actor());
        $this->actingAs($this->actor());
        foreach ([$foreign->id, '999999', '01', '0', '-1', '1e2', '9223372036854775808'] as $id) {
            $this->getJson($this->endpoint($id))->assertNotFound()->assertExactJson(['message' => 'Không tìm thấy phòng.']);
            $this->postJson($this->endpoint($id), ['image' => $this->image()])->assertNotFound();
        }
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertDatabaseCount('room_images', 0);
    }

    public function test_invalid_files_and_extra_fields_and_queries_are_rejected(): void
    {
        $host = $this->actor(); $room = $this->room($host); $this->actingAs($host);
        foreach ([[], ['image' => 'not-file'], ['image' => [$this->image()]],
            ['image' => UploadedFile::fake()->createWithContent('fake.png', 'not-an-image')],
            ['image' => $this->image(), 'room_id' => 99],
            ['image' => $this->image(), 'is_primary' => true]] as $body) {
            $this->postJson($this->endpoint($room->id), $body)->assertUnprocessable();
        }
        $this->getJson($this->endpoint($room->id).'?page=1')->assertUnprocessable()->assertJsonValidationErrors('page');
        $this->postJson($this->endpoint($room->id).'?image=fake', ['image' => $this->image()])
            ->assertUnprocessable()->assertJsonValidationErrors('image');
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_oversized_file_and_dimensions_rejected_but_size_boundary_is_accepted(): void
    {
        $host = $this->actor(); $room = $this->room($host); $this->actingAs($host);
        $fixture = $this->image();
        $bytes = file_get_contents($fixture->getRealPath());
        $oversized = UploadedFile::fake()->createWithContent('big.png', $bytes.str_repeat("\0", 5 * 1024 * 1024 + 1 - strlen($bytes)));
        $this->postJson($this->endpoint($room->id), ['image' => $oversized])->assertUnprocessable()->assertJsonValidationErrors('image');
        $wide = UploadedFile::fake()->createWithContent('wide.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAEAEAAAABCAIAAAA+gZcVAAAAKElEQVR4nO3CQREAAAgDIEMsomGNZY094Jjsqaqqqqqqqqqqqqqq/R8W6QJtfiZhlAAAAABJRU5ErkJggg=='));
        $this->postJson($this->endpoint($room->id), ['image' => $wide])->assertUnprocessable()->assertJsonValidationErrors('image');
        $edge = UploadedFile::fake()->createWithContent('edge.png', $bytes.str_repeat("\0", 5 * 1024 * 1024 - strlen($bytes)));
        $this->postJson($this->endpoint($room->id), ['image' => $edge])->assertCreated();
    }

    public function test_twentieth_image_allowed_twenty_first_conflicts_without_new_file(): void
    {
        $host = $this->actor(); $room = $this->room($host);
        for ($i = 0; $i < 19; $i++) $room->images()->create(['image_path' => 'rooms/seed-'.$i.'.png', 'is_primary' => $i === 0]);
        $this->actingAs($host)->postJson($this->endpoint($room->id), ['image' => $this->image()])->assertCreated();
        $files = Storage::disk('public')->allFiles();
        $this->postJson($this->endpoint($room->id), ['image' => $this->image()])->assertConflict();
        $this->assertSame(20, $room->images()->count());
        $this->assertSame($files, Storage::disk('public')->allFiles());
    }

    public function test_multipart_webp_and_dimension_boundary_use_detected_mime_not_filename(): void
    {
        $host = $this->actor(); $room = $this->room($host);
        $fixture = UploadedFile::fake()->createWithContent('fixture.webp', base64_decode('UklGRiIAAABXRUJQVlA4IBYAAAAwAQCdASoBAAEADsD+JaQAA3AAAAAA'));
        $webp = new UploadedFile($fixture->getRealPath(), 'misleading-name.txt', null, null, true);
        $this->actingAs($host)->withHeader('Accept', 'application/json')->post($this->endpoint($room->id), ['image' => $webp])
            ->assertCreated()->assertJsonPath('data.is_primary', true);
        $this->assertStringEndsWith('.webp', $room->images()->first()->image_path);
        $edge = UploadedFile::fake()->createWithContent('edge.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAEAAAAAABCAIAAADRQ/wrAAAAKElEQVR4nO3CQREAAAgDIEMsomGNZY094Jjsqaqqqqqqqqqqqqqq5R8RlgEtDijk5QAAAABJRU5ErkJggg=='));
        $this->post($this->endpoint($room->id), ['image' => $edge])->assertCreated();
    }

    public function test_upload_normalizes_legacy_primary_flags_but_does_not_change_foreign_rooms(): void
    {
        $host = $this->actor(); $room = $this->room($host);
        $private = $room->images()->create(['image_path' => 'profile-avatars/private.png', 'is_primary' => true]);
        $primary = $room->images()->create(['image_path' => 'rooms/keep.png', 'is_primary' => true]);
        $duplicate = $room->images()->create(['image_path' => 'rooms/duplicate.png', 'is_primary' => true]);
        $foreign = $this->room($this->actor());
        $foreignImage = $foreign->images()->create(['image_path' => 'rooms/foreign.png', 'is_primary' => true]);
        $before = $foreignImage->fresh()->getAttributes();
        $this->actingAs($host)->postJson($this->endpoint($room->id), ['image' => $this->image()])->assertCreated()->assertJsonPath('data.is_primary', false);
        $this->assertTrue($primary->fresh()->is_primary);
        $this->assertFalse($private->fresh()->is_primary);
        $this->assertFalse($duplicate->fresh()->is_primary);
        $this->assertSame($before, $foreignImage->fresh()->getAttributes());
    }

    public function test_failed_compensation_preserves_original_failure_and_warns_without_claiming_success(): void
    {
        $host = $this->actor(); $room = $this->room($host);
        $disk = Storage::disk('public');
        $fake = \Mockery::mock($disk)->makePartial();
        $fake->shouldReceive('delete')->once()->andThrow(new RuntimeException('Cleanup fixture failure'));
        Storage::shouldReceive('disk')->with('public')->andReturn($fake);
        \Illuminate\Support\Facades\Log::spy();
        $this->mock(CatalogRepository::class, function ($mock) {
            $mock->makePartial()->shouldReceive('createRoomImage')->once()->andThrow(new RuntimeException('Original insert failure'));
        });
        try {
            $this->withoutExceptionHandling()->actingAs($host)->postJson($this->endpoint($room->id), ['image' => $this->image()]);
            $this->fail('Expected original failure');
        } catch (RuntimeException $failure) { $this->assertSame('Original insert failure', $failure->getMessage()); }
        $this->assertDatabaseCount('room_images', 0);
        \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')->once();
        \Illuminate\Support\Facades\Log::shouldNotHaveReceived('info');
    }

    public function test_database_failure_after_storage_rolls_back_and_cleans_owned_file(): void
    {
        $host = $this->actor(); $room = $this->room($host);
        $this->mock(CatalogRepository::class, function ($mock) {
            $mock->makePartial()->shouldReceive('createRoomImage')->once()->andThrow(new RuntimeException('Fixture insert failure'));
        });
        try {
            $this->withoutExceptionHandling()->actingAs($host)->postJson($this->endpoint($room->id), ['image' => $this->image()]);
            $this->fail('Expected insert failure');
        } catch (RuntimeException $failure) { $this->assertSame('Fixture insert failure', $failure->getMessage()); }
        $this->assertDatabaseCount('room_images', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_storage_failure_does_not_create_row(): void
    {
        $host = $this->actor(); $room = $this->room($host);
        $disk = Storage::disk('public');
        $fake = \Mockery::mock($disk)->makePartial();
        $fake->shouldReceive('putFileAs')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('public')->andReturn($fake);
        try {
            $this->withoutExceptionHandling()->actingAs($host)->postJson($this->endpoint($room->id), ['image' => $this->image()]);
            $this->fail('Expected storage failure');
        } catch (RuntimeException $failure) { $this->assertSame('Room image storage failed.', $failure->getMessage()); }
        $this->assertDatabaseCount('room_images', 0);
    }

    public function test_service_rejects_outer_transaction_and_mapper_has_no_queries_or_private_fields(): void
    {
        $host = $this->actor(); $room = $this->room($host);
        DB::beginTransaction();
        try {
            app(HostRoomImageService::class)->upload($host, new HostRoomImageQuery($room->id), new UploadHostRoomImageRequest($this->image()));
            $this->fail('Expected root transaction guard');
        } catch (LogicException $failure) { $this->assertSame('Room image uploads require a root transaction.', $failure->getMessage()); }
        finally { DB::rollBack(); }
        $image = new RoomImage(['room_id' => $room->id, 'image_path' => 'https://example.test/image.png', 'is_primary' => true]);
        $image->id = 42;
        DB::enableQueryLog(); DB::flushQueryLog();
        $result = app(CatalogMapper::class)->toHostRoomImage($image);
        $this->assertSame(['id' => 42, 'url' => 'https://example.test/image.png', 'is_primary' => true], $result->jsonSerialize());
        $this->assertSame([], DB::getQueryLog()); DB::disableQueryLog();
    }
}
