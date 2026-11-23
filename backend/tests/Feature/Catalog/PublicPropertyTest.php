<?php

namespace Tests\Feature\Catalog;

use App\DTOs\Catalog\Requests\PublicPropertyListInputRequest;
use App\DTOs\Catalog\Responses\PublicPropertyDetailResponse;
use App\DTOs\Catalog\Responses\PublicPropertyListItemResponse;
use App\DTOs\Catalog\Responses\PublicRoomResponse;
use App\Mappers\Catalog\CatalogMapper;
use App\Models\User;
use App\Models\Property;
use App\Models\PropertyVerification;
use App\Models\PropertyImage;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\RoomImage;
use App\Models\Amenity;
use App\Models\Review;
use App\Models\Booking;
use App\Repositories\Catalog\CatalogRepository;
use App\Validators\Catalog\CatalogValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\ObjectMapper\ObjectMapper;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

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

    public function test_list_keeps_the_exact_item_and_paginator_contract(): void
    {
        $this->property();
        $property = $this->property();
        $this->property();
        $this->room($property, '0.00');
        PropertyImage::create([
            'property_id' => $property->id,
            'image_path' => 'https://example.com/primary.jpg',
            'is_primary' => true,
        ]);

        $response = $this->getJson('/api/public/properties?page=2&per_page=1')->assertOk();
        $this->assertEqualsCanonicalizing(['data', 'links', 'meta'], array_keys($response->json()));
        $this->assertEqualsCanonicalizing(
            ['current_page', 'from', 'last_page', 'links', 'path', 'per_page', 'to', 'total'],
            array_keys($response->json('meta')),
        );
        $response->assertJsonPath('data', [[
            'id' => $property->id,
            'name' => 'Catalog fixture',
            'type' => 'HOTEL',
            'address' => 'Public property address',
            'description' => '<script>fixture</script>',
            'check_in_time' => '14:00:00',
            'check_out_time' => '12:00:00',
            'primary_image' => 'https://example.com/primary.jpg',
            'starting_price' => '0.00',
            'currency' => 'VND',
            'price_unit' => 'night',
            'rating' => null,
            'review_count' => 0,
            'is_verified' => false,
        ]])->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.links.2.active', true);

        $path = url('/api/public/properties');
        $response->assertJsonPath('links', [
            'first' => $path.'?per_page=1&page=1',
            'last' => $path.'?per_page=1&page=3',
            'prev' => $path.'?per_page=1&page=1',
            'next' => $path.'?per_page=1&page=3',
        ])->assertJsonPath('meta.links.2.url', $path.'?per_page=1&page=2');
    }

    public function test_list_keeps_json_field_messages_and_the_html_redirect(): void
    {
        $path = '/api/public/properties?page=0&city=Hue';
        $errors = [
            'page' => ['Trang phải là số nguyên từ 1 đến 2147483647.'],
            'city' => ['Danh sách cơ sở chỉ hỗ trợ page và per_page; chưa lọc phòng trống theo ngày.'],
        ];

        $this->getJson($path)->assertUnprocessable()->assertJsonPath('errors', $errors);
        $this->from('/properties')->get($path, ['Accept' => 'text/html', 'Origin' => 'http://localhost:5173'])
            ->assertRedirect('/properties')
            ->assertSessionHasErrors(['page' => $errors['page'][0], 'city' => $errors['city'][0]])
            ->assertSessionHasInput('page', '0')
            ->assertSessionHasInput('city', 'Hue');
    }

    public static function paginationInputs(): array
    {
        return [
            'defaults' => [[], [], [1, 12], []],
            'upper bounds' => [['page' => '2147483647', 'per_page' => '24'], [], [2147483647, 24], []],
            'integer values' => [['page' => 2, 'per_page' => 3], [], [2, 3], []],
            'integer floats' => [['page' => 2.0, 'per_page' => 3.0], [], [2, 3], []],
            'true' => [['page' => true, 'per_page' => true], [], [1, 1], []],
            'signed strings' => [['page' => '+2', 'per_page' => '+3'], [], [2, 3], []],
            'padded strings' => [['page' => ' 2 ', 'per_page' => "\t3\n"], [], [2, 3], []],
            'ignored body fields' => [['page' => 2, 'ignored' => 'value'], [], [2, 12], []],
            'null' => [['page' => null, 'per_page' => null], [], null, ['page', 'per_page']],
            'empty strings' => [['page' => '', 'per_page' => ' '], [], null, ['page', 'per_page']],
            'false' => [['page' => false, 'per_page' => false], [], null, ['page', 'per_page']],
            'zero' => [['page' => 0, 'per_page' => '0'], [], null, ['page', 'per_page']],
            'negative' => [['page' => -1, 'per_page' => '-1'], [], null, ['page', 'per_page']],
            'fractional' => [['page' => 1.5, 'per_page' => '1.5'], [], null, ['page', 'per_page']],
            'decimal strings' => [['page' => '1.0', 'per_page' => '2.0'], [], null, ['page', 'per_page']],
            'exponent strings' => [['page' => '1e0', 'per_page' => '2e0'], [], null, ['page', 'per_page']],
            'leading zeros' => [['page' => '01', 'per_page' => '02'], [], null, ['page', 'per_page']],
            'arrays' => [['page' => [], 'per_page' => ['1']], [], null, ['page', 'per_page']],
            'objects' => [['page' => new \stdClass(), 'per_page' => new \stdClass()], [], null, ['page', 'per_page']],
            'page overflow' => [['page' => '2147483648'], [], null, ['page']],
            'per page overflow' => [['per_page' => 25], [], null, ['per_page']],
            'native overflow' => [['page' => '9223372036854775808'], [], null, ['page']],
            'field error order' => [['per_page' => 25, 'page' => 0], ['city' => 'Hue', 'keyword' => 'stay'], null, ['page', 'per_page', 'city', 'keyword']],
        ];
    }

    #[DataProvider('paginationInputs')]
    public function test_raw_pagination_contract_and_error_order_without_queries(array $input, array $query, ?array $expected, array $fields): void
    {
        $messages = [
            'page' => 'Trang phải là số nguyên từ 1 đến 2147483647.',
            'per_page' => 'Số cơ sở mỗi trang phải là số nguyên từ 1 đến 24.',
        ];
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            try {
                $dto = app(CatalogValidator::class)->validatePublicPropertyList($input, $query);
                $this->assertSame($expected, [$dto->page, $dto->perPage]);
            } catch (ValidationException $exception) {
                $this->assertNull($expected);
                $this->assertSame($fields, array_keys($exception->errors()));
                foreach ($fields as $field) {
                    $this->assertSame([$messages[$field] ?? 'Danh sách cơ sở chỉ hỗ trợ page và per_page; chưa lọc phòng trống theo ngày.'], $exception->errors()[$field]);
                }
            }
            $this->assertSame([], DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }

    public function test_list_keeps_json_body_precedence_and_duplicate_query_parsing(): void
    {
        $this->json('GET', '/api/public/properties?page=0', ['page' => true, 'per_page' => 2.0])
            ->assertOk()->assertJsonPath('meta.current_page', 1)->assertJsonPath('meta.per_page', 2);
        $this->getJson('/api/public/properties?page=0&page=2&per_page=1')
            ->assertOk()->assertJsonPath('meta.current_page', 2);
        $this->getJson('/api/public/properties?page=2&page=0')->assertUnprocessable()->assertJsonValidationErrors('page');
    }

    public function test_public_list_validates_raw_values_through_symfony_with_an_explicit_group(): void
    {
        $engine = app(ValidatorInterface::class);
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects($this->once())->method('validate')->willReturnCallback(
            function (object $input, $constraints, $groups) use ($engine) {
                $this->assertInstanceOf(PublicPropertyListInputRequest::class, $input);
                $this->assertTrue($input->page);
                $this->assertSame(2.0, $input->per_page);
                $this->assertNull($constraints);
                $this->assertSame(['catalog-public-list'], $groups);

                return $engine->validate($input, $constraints, $groups);
            },
        );
        $this->app->instance(ValidatorInterface::class, $validator);

        $dto = app(CatalogValidator::class)->validatePublicPropertyList(['page' => true, 'per_page' => 2.0], []);
        $this->assertSame([1, 2], [$dto->page, $dto->perPage]);
    }

    public function test_container_maps_a_loaded_public_property_through_symfony_without_queries(): void
    {
        $this->property();
        $property = $this->app->make(CatalogRepository::class)->paginatePublicProperties(12, 1)->items()[0];
        $symfony = $this->app->make(ObjectMapperInterface::class);
        $this->assertInstanceOf(ObjectMapper::class, $symfony);
        $mapper = $this->createMock(ObjectMapperInterface::class);
        $mapper->expects($this->once())->method('map')->willReturnCallback(
            function (object $source, string $target) use ($symfony): object {
                $this->assertSame(PublicPropertyListItemResponse::class, $target);
                $this->assertFalse(property_exists($source, 'host_id'));
                $this->assertFalse(property_exists($source, 'phone'));

                return $symfony->map($source, $target);
            },
        );
        $this->app->instance(ObjectMapperInterface::class, $mapper);

        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $item = $this->app->make(CatalogMapper::class)->toPublicListItem($property);
            $this->assertSame($property->id, $item->id);
            $this->assertNull($item->primary_image);
            $this->assertNull($item->starting_price);
            $this->assertSame([], DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
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
            'starting_price', 'currency', 'price_unit', 'rating', 'review_count', 'is_verified', 'images', 'rooms', 'amenities']; sort($expected);
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

    public function test_detail_preserves_the_exact_nested_response_contract(): void
    {
        $property = $this->property();
        $room = $this->room($property);
        $first = Amenity::create(['name' => 'Wifi', 'status' => true]);
        $second = Amenity::create(['name' => 'Bếp', 'status' => true]);
        $room->amenities()->attach([$second->id, $first->id]);
        $anotherRoom = $this->room($property, '900000.00');
        $anotherRoom->amenities()->attach($first->id);
        PropertyImage::create([
            'property_id' => $property->id, 'image_path' => 'https://example.com/property.jpg', 'is_primary' => true,
        ]);
        RoomImage::create(['room_id' => $room->id, 'image_path' => 'https://example.com/room.jpg']);

        $amenities = [['id' => $first->id, 'name' => 'Wifi'], ['id' => $second->id, 'name' => 'Bếp']];
        $this->getJson('/api/public/properties/'.$property->id)->assertOk()->assertExactJson(['data' => [
            'id' => $property->id,
            'name' => 'Catalog fixture',
            'type' => 'HOTEL',
            'address' => 'Public property address',
            'description' => '<script>fixture</script>',
            'check_in_time' => '14:00:00',
            'check_out_time' => '12:00:00',
            'primary_image' => 'https://example.com/property.jpg',
            'starting_price' => '500000.25',
            'currency' => 'VND',
            'price_unit' => 'night',
            'rating' => null,
            'review_count' => 0,
            'is_verified' => false,
            'images' => ['https://example.com/property.jpg'],
            'rooms' => [
                [
                    'id' => $room->id, 'room_number' => '101', 'name' => 'Public room',
                    'description' => null, 'capacity' => 2, 'price_per_night' => '500000.25',
                    'room_type' => ['id' => $room->room_type_id, 'name' => 'Public room type'],
                    'images' => ['https://example.com/room.jpg'], 'amenities' => $amenities,
                ],
                [
                    'id' => $anotherRoom->id, 'room_number' => '101', 'name' => 'Public room',
                    'description' => null, 'capacity' => 2, 'price_per_night' => '900000.00',
                    'room_type' => ['id' => $anotherRoom->room_type_id, 'name' => 'Public room type'],
                    'images' => [], 'amenities' => [$amenities[0]],
                ],
            ],
            'amenities' => $amenities,
        ]]);
    }

    public function test_verification_matches_current_address_across_public_catalog_and_filtered_search(): void
    {
        $property = $this->property();
        $this->room($property);
        $entry = PropertyVerification::create([
            'property_id' => $property->id, 'submitted_by' => $property->host_id,
            'status' => PropertyVerification::APPROVED, 'address_snapshot' => $property->address,
            'submitted_at' => now(), 'document_path' => 'private/fixture.pdf', 'document_mime' => 'application/pdf', 'document_size' => 10,
        ]);
        $routes = [
            '/api/public/properties' => 'data.0',
            '/api/public/properties/'.$property->id => 'data',
            '/api/public/properties-search?keyword=Catalog&guest_count=2' => 'data.0',
        ];
        foreach ($routes as $url => $key) {
            $this->getJson($url)->assertOk()->assertJsonPath($key.'.is_verified', true)
                ->assertJsonMissingPath($key.'.document_path')->assertJsonMissingPath($key.'.address_snapshot');
        }
        $entry->update(['address_snapshot' => strtolower($property->address)]);
        foreach ($routes as $url => $key) {
            $this->getJson($url)->assertOk()->assertJsonPath($key.'.is_verified', false);
        }
        $entry->update(['address_snapshot' => $property->address]);
        foreach ([PropertyVerification::SUBMITTED, PropertyVerification::REJECTED, PropertyVerification::INVALIDATED] as $status) {
            $entry->update(['status' => $status]);
            $this->getJson('/api/public/properties/'.$property->id)->assertOk()->assertJsonPath('data.is_verified', false);
        }
    }

    public function test_detail_keeps_route_then_query_then_property_id_precedence(): void
    {
        $path = '/api/public/properties/';
        $message = 'Chi tiết cơ sở không nhận bộ lọc phòng trống hoặc tham số truy vấn.';
        $this->getJson($path.'abc?city=Hue')->assertNotFound();
        foreach (['0', '01', str_repeat('9', 40), '999999'] as $id) {
            $this->getJson($path.$id.'?city=Hue')->assertUnprocessable()
                ->assertJsonPath('errors', ['city' => [$message]]);
            $this->getJson($path.$id)->assertNotFound()
                ->assertExactJson(['message' => 'Không tìm thấy cơ sở lưu trú.']);
        }
        $this->from('/properties')->get($path.'01?city=Hue', ['Accept' => 'text/html', 'Origin' => 'http://localhost:5173'])
            ->assertRedirect('/properties')
            ->assertSessionHasErrors(['city' => $message])
            ->assertSessionHasInput('city', 'Hue');
    }

    public function test_container_maps_and_serializes_detail_and_rooms_through_symfony_without_queries(): void
    {
        $property = $this->property();
        $room = $this->room($property);
        $amenity = Amenity::create(['name' => 'Wifi', 'status' => true]);
        $room->amenities()->attach($amenity->id);
        $loaded = $this->app->make(CatalogRepository::class)->findPublicProperty($property->id);
        $symfony = $this->app->make(ObjectMapperInterface::class);
        $this->assertInstanceOf(ObjectMapper::class, $symfony);
        $targets = [];
        $mapper = $this->createMock(ObjectMapperInterface::class);
        $mapper->expects($this->exactly(3))->method('map')->willReturnCallback(
            function (object $source, string $target) use ($symfony, &$targets): object {
                $targets[] = $target;
                $this->assertFalse(property_exists($source, 'host_id'));
                $this->assertFalse(property_exists($source, 'phone'));

                return $symfony->map($source, $target);
            },
        );
        $this->app->instance(ObjectMapperInterface::class, $mapper);

        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $detail = $this->app->make(CatalogMapper::class)->toPublicDetail($loaded);
            $json = json_decode(json_encode($detail, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame([
                PublicRoomResponse::class, PublicPropertyListItemResponse::class, PublicPropertyDetailResponse::class,
            ], $targets);
            $this->assertInstanceOf(PublicRoomResponse::class, $detail->rooms[0]);
            $this->assertSame($property->id, $json['id']);
            $this->assertSame('500000.25', $json['rooms'][0]['price_per_night']);
            $this->assertSame([['id' => $amenity->id, 'name' => 'Wifi']], $json['amenities']);
            $this->assertSame([], DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
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
