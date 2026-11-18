<?php

namespace Tests\Feature\Catalog;

use App\Models\Amenity;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use App\Services\Catalog\PublicRoomQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class PublicRoomFilterTest extends TestCase
{
    use RefreshDatabase;

    public function createApplication()
    {
        foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:',
            'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'] as $key => $expected) {
            if (($_ENV[$key] ?? getenv($key)) !== $expected) throw new RuntimeException('UC7 requires an isolated in-memory test environment.');
        }
        if (is_file(dirname(__DIR__, 3).'/bootstrap/cache/config.php')) throw new RuntimeException('UC7 refuses cached configuration.');
        $app = parent::createApplication();
        if ($app['config']->get('database.default') !== 'sqlite'
            || $app['config']->get('database.connections.sqlite.database') !== ':memory:'
            || !empty($app['config']->get('database.connections.sqlite.url'))
            || $app['config']->get('cache.default') !== 'array' || $app['config']->get('session.driver') !== 'array') {
            throw new RuntimeException('UC7 effective test database is unsafe.');
        }

        return $app;
    }

    private function property(string $status = 'ACTIVE'): Property
    {
        return Property::create(['host_id' => User::factory()->create(['role' => 'HOST'])->id,
            'name' => 'UC7 property', 'type' => 'HOTEL', 'address' => 'Public address', 'phone' => 'PRIVATE', 'status' => $status]);
    }

    private function room(?Property $property = null, array $extra = []): Room
    {
        $property ??= $this->property();
        $type = $property->roomTypes()->create(['name' => 'Suite']);

        return $property->rooms()->create(array_replace([
            'room_type_id' => $type->id, 'room_number' => 'R1', 'name' => 'Public room', 'description' => null,
            'capacity' => 2, 'price_per_night' => '500000.25', 'status' => 'ACTIVE',
        ], $extra));
    }

    public function test_guest_receives_a_real_empty_page_and_empty_options(): void
    {
        $this->getJson('/api/public/rooms')->assertOk()->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0)->assertJsonPath('meta.per_page', 12)->assertJsonStructure(['data', 'meta', 'links']);
        $this->getJson('/api/public/rooms/filter-options')->assertOk()->assertExactJson(['data' => ['room_types' => [], 'amenities' => []]]);
    }

    public function test_public_visibility_room_type_ownership_and_active_options_are_enforced(): void
    {
        $visible = $this->room();
        foreach (['INACTIVE', 'BLOCKED', 'PENDING'] as $status) $this->room($this->property($status));
        foreach (['INACTIVE', 'MAINTENANCE'] as $status) $this->room(extra: ['status' => $status]);
        $foreign = $this->room();
        $foreign->update(['room_type_id' => $visible->room_type_id]);
        $this->room(extra: ['capacity' => 0]);
        $this->room(extra: ['price_per_night' => '-1.00']);
        $wifi = Amenity::create(['name' => 'Wifi', 'status' => true]);
        $disabled = Amenity::create(['name' => 'Disabled', 'status' => false]);
        Amenity::create(['name' => 'Unused', 'status' => true]);
        $visible->amenities()->attach([$wifi->id, $disabled->id]);
        $this->getJson('/api/public/rooms')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $visible->id);
        $this->getJson('/api/public/rooms/filter-options')->assertOk()->assertExactJson(['data' => [
            'room_types' => [['id' => $visible->room_type_id, 'name' => 'Suite', 'property' => ['id' => $visible->property_id, 'name' => 'UC7 property']]],
            'amenities' => [['id' => $wifi->id, 'name' => 'Wifi']],
        ]]);
        $this->getJson('/api/public/rooms?amenity_ids[]='.$disabled->id)->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_all_filters_must_match_one_room_not_different_rooms_of_a_property(): void
    {
        $property = $this->property();
        $wifi = Amenity::create(['name' => 'Wifi', 'status' => true]);
        $pool = Amenity::create(['name' => 'Pool', 'status' => true]);
        $cheap = $this->room($property, ['capacity' => 4, 'price_per_night' => '100.00']);
        $cheap->amenities()->attach($wifi->id);
        $expensive = $this->room($property, ['capacity' => 2, 'price_per_night' => '900.00']);
        $expensive->amenities()->attach([$wifi->id, $pool->id]);
        $params = ['max_price' => '500.00', 'guest_count' => '3', 'amenity_ids' => [$wifi->id, $pool->id]];
        $this->getJson('/api/public/rooms?'.http_build_query($params))->assertOk()->assertJsonCount(0, 'data');
        $match = $this->room($property, ['capacity' => 3, 'price_per_night' => '500.00']);
        $match->amenities()->attach([$wifi->id, $pool->id]);
        $params['min_price'] = '500.00';
        $params['room_type_ids'] = [$match->room_type_id, $cheap->room_type_id];
        $this->getJson('/api/public/rooms?'.http_build_query($params))->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id)->assertJsonPath('data.0.price_per_night', '500.00');
        $params['room_type_ids'] = [$expensive->room_type_id];
        $this->getJson('/api/public/rooms?'.http_build_query($params))->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_prices_zero_inclusive_boundaries_stable_sort_and_pagination(): void
    {
        $zero = $this->room(extra: ['price_per_night' => '0.00']);
        $first = $this->room(extra: ['price_per_night' => '100.10']);
        $second = $this->room(extra: ['price_per_night' => '100.10']);
        $high = $this->room(extra: ['price_per_night' => '200.20']);
        $this->getJson('/api/public/rooms?min_price=0&max_price=0.00')->assertOk()->assertJsonPath('data.0.id', $zero->id)
            ->assertJsonPath('data.0.price_per_night', '0.00');
        $this->getJson('/api/public/rooms?min_price=100.10&max_price=200.20&per_page=1&page=2')->assertOk()
            ->assertJsonPath('data.0.id', $second->id)->assertJsonPath('meta.total', 3)->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.current_page', 2)->assertJsonPath('meta.per_page', 1);
        $this->getJson('/api/public/rooms?sort=price_desc&per_page=2')->assertOk()->assertJsonPath('data.0.id', $high->id)
            ->assertJsonPath('data.1.id', $first->id);
        $this->getJson('/api/public/rooms?page=99')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 4);
        $this->getJson('/api/public/rooms?room_type_ids[]=999999')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/public/rooms?guest_count=3')->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_response_is_a_whitelist_and_mapping_never_lazy_loads(): void
    {
        $room = $this->room();
        $room->images()->create(['image_path' => 'profile-avatars/private.jpg', 'is_primary' => true]);
        $room->images()->create(['image_path' => 'https://example.com/room.jpg', 'is_primary' => false]);
        $response = $this->getJson('/api/public/rooms')->assertOk();
        $this->assertSame([[
            'id' => $room->id, 'room_number' => 'R1', 'name' => 'Public room', 'description' => null,
            'capacity' => 2, 'price_per_night' => '500000.25', 'room_type' => ['id' => $room->room_type_id, 'name' => 'Suite'],
            'images' => ['https://example.com/room.jpg'], 'amenities' => [],
            'property' => ['id' => $room->property_id, 'name' => 'UC7 property', 'type' => 'HOTEL', 'address' => 'Public address', 'rating' => null, 'review_count' => 0],
            'currency' => 'VND', 'price_unit' => 'night',
        ]], $response->json('data'));
        $this->assertStringNotContainsString('PRIVATE', $response->getContent());
        $filters = $this->app->make(\App\Validators\Catalog\PublicRoomFilterValidator::class)->validate([]);
        $page = $this->app->make(PublicRoomQueryService::class)->listing($filters);
        DB::enableQueryLog(); DB::flushQueryLog();
        try {
            $this->assertSame('500000.25', $page->items()[0]->jsonSerialize()['price_per_night']);
            $this->assertSame([], DB::getQueryLog());
        } finally { DB::disableQueryLog(); }
    }

    public function test_http_validation_rejects_unsupported_availability_and_invalid_filters(): void
    {
        foreach ([['startDate' => '2026-12-01'], ['check_out_date' => '2026-12-02'], ['city' => 'Hue'], ['min_price' => '-1'],
            ['min_price' => '10', 'max_price' => '1'], ['guest_count' => '0'], ['per_page' => '25'], ['sort' => 'RATING_DESC'], ['amenity_ids' => ['1', '1']]] as $params) {
            $this->getJson('/api/public/rooms?'.http_build_query($params))->assertUnprocessable()->assertJsonStructure(['errors']);
        }
        $this->getJson('/api/public/rooms/filter-options?page=1')->assertUnprocessable()->assertJsonValidationErrors('page');
    }

    public function test_reads_are_batched_and_do_not_change_room_or_booking_state(): void
    {
        $room = $this->room();
        $before = $room->fresh()->getAttributes();
        DB::enableQueryLog(); DB::flushQueryLog();
        try {
            $this->getJson('/api/public/rooms')->assertOk();
            $small = count(DB::getQueryLog());
            DB::disableQueryLog();
            for ($i = 0; $i < 8; ++$i) $this->room();
            DB::enableQueryLog(); DB::flushQueryLog();
            $this->getJson('/api/public/rooms')->assertOk();
            $this->assertSame($small, count(DB::getQueryLog()));
            foreach (DB::getQueryLog() as $query) $this->assertMatchesRegularExpression('/\A\s*select\b/i', $query['query']);
        } finally { DB::disableQueryLog(); }
        $this->assertSame($before, $room->fresh()->getAttributes());
        $this->assertDatabaseCount('bookings', 0);
    }

    private function review(Room $room, int $rating): void
    {
        $customer = User::factory()->create();
        $booking = DB::table('bookings')->insertGetId([
            'booking_code' => 'UC7-REVIEW-'.(DB::table('bookings')->count() + 1),
            'customer_id' => $customer->id, 'room_id' => $room->id,
            'check_in_date' => '2026-01-01', 'check_out_date' => '2026-01-02',
            'guest_count' => 1, 'number_of_nights' => 1, 'price_per_night' => '100.00',
            'room_total' => '100.00', 'status' => 'COMPLETED',
        ]);
        DB::table('reviews')->insert(['booking_id' => $booking, 'customer_id' => $customer->id,
            'property_id' => $room->property_id, 'rating' => $rating, 'comment' => 'UC7 fixture review']);
    }

    public function test_rating_uses_real_property_average_excludes_unreviewed_and_combines_with_room_filters(): void
    {
        $this->room(); // No review: must not be treated as five stars.
        $high = $this->room(extra: ['price_per_night' => '100.00']);
        $this->review($high, 4); $this->review($high, 5);
        $low = $this->room(); $this->review($low, 3);
        $perfect = $this->room(extra: ['capacity' => 4, 'price_per_night' => '200.00']);
        $this->review($perfect, 5);
        $this->getJson('/api/public/rooms?min_rating=4.5')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $high->id)->assertJsonPath('data.0.property.rating', 4.5)
            ->assertJsonPath('data.0.property.review_count', 2)->assertJsonPath('data.1.id', $perfect->id)
            ->assertJsonPath('meta.total', 2);
        $this->getJson('/api/public/rooms?min_rating=5')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $perfect->id);
        $this->getJson('/api/public/rooms?min_rating=4.5&guest_count=3')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $perfect->id);
        $this->getJson('/api/public/rooms?min_rating=4.5&max_price=100.00')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $high->id);
        $this->getJson('/api/public/rooms')->assertOk()->assertJsonPath('meta.total', 4);
        $this->getJson('/api/public/rooms?min_rating=5.1')->assertUnprocessable()->assertJsonValidationErrors('min_rating');
    }

    public function test_rating_filter_compares_unrounded_average(): void
    {
        $room = $this->room();
        // 4.45 displays as 4.5 but does not satisfy a minimum raw score of 4.5.
        for ($i = 0; $i < 20; ++$i) $this->review($room, $i < 9 ? 5 : 4);
        $this->getJson('/api/public/rooms')->assertOk()->assertJsonPath('data.0.property.rating', 4.5)->assertJsonPath('data.0.property.review_count', 20);
        $this->getJson('/api/public/rooms?min_rating=4.5')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/public/rooms?min_rating=4.4')->assertOk()->assertJsonCount(1, 'data');
    }
}
