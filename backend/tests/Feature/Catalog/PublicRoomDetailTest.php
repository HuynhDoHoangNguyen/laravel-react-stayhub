<?php

namespace Tests\Feature\Catalog;

use App\DTOs\Catalog\Requests\PublicRoomDetailQuery;
use App\Mappers\Catalog\CatalogMapper;
use App\Models\Amenity;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use App\Repositories\Catalog\CatalogRepository;
use App\Services\Catalog\PublicRoomQueryService;
use App\Validators\Catalog\CatalogValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

final class PublicRoomDetailTest extends TestCase
{
    use RefreshDatabase;

    public function createApplication()
    {
        foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:',
            'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'] as $key => $expected) {
            if (($_ENV[$key] ?? getenv($key)) !== $expected) {
                throw new RuntimeException('UC8-01 requires an isolated in-memory test environment.');
            }
        }
        if (is_file(dirname(__DIR__, 3).'/bootstrap/cache/config.php')) {
            throw new RuntimeException('UC8-01 refuses cached configuration.');
        }
        $app = parent::createApplication();
        if ($app['config']->get('database.default') !== 'sqlite'
            || $app['config']->get('database.connections.sqlite.database') !== ':memory:'
            || !empty($app['config']->get('database.connections.sqlite.url'))
            || $app['config']->get('cache.default') !== 'array'
            || $app['config']->get('session.driver') !== 'array') {
            throw new RuntimeException('UC8-01 effective test database is unsafe.');
        }

        return $app;
    }

    private function room(array $extra = [], string $propertyStatus = 'ACTIVE'): Room
    {
        $property = Property::create([
            'host_id' => User::factory()->create(['role' => 'HOST'])->id,
            'name' => 'Cơ sở kiểm thử', 'type' => 'HOTEL', 'address' => 'Địa chỉ public',
            'phone' => 'PRIVATE', 'status' => $propertyStatus,
        ]);
        $type = $property->roomTypes()->create(['name' => 'Deluxe', 'description' => 'INTERNAL']);

        return $property->rooms()->create(array_replace([
            'room_type_id' => $type->id, 'room_number' => '201', 'name' => 'Phòng kiểm thử',
            'description' => null, 'capacity' => 2, 'price_per_night' => '850000.25', 'status' => 'ACTIVE',
        ], $extra));
    }

    public function test_guest_receives_exact_public_projection_with_ordered_safe_images_and_active_amenities(): void
    {
        $room = $this->room();
        $room->images()->create(['image_path' => 'https://example.com/second.jpg', 'is_primary' => false]);
        $room->images()->create(['image_path' => 'https://example.com/main.jpg', 'is_primary' => true]);
        $room->images()->create(['image_path' => 'javascript:alert(1)', 'is_primary' => true]);
        $room->images()->create(['image_path' => '/api/profile/avatar', 'is_primary' => false]);
        $room->images()->create(['image_path' => 'profile-avatars/private.jpg', 'is_primary' => false]);
        $wifi = Amenity::create(['name' => 'Wifi', 'status' => true, 'description' => 'INTERNAL']);
        $tv = Amenity::create(['name' => 'TV', 'status' => true]);
        $inactive = Amenity::create(['name' => 'Không công khai', 'status' => false]);
        $room->amenities()->attach([$tv->id, $inactive->id, $wifi->id]);
        $this->getJson('/api/public/rooms/'.$room->id)->assertOk()->assertExactJson(['data' => [
            'id' => $room->id, 'room_number' => '201', 'name' => 'Phòng kiểm thử', 'description' => null,
            'capacity' => 2, 'price_per_night' => '850000.25',
            'room_type' => ['id' => $room->room_type_id, 'name' => 'Deluxe'],
            'images' => ['https://example.com/main.jpg', 'https://example.com/second.jpg'],
            'amenities' => [['id' => $wifi->id, 'name' => 'Wifi'], ['id' => $tv->id, 'name' => 'TV']],
            'property' => ['id' => $room->property_id, 'name' => 'Cơ sở kiểm thử', 'type' => 'HOTEL', 'address' => 'Địa chỉ public'],
            'currency' => 'VND', 'price_unit' => 'night',
        ]]);
    }

    #[DataProvider('roles')]
    public function test_authenticated_roles_can_read_the_same_public_room(string $role): void
    {
        $room = $this->room();
        $this->actingAs(User::factory()->create(['role' => $role, 'status' => 'ACTIVE']));
        $this->getJson('/api/public/rooms/'.$room->id)->assertOk()->assertJsonPath('data.id', $room->id);
    }

    public static function roles(): array
    {
        return [['CUSTOMER'], ['HOST'], ['ADMIN']];
    }

    #[DataProvider('hiddenRooms')]
    public function test_nonpublic_or_invalid_room_candidates_use_the_same_privacy_404(array $extra, string $propertyStatus): void
    {
        $room = $this->room($extra, $propertyStatus);
        $this->getJson('/api/public/rooms/'.$room->id)->assertNotFound()
            ->assertExactJson(['message' => 'Không tìm thấy phòng.']);
    }

    public static function hiddenRooms(): array
    {
        return [
            'inactive property' => [[], 'INACTIVE'], 'blocked property' => [[], 'BLOCKED'],
            'pending property' => [[], 'PENDING'], 'inactive room' => [['status' => 'INACTIVE'], 'ACTIVE'],
            'maintenance room' => [['status' => 'MAINTENANCE'], 'ACTIVE'],
            'zero capacity' => [['capacity' => 0], 'ACTIVE'], 'negative capacity' => [['capacity' => -1], 'ACTIVE'],
            'negative price' => [['price_per_night' => '-0.01'], 'ACTIVE'],
        ];
    }

    public function test_room_type_from_another_property_is_hidden(): void
    {
        $room = $this->room();
        $foreign = $this->room();
        $room->update(['room_type_id' => $foreign->room_type_id]);
        $this->getJson('/api/public/rooms/'.$room->id)->assertNotFound()
            ->assertExactJson(['message' => 'Không tìm thấy phòng.']);
    }

    #[DataProvider('invalidIds')]
    public function test_invalid_ids_and_missing_rooms_return_404_without_integer_coercion(string $id): void
    {
        $this->room();
        $this->getJson('/api/public/rooms/'.rawurlencode($id))->assertNotFound()
            ->assertExactJson(['message' => 'Không tìm thấy phòng.']);
    }

    public static function invalidIds(): array
    {
        return array_map(static fn ($id) => [$id], [
            '0', '-1', '01', '+1', '1.0', '1e0', 'abc', '1 ', '999999', '9223372036854775808',
        ]);
    }

    public function test_query_errors_precede_id_lookup_and_keep_field_errors(): void
    {
        foreach (['abc', '999999'] as $id) {
            $this->getJson('/api/public/rooms/'.$id.'?check_in_date=2026-12-01&guest_count=2')
                ->assertUnprocessable()->assertJsonValidationErrors(['check_in_date', 'guest_count']);
        }
        $query = app(CatalogValidator::class)->validatePublicRoomDetail('12', []);
        $this->assertInstanceOf(PublicRoomDetailQuery::class, $query);
        $this->assertSame(12, $query->roomId);
        $this->assertNull(app(CatalogValidator::class)->validatePublicRoomDetail('', []));
    }

    #[DataProvider('prices')]
    public function test_decimal_price_null_description_and_empty_collections_are_preserved(string $price): void
    {
        $room = $this->room(['price_per_night' => $price]);
        $this->getJson('/api/public/rooms/'.$room->id)->assertOk()
            ->assertJsonPath('data.price_per_night', $price)->assertJsonPath('data.description', null)
            ->assertJsonPath('data.images', [])->assertJsonPath('data.amenities', []);
    }

    public static function prices(): array
    {
        return [['0.00'], ['0.01'], ['9999999999.99']];
    }

    public function test_description_and_homestay_type_are_returned_without_markup_interpretation(): void
    {
        $room = $this->room(['description' => "Mô tả\n<script>alert(1)</script>"]);
        $room->property->update(['type' => 'HOMESTAY']);
        $this->getJson('/api/public/rooms/'.$room->id)->assertOk()
            ->assertJsonPath('data.description', "Mô tả\n<script>alert(1)</script>")
            ->assertJsonPath('data.property.type', 'HOMESTAY');
    }

    public function test_repository_eager_loads_relations_and_symfony_mapping_performs_no_queries(): void
    {
        $room = $this->room();
        $loaded = app(CatalogRepository::class)->findPublicRoom($room->id);
        foreach (['property', 'roomType', 'images', 'amenities'] as $relation) {
            $this->assertTrue($loaded->relationLoaded($relation));
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $response = app(CatalogMapper::class)->toPublicRoomDetail($loaded);
        $data = json_decode(json_encode($response, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($room->id, $data['id']);
        $this->assertSame('850000.25', $data['price_per_night']);
        $this->assertSame([], DB::getQueryLog());
        DB::disableQueryLog();
    }

    public function test_mapper_rejects_unloaded_relations_instead_of_lazy_loading(): void
    {
        $room = $this->room();
        $this->expectException(LogicException::class);
        app(CatalogMapper::class)->toPublicRoomDetail($room);
    }

    public function test_detail_is_read_only_and_existing_public_routes_still_work(): void
    {
        $room = $this->room();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson('/api/public/rooms/'.$room->id)->assertOk();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertNotEmpty($queries);
        foreach ($queries as $query) {
            $this->assertStringStartsWith('select', strtolower(ltrim($query['query'])));
        }
        $this->getJson('/api/public/rooms/filter-options')->assertOk()->assertJsonStructure(['data' => ['room_types', 'amenities']]);
        $this->getJson('/api/public/rooms')->assertOk()->assertJsonPath('data.0.id', $room->id);
        $this->getJson('/api/public/properties/'.$room->property_id)->assertOk();
        $this->assertNull(app(PublicRoomQueryService::class)->detail(new PublicRoomDetailQuery(999999)));
    }
}
