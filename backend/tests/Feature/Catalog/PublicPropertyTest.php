<?php

namespace Tests\Feature\Catalog;

use App\Models\User;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\RoomImage;
use App\Models\Amenity;
use App\Models\Review;
use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

class PublicPropertyTest extends TestCase
{
    use RefreshDatabase;
    private ?string $sessionCookie = null;
    private ?User $owner = null;

    private function property(string $status = 'ACTIVE'): Property
    {
        $this->owner ??= User::create(['name' => 'PRIVATE HOST', 'email' => 'private-host@example.test',
            'password' => 'Fixture123!', 'phone' => '0123456789', 'address' => 'PRIVATE HOME', 'role' => 'HOST', 'status' => 'ACTIVE']);
        return Property::create(['host_id' => $this->owner->id, 'name' => 'Catalog fixture', 'type' => 'HOTEL',
            'address' => 'Public property address', 'phone' => '0999999999', 'description' => '<script>fixture</script>', 'status' => $status]);
    }

    private function room(Property $property, string $price = '500000.25', string $status = 'ACTIVE', ?RoomType $type = null): Room
    {
        $type ??= RoomType::create(['property_id' => $property->id, 'name' => 'Public room type']);
        return Room::create(['property_id' => $property->id, 'room_type_id' => $type->id,
            'room_number' => '101', 'name' => 'Public room', 'capacity' => 2, 'price_per_night' => $price, 'status' => $status]);
    }

    public function test_empty_catalog_is_a_real_empty_paginated_collection(): void
    {
        $this->getJson('/api/public/properties')->assertOk()->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0)->assertJsonPath('meta.per_page', 12)->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_only_active_properties_count_towards_pagination_and_order_is_stable(): void
    {
        $first = $this->property(); $this->property('INACTIVE'); $this->property('PENDING'); $this->property('BLOCKED'); $last = $this->property();
        $this->getJson('/api/public/properties?per_page=1')->assertOk()->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.last_page', 2)->assertJsonPath('data.0.id', $first->id);
        $this->getJson('/api/public/properties?page=2&per_page=1')->assertOk()->assertJsonPath('data.0.id', $last->id);
        $this->getJson('/api/public/properties?page=3&per_page=1')->assertOk()->assertJsonCount(0, 'data');
    }

    public static function invalidQueries(): array
    {
        return [[['page' => 0], 'page'], [['page' => -1], 'page'], [['page' => '1.5'], 'page'], [['page' => 'bad'], 'page'],
            [['page' => []], 'page'], [['page' => '2147483648'], 'page'], [['per_page' => 0], 'per_page'], [['per_page' => 25], 'per_page'],
            [['per_page' => 100000], 'per_page'], [['per_page' => '1.5'], 'per_page'], [['per_page' => ['bad']], 'per_page'],
            [['city' => 'City'], 'city'], [['check_in_date' => '2026-12-01'], 'check_in_date'], [['minPrice' => '0'], 'minPrice']];
    }

    #[DataProvider('invalidQueries')]
    public function test_invalid_and_unsupported_list_parameters_return_field_errors(array $params, string $field): void
    {
        $query = $params['page'] ?? null;
        $url = $field === 'page' && is_array($query) ? '/api/public/properties?page[]=1'
            : '/api/public/properties?'.http_build_query($params);
        $this->getJson($url)->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    public function test_per_page_upper_boundary_and_default_use_database_pagination(): void
    {
        for ($i = 0; $i < 25; ++$i) $this->property();
        $this->getJson('/api/public/properties')->assertJsonCount(12, 'data')->assertJsonPath('meta.total', 25);
        $this->getJson('/api/public/properties?per_page=24')->assertJsonCount(24, 'data')->assertJsonPath('meta.last_page', 2);
    }

    public function test_starting_price_excludes_nonpublic_and_wrong_owner_rooms_and_preserves_zero(): void
    {
        $property = $this->property(); $other = $this->property();
        $this->room($property, '500000.25'); $this->room($property, '1.00', 'INACTIVE'); $this->room($property, '2.00', 'MAINTENANCE');
        $wrongType = RoomType::create(['property_id' => $other->id, 'name' => 'PRIVATE WRONG TYPE']);
        $this->room($property, '3.00', 'ACTIVE', $wrongType);
        $this->getJson('/api/public/properties')->assertJsonPath('data.0.starting_price', '500000.25');
        $this->getJson('/api/public/properties/'.$property->id)->assertJsonCount(1, 'data.rooms')->assertJsonPath('data.starting_price', '500000.25');
        $this->room($property, '0.00');
        $this->getJson('/api/public/properties')->assertJsonPath('data.0.starting_price', '0.00');
    }

    public function test_active_property_with_no_public_room_image_or_review_is_still_visible(): void
    {
        $property = $this->property(); $this->room($property, '100.00', 'INACTIVE');
        $this->getJson('/api/public/properties')->assertJsonCount(1, 'data')->assertJsonPath('data.0.starting_price', null);
        $this->getJson('/api/public/properties/'.$property->id)->assertOk()->assertJsonPath('data.starting_price', null)
            ->assertJsonPath('data.primary_image', null)->assertJsonCount(0, 'data.rooms')->assertJsonCount(0, 'data.images')
            ->assertJsonCount(0, 'data.amenities')->assertJsonPath('data.rating', null)->assertJsonPath('data.review_count', 0);
    }

    public function test_primary_and_fallback_images_have_deterministic_order_and_belong_to_correct_entities(): void
    {
        $property = $this->property(); $other = $this->property(); $room = $this->room($property); $otherRoom = $this->room($other);
        foreach ([['fallback', false], ['main1', true], ['main2', true]] as [$name, $main]) PropertyImage::create([
            'property_id' => $property->id, 'image_path' => 'https://example.com/'.$name.'.jpg', 'is_primary' => $main]);
        PropertyImage::create(['property_id' => $other->id, 'image_path' => 'https://example.com/PRIVATE.jpg', 'is_primary' => true]);
        RoomImage::create(['room_id' => $room->id, 'image_path' => 'https://example.com/room.jpg']);
        RoomImage::create(['room_id' => $otherRoom->id, 'image_path' => 'https://example.com/OTHERROOM.jpg']);
        $this->getJson('/api/public/properties')->assertJsonPath('data.0.primary_image', 'https://example.com/main1.jpg');
        $response = $this->getJson('/api/public/properties/'.$property->id)->assertOk()
            ->assertJsonPath('data.images', ['https://example.com/main1.jpg', 'https://example.com/main2.jpg', 'https://example.com/fallback.jpg'])
            ->assertJsonPath('data.rooms.0.images.0', 'https://example.com/room.jpg');
        $this->assertStringNotContainsString('PRIVATE.jpg', $response->getContent());
        $this->assertStringNotContainsString('OTHERROOM', $response->getContent());
        $this->getJson('/api/public/properties/'.$other->id)->assertJsonPath('data.primary_image', 'https://example.com/PRIVATE.jpg');
    }

    public function test_inactive_amenities_and_amenities_of_hidden_rooms_are_not_public(): void
    {
        $property = $this->property(); $room = $this->room($property); $hidden = $this->room($property, '5.00', 'INACTIVE');
        $public = Amenity::create(['name' => 'Wifi', 'status' => true]);
        $disabled = Amenity::create(['name' => 'PRIVATE DISABLED', 'status' => false]);
        $hiddenOnly = Amenity::create(['name' => 'PRIVATE HIDDEN ROOM', 'status' => true]);
        $room->amenities()->attach([$public->id, $disabled->id]); $hidden->amenities()->attach($hiddenOnly->id);
        $this->getJson('/api/public/properties/'.$property->id)->assertOk()->assertJsonCount(1, 'data.rooms')
            ->assertJsonPath('data.amenities', [['id' => $public->id, 'name' => 'Wifi']])
            ->assertJsonPath('data.rooms.0.amenities', [['id' => $public->id, 'name' => 'Wifi']]);
    }

    public function test_image_selection_falls_back_to_first_id_when_no_image_is_primary(): void
    {
        $property = $this->property();
        foreach (['first', 'second'] as $name) PropertyImage::create(['property_id' => $property->id,
            'image_path' => 'https://example.com/'.$name.'.png', 'is_primary' => false]);
        $this->getJson('/api/public/properties')->assertOk()->assertJsonPath('data.0.primary_image', 'https://example.com/first.png');
        $this->getJson('/api/public/properties/'.$property->id)->assertOk()->assertJsonPath('data.primary_image', 'https://example.com/first.png');
    }

    public static function hiddenStatuses(): array { return [['INACTIVE'], ['BLOCKED'], ['PENDING']]; }
    #[DataProvider('hiddenStatuses')]
    public function test_nonpublic_detail_is_indistinguishable_from_missing_property(string $status): void
    {
        $property = $this->property($status);
        $missing = $this->getJson('/api/public/properties/999999')->assertNotFound()->json();
        $this->assertSame($missing, $this->getJson('/api/public/properties/'.$property->id)->assertNotFound()->json());
    }

    public static function malformedIds(): array { return [['0'], ['-1'], ['01'], ['abc'], ['search'], ['1e2'], [str_repeat('9', 40)]]; }
    #[DataProvider('malformedIds')]
    public function test_malformed_ids_are_safe_404_responses(string $id): void
    {
        $this->getJson('/api/public/properties/'.$id)->assertNotFound();
    }

    public function test_resources_are_whitelisted_without_host_booking_or_private_contact_information(): void
    {
        $property = $this->property(); $this->room($property);
        $response = $this->getJson('/api/public/properties/'.$property->id)->assertOk();
        $keys = array_keys($response->json('data')); sort($keys);
        $expected = ['id', 'name', 'type', 'address', 'description', 'check_in_time', 'check_out_time', 'primary_image',
            'starting_price', 'currency', 'price_unit', 'rating', 'review_count', 'images', 'rooms', 'amenities']; sort($expected);
        $this->assertSame($expected, $keys);
        foreach (['PRIVATE HOST', 'PRIVATE HOME', 'private-host@example', '0999999999', 'host_id', 'customer_id', 'password', 'bookings', 'invoices'] as $private) {
            $this->assertStringNotContainsString($private, $response->getContent());
        }
    }

    public function test_private_paths_unsafe_schemes_and_credentials_never_appear_as_images(): void
    {
        $property = $this->property();
        foreach (['D:\\private\\file.jpg', '../private/file.jpg', 'profile-avatars/1/a.png', 'data:image/png;abc', 'javascript:alert(1)',
            'https://user:secret@example.com/photo.jpg', 'https://localhost/api/profile/avatar?v=secret', 'properties/%2e%2e/private.png'] as $path) {
            PropertyImage::create(['property_id' => $property->id, 'image_path' => $path, 'is_primary' => true]);
        }
        PropertyImage::create(['property_id' => $property->id, 'image_path' => 'properties/public.jpg']);
        $response = $this->getJson('/api/public/properties/'.$property->id)->assertOk()->assertJsonCount(1, 'data.images');
        $this->assertStringEndsWith('/storage/properties/public.jpg', $response->json('data.primary_image'));
    }

    public function test_ratings_match_existing_review_semantics_without_embedding_reviews(): void
    {
        $property = $this->property(); $room = $this->room($property);
        $customer = User::create(['name' => 'PRIVATE CUSTOMER', 'email' => 'private-customer@example.test', 'password' => 'Fixture123!', 'role' => 'CUSTOMER', 'status' => 'ACTIVE']);
        foreach ([2, 5] as $rating) {
            $booking = Booking::create(['booking_code' => 'CATALOG-'.$rating, 'customer_id' => $customer->id, 'room_id' => $room->id,
                'check_in_date' => '2026-01-01', 'check_out_date' => '2026-01-02', 'guest_count' => 1, 'number_of_nights' => 1,
                'price_per_night' => '500000.25', 'room_total' => '500000.25', 'status' => 'COMPLETED']);
            Review::create(['booking_id' => $booking->id, 'customer_id' => $customer->id, 'property_id' => $property->id, 'rating' => $rating, 'comment' => 'PRIVATE COMMENT']);
        }
        $response = $this->getJson('/api/public/properties')->assertOk()->assertJsonPath('data.0.rating', 3.5)->assertJsonPath('data.0.review_count', 2)
            ->assertJsonMissingPath('data.0.reviews');
        $this->assertStringNotContainsString('PRIVATE', $response->getContent());
    }

    public function test_public_catalog_does_not_apply_account_status_to_a_logged_in_actor(): void
    {
        $property = $this->property();
        $this->browser('POST', '/login', ['email' => $this->owner->email, 'password' => 'Fixture123!'])->assertOk();
        $this->owner->update(['status' => 'BLOCKED']);
        $this->browser('GET', '/api/public/properties')->assertOk();
        $this->browser('GET', '/api/public/properties/'.$property->id)->assertOk();
    }

    public function test_detail_never_claims_to_accept_availability_filters(): void
    {
        $property = $this->property();
        $this->getJson('/api/public/properties/'.$property->id.'?startDate=2026-12-01')->assertUnprocessable()->assertJsonValidationErrors('startDate');
    }

    public function test_image_loading_is_batched_for_a_full_page_instead_of_one_query_per_property(): void
    {
        $this->property(); $this->getJson('/api/public/properties')->assertOk();
        DB::enableQueryLog(); DB::flushQueryLog();
        $this->getJson('/api/public/properties')->assertOk();
        $imageQueries = fn () => array_values(array_filter(DB::getQueryLog(), fn ($q) => str_contains($q['query'], 'from "property_images"')));
        $small = count($imageQueries());
        for ($i = 0; $i < 10; ++$i) $this->property();
        DB::flushQueryLog(); $this->getJson('/api/public/properties')->assertOk();
        $this->assertSame($small, count($imageQueries()));
        $this->assertSame(1, $small); DB::disableQueryLog();
    }
    public function createApplication()
    {
        foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:',
            'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'] as $key => $expected) {
            if (($_ENV[$key] ?? getenv($key)) !== $expected) {
                throw new RuntimeException('UC1 tests require an isolated in-memory test environment before boot.');
            }
        }
        if (is_file(dirname(__DIR__, 3).'/bootstrap/cache/config.php')) {
            throw new RuntimeException('UC1 tests cannot run with cached application configuration.');
        }

        $app = parent::createApplication();
        if ($app['config']->get('database.default') !== 'sqlite'
            || $app['config']->get('database.connections.sqlite.database') !== ':memory:'
            || !empty($app['config']->get('database.connections.sqlite.url'))
            || $app['config']->get('cache.default') !== 'array'
            || $app['config']->get('session.driver') !== 'array') {
            throw new RuntimeException('UC1 database isolation must be verified before RefreshDatabase runs.');
        }
        $app['config']->set('sanctum.stateful', ['localhost:5173']);
        return $app;
    }

    private function browser(string $method, string $uri, array $data = []): TestResponse
    {
        Auth::forgetGuards();
        Auth::shouldUse('web');
        $this->withCredentials();
        if ($this->sessionCookie !== null) {
            $this->withCookie(config('session.cookie'), $this->sessionCookie);
        }
        $response = $this->json($method, $uri, $data, [
            'Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/',
        ]);
        if ($cookie = $response->getCookie(config('session.cookie'))) {
            $this->sessionCookie = $cookie->getValue();
        }
        return $response;
    }

}
