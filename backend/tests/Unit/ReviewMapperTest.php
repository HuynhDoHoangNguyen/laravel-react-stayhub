<?php

namespace Tests\Unit;

use App\Mappers\EloquentObjectMapper;
use App\Mappers\Review\ReviewMapper;
use App\Models\Review;
use App\Models\User;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ObjectMapper\ObjectMapper;
use Symfony\Component\PropertyAccess\PropertyAccess;

class ReviewMapperTest extends TestCase
{
    private function mapper(): ReviewMapper
    {
        return new ReviewMapper(new EloquentObjectMapper(
            new ObjectMapper(propertyAccessor: PropertyAccess::createPropertyAccessor()),
        ));
    }

    public function test_host_filters_keep_defaults_and_search_semantics(): void
    {
        $mapper = $this->mapper();
        $dto = $mapper->toHostFilterRequest([]);
        $this->assertSame([1, 10, null, null, null], [$dto->page, $dto->perPage, $dto->propertyId, $dto->rating, $dto->search]);
        $input = ['page' => '+2', 'per_page' => '50', 'property_id' => '3', 'rating' => true, 'search' => '  0%_  '];
        $before = $input;
        $dto = $mapper->toHostFilterRequest($input);
        $this->assertSame([2, 50, 3, 1, '  0%_  '], [$dto->page, $dto->perPage, $dto->propertyId, $dto->rating, $dto->search]);
        $this->assertSame($before, $input);
    }

    public function test_host_projection_preserves_full_customer_name_and_explicit_fields(): void
    {
        $review = new Review();
        $review->setRawAttributes(['id' => '7', 'booking_id' => '9', 'property_id' => '3',
            'rating' => '4', 'comment' => '<b>literal comment</b>', 'created_at' => null], true);
        $booking = new \App\Models\Booking(['booking_code' => 'BK-9']);
        $booking->setRelation('room', new \App\Models\Room(['name' => 'Room 9']));
        $review->setRelation('booking', $booking);
        $review->setRelation('property', new \App\Models\Property(['name' => 'Property 3']));
        $review->setRelation('customer', new User(['name' => 'Trần Thị Thu Thảo', 'email' => 'private@example.test', 'phone' => '0912345678']));
        $before = $review->getAttributes();
        $relationsBefore = serialize($review->getRelations());
        $dto = $this->mapper()->toHostResponse($review);
        $expected = ['id' => 7, 'booking_id' => 9, 'booking_code' => 'BK-9', 'property_id' => 3,
            'property_name' => 'Property 3', 'room_name' => 'Room 9', 'customer_name' => 'Trần Thị Thu Thảo',
            'rating' => 4, 'comment' => '<b>literal comment</b>', 'created_at' => null];
        $this->assertSame($expected, $dto->jsonSerialize());
        $this->assertSame($expected, json_decode(json_encode($dto, JSON_THROW_ON_ERROR), true));
        $this->assertSame($before, $review->getAttributes());
        $this->assertSame($relationsBefore, serialize($review->getRelations()));
    }

    public function test_host_mapping_keeps_loaded_null_and_unloaded_fallbacks_without_lazy_loading(): void
    {
        $review = new Review();
        $review->setRawAttributes(['id' => 7, 'booking_id' => 9, 'property_id' => 3, 'rating' => 4, 'comment' => 'text'], true);
        $review->exists = true;
        $mapper = $this->mapper();
        foreach ([false, true] as $loaded) {
            if ($loaded) {
                foreach (['booking', 'property', 'customer'] as $relation) $review->setRelation($relation, null);
            }
            $dto = $mapper->toHostResponse($review);
            $this->assertNull($dto->booking_code);
            $this->assertNull($dto->property_name);
            $this->assertNull($dto->room_name);
            $this->assertSame('Khách lưu trú', $dto->customer_name);
        }
        $review->setRelation('customer', new User(['name' => '']));
        $this->assertSame('', $mapper->toHostResponse($review)->customer_name);
    }

    public function test_customer_pagination_preserves_sources_fallbacks_and_raw_input(): void
    {
        $mapper = $this->mapper();
        $cases = [
            [[], [], 1, 10],
            [['page' => 2, 'per_page' => 50], [], 2, 10],
            [['page' => 2, 'per_page' => 50], ['page' => '1', 'per_page' => '2'], 2, 2],
            [['per_page' => 2], ['per_page' => 'bad'], 1, 10],
            [['page' => [], 'per_page' => []], ['per_page' => []], 1, 10],
            [['page' => null, 'per_page' => null], ['per_page' => null], 1, 10],
            [['page' => false, 'per_page' => false], ['per_page' => false], 1, 10],
            [['page' => true, 'per_page' => true], ['per_page' => true], 1, 10],
            [['page' => 2.0, 'per_page' => 2.9], ['per_page' => 2.9], 2, 2],
            [['page' => '+2', 'per_page' => '1e2'], ['per_page' => '1e2'], 2, 50],
            [['page' => '01', 'per_page' => '01'], ['per_page' => '01'], 1, 1],
            [['page' => '1e2', 'per_page' => '-1'], ['per_page' => '-1'], 1, 1],
            [['page' => '2.9', 'per_page' => '0'], ['per_page' => '0'], 1, 1],
            [['page' => 0, 'per_page' => ''], ['per_page' => ''], 1, 10],
        ];
        foreach ($cases as [$input, $query, $page, $perPage]) {
            $before = [$input, $query];
            $dto = $mapper->toCustomerFilterRequest($input, $query);
            $this->assertSame([$page, $perPage], [$dto->page, $dto->perPage]);
            $this->assertSame($before, [$input, $query]);
        }
    }

    public function test_customer_projection_preserves_explicit_fields_and_null_relations_without_mutation(): void
    {
        $review = new Review();
        $review->setRawAttributes(['id' => '7', 'booking_id' => '9', 'property_id' => '3', 'rating' => '4',
            'comment' => '<b>literal text</b>', 'created_at' => null, 'updated_at' => null], true);
        $review->exists = true;
        $mapper = $this->mapper();
        $expected = ['id' => 7, 'booking_id' => 9, 'booking_code' => null, 'property_id' => 3,
            'property_name' => null, 'room_name' => null, 'rating' => 4, 'comment' => '<b>literal text</b>',
            'created_at' => null, 'updated_at' => null];
        $this->assertSame($expected, $mapper->toCustomerResponse($review)->jsonSerialize());
        foreach (['booking', 'property'] as $name) $review->setRelation($name, null);
        $this->assertSame($expected, $mapper->toCustomerResponse($review)->jsonSerialize());
        $booking = new \App\Models\Booking(['booking_code' => 'BK-9']);
        $booking->setRelation('room', new \App\Models\Room(['name' => 'Room 9']));
        $review->setRelation('booking', $booking);
        $review->setRelation('property', new \App\Models\Property(['name' => 'Property 3']));
        $review->setRelation('customer', new User(['name' => 'Private name', 'email' => 'private@example.test']));
        $expected['booking_code'] = 'BK-9';
        $expected['room_name'] = 'Room 9';
        $expected['property_name'] = 'Property 3';
        $before = [$review->getAttributes(), serialize($review->getRelations())];
        $dto = $mapper->toCustomerResponse($review);
        $this->assertSame($expected, json_decode(json_encode($dto, JSON_THROW_ON_ERROR), true));
        $this->assertSame($before, [$review->getAttributes(), serialize($review->getRelations())]);
        $this->assertFalse($review->isDirty());
    }

    public function test_create_projection_uses_server_identities_and_preserves_trim_cast_behavior(): void
    {
        $booking = new \App\Models\Booking(['customer_id' => 81]);
        $booking->id = 7;
        $actor = new User();
        $actor->id = 81;
        $data = ['rating' => '5', 'comment' => '  Text with Unicode á  ', 'booking_id' => 999,
            'customer_id' => 999, 'property_id' => 999, 'role' => 'ADMIN'];
        $before = [serialize($booking), serialize($actor), $data];
        $this->assertSame(['booking_id' => 7, 'customer_id' => 81, 'property_id' => 42,
            'rating' => 5, 'comment' => 'Text with Unicode á'],
            $this->mapper()->toCreationAttributes($booking, $actor, 42, $data));
        $this->assertSame($before, [serialize($booking), serialize($actor), $data]);
    }

    public static function authorNames(): array
    {
        return [
            [null, 'Khách lưu trú'], ['', 'Khách lưu trú'], ['  ', 'Khách lưu trú'],
            ['Thảo', 'Thảo'], ['  Trần Thị Thu Thảo  ', 'Trần T.'],
            ["Nguyễn\tVăn\nAn", 'Nguyễn A.'], ['Đặng ánh', 'Đặng Á.'], ['0', '0'],
        ];
    }

    public function test_create_request_normalizes_rating_and_uses_route_booking_id(): void
    {
        $mapper = $this->mapper();
        $input = ['rating' => '+4', 'comment' => 'Literal review text', 'booking_id' => 99, 'customer_id' => 88];
        $before = $input;
        $command = $mapper->toCreateRequest(7, $input);

        $this->assertSame(['bookingId' => 7, 'rating' => 4, 'comment' => 'Literal review text'], get_object_vars($command));
        $this->assertSame($before, $input);
        $this->assertTrue($mapper->toCreateRequest(7, ['rating' => true, 'comment' => 'Literal review text'])->rating);
    }

    #[DataProvider('authorNames')]
    public function test_anonymization_preserves_unicode_whitespace_and_fallbacks(?string $name, string $expected): void
    {
        $this->assertSame($expected, $this->mapper()->anonymizeName($name));
    }

    public function test_filter_defaults_and_normalization_do_not_mutate_input(): void
    {
        $mapper = $this->mapper();
        $defaults = $mapper->toPublicFilterRequest(7, []);
        $this->assertSame([7, 1, 10, null, 'latest'], [
            $defaults->propertyId, $defaults->page, $defaults->perPage, $defaults->rating, $defaults->sortBy,
        ]);
        $input = ['page' => '+2', 'per_page' => '50', 'rating' => '5', 'sort_by' => 'rating_desc'];
        $before = $input;
        $filters = $mapper->toPublicFilterRequest(7, $input);
        $this->assertSame([7, 2, 50, 5, 'rating_desc'], [
            $filters->propertyId, $filters->page, $filters->perPage, $filters->rating, $filters->sortBy,
        ]);
        $this->assertSame($before, $input);
    }

    public function test_projection_and_serialization_only_export_public_fields_without_mutation(): void
    {
        $review = new Review();
        $review->setRawAttributes(['id' => '7', 'rating' => '5', 'comment' => '<script>literal text</script>',
            'customer_id' => 81, 'booking_id' => 92, 'created_at' => null], true);
        $customer = new User(['name' => 'Trần Thị Thu Thảo', 'email' => 'private@example.test', 'phone' => '0912345678']);
        $review->setRelation('customer', $customer);
        $before = $review->getAttributes();
        $customerBefore = $customer->getAttributes();
        $dto = $this->mapper()->toPublicResponse($review);
        $expected = ['id' => 7, 'rating' => 5, 'comment' => '<script>literal text</script>', 'author' => 'Trần T.', 'created_at' => null];
        $this->assertSame($expected, $dto->jsonSerialize());
        $this->assertSame($expected, json_decode(json_encode($dto, JSON_THROW_ON_ERROR), true));
        $this->assertSame($before, $review->getAttributes());
        $this->assertSame($customerBefore, $customer->getAttributes());
        $this->assertFalse($review->isDirty());
        $review->setRelation('customer', null);
        $this->assertSame('Khách lưu trú', $this->mapper()->toPublicResponse($review)->author);
    }

    public function test_responses_preserve_utc_microseconds_and_missing_updated_at(): void
    {
        $review = (new Review())->setDateFormat('Y-m-d H:i:s');
        $review->setRawAttributes([
            'id' => '7', 'booking_id' => '9', 'property_id' => '3', 'rating' => '4', 'comment' => 'Text',
            'created_at' => '2026-11-14T08:02:03.456789+07:00',
        ], true);
        $review->setRelation('customer', null);
        $mapper = $this->mapper();
        $expected = '2026-11-14T01:02:03.456789Z';

        $this->assertSame($expected, $mapper->toPublicResponse($review)->created_at);
        $this->assertSame($expected, $mapper->toHostResponse($review)->created_at);
        $customer = $mapper->toCustomerResponse($review)->jsonSerialize();
        $this->assertSame($expected, $customer['created_at']);
        $this->assertArrayHasKey('updated_at', $customer);
        $this->assertNull($customer['updated_at']);

        $review->setRawAttributes($review->getAttributes() + ['updated_at' => '2026-11-14T08:03:04.654321+07:00'], true);
        $this->assertSame('2026-11-14T01:03:04.654321Z', $mapper->toCustomerResponse($review)->updated_at);
    }

    public function test_unloaded_customer_is_an_internal_error_instead_of_lazy_loading(): void
    {
        $review = new Review();
        $review->setRawAttributes(['id' => 1, 'customer_id' => 999], true);
        $review->exists = true;
        $this->expectException(LogicException::class);
        $this->mapper()->toPublicResponse($review);
    }
}
