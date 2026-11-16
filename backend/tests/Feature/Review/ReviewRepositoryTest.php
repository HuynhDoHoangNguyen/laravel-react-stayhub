<?php

namespace Tests\Feature\Review;

use App\DTOs\Review\Requests\HostReviewFilterRequest;
use App\DTOs\Review\Responses\CustomerReviewResponse;
use App\DTOs\Review\Responses\HostReviewResponse;
use Illuminate\Auth\Access\AuthorizationException;
use App\Mappers\Review\ReviewMapper;
use App\DTOs\Review\Requests\PublicReviewFilterRequest;
use App\DTOs\Review\Responses\PublicReviewResponse;
use App\Common\ApiResponse;
use App\Validators\Review\ReviewValidator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use App\Models\Booking;
use App\Models\Property;
use App\Models\Review;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Repositories\Review\ReviewRepository;
use App\Services\Review\ReviewQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use RuntimeException;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;

class ReviewRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private ReviewRepository $repository;
    private User $host;
    private User $otherHost;
    private User $customer;
    private User $otherCustomer;
    private Property $property;
    private Property $secondProperty;
    private Property $foreignProperty;
    private array $reviews;

    public function createApplication()
    {
        foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:',
            'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'] as $key => $expected) {
            if (($_ENV[$key] ?? getenv($key)) !== $expected) {
                throw new RuntimeException('Review repository tests require an isolated in-memory environment.');
            }
        }
        if (is_file(dirname(__DIR__, 3).'/bootstrap/cache/config.php')) {
            throw new RuntimeException('Cached configuration is unsafe for repository tests.');
        }
        $app = parent::createApplication();
        $config = $app['config'];
        if ($config->get('database.default') !== 'sqlite'
            || $config->get('database.connections.sqlite.database') !== ':memory:'
            || !empty($config->get('database.connections.sqlite.url'))
            || $config->get('cache.default') !== 'array' || $config->get('session.driver') !== 'array') {
            throw new RuntimeException('Effective configuration is unsafe before RefreshDatabase.');
        }
        $connection = $app['db']->connection();
        if ($connection->getDriverName() !== 'sqlite' || $connection->getDatabaseName() !== ':memory:') {
            throw new RuntimeException('Effective connection must be SQLite in-memory.');
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(ReviewRepository::class);
        $this->host = $this->actor('HOST');
        $this->otherHost = $this->actor('HOST');
        $this->customer = $this->actor('CUSTOMER');
        $this->otherCustomer = $this->actor('CUSTOMER');
        $this->property = $this->property($this->host);
        $this->secondProperty = $this->property($this->host);
        $this->foreignProperty = $this->property($this->otherHost);
        $this->reviews = [
            $this->review($this->property, $this->customer, 5, '2026-01-02 00:00:00'),
            $this->review($this->property, $this->otherCustomer, 5, '2026-01-02 00:00:00'),
            $this->review($this->secondProperty, $this->customer, 2, '2026-01-01 00:00:00'),
            $this->review($this->foreignProperty, $this->otherCustomer, 1, '2026-01-03 00:00:00'),
        ];
    }

    private function actor(string $role): User
    {
        return User::factory()->create(['role' => $role, 'status' => 'ACTIVE', 'name' => 'Repository Fixture']);
    }

    private function property(User $host): Property
    {
        return Property::create(['host_id' => $host->id, 'name' => 'Fixture property', 'type' => 'HOTEL',
            'address' => 'Fixture address', 'status' => 'INACTIVE']);
    }

    private function review(Property $property, User $customer, int $rating, string $createdAt): Review
    {
        $type = RoomType::create(['property_id' => $property->id, 'name' => 'Fixture type']);
        $room = Room::create(['property_id' => $property->id, 'room_type_id' => $type->id,
            'room_number' => '101', 'name' => 'Fixture room', 'capacity' => 2, 'price_per_night' => '100.00', 'status' => 'ACTIVE']);
        $booking = Booking::create(['booking_code' => 'REPO-'.($type->id), 'customer_id' => $customer->id,
            'room_id' => $room->id, 'check_in_date' => '2025-12-01', 'check_out_date' => '2025-12-02',
            'guest_count' => 1, 'number_of_nights' => 1, 'price_per_night' => '100.00',
            'room_total' => '100.00', 'status' => 'COMPLETED']);

        $review = Review::create(['booking_id' => $booking->id, 'customer_id' => $customer->id,
            'property_id' => $property->id, 'rating' => $rating, 'comment' => 'Common searchable phrase']);
        // created_at is deliberately not mass assignable on the production Model.
        $review->forceFill(['created_at' => $createdAt])->save();

        return $review;
    }

    public function test_create_repository_relations_facts_and_domain_validation_are_explicit_and_pure(): void
    {
        $bookingId = $this->reviews[0]->booking_id;
        $booking = $this->repository->findBookingForReviewWithLock($bookingId);
        $this->assertNotNull($booking);
        $this->assertTrue($booking->relationLoaded('review'));
        $this->assertTrue($booking->relationLoaded('room'));
        $this->assertTrue($booking->getRelation('room')->relationLoaded('property'));
        $this->assertTrue($this->repository->reviewExistsForBooking($bookingId));
        $this->assertFalse($this->repository->reviewExistsForBooking(999999));
        $this->assertNull($this->repository->findBookingForReviewWithLock(999999));
        $booking->setRelation('review', null);
        $validator = app(\App\Validators\Review\ReviewCreationValidator::class);
        $connection = $this->app['db']->connection();
        Log::spy();
        $before = serialize($booking);
        $connection->enableQueryLog();
        $connection->flushQueryLog();
        try {
            $this->assertSame($this->property->id, $validator->validateForCreation($booking, $this->customer,
                ['rating' => 5, 'comment' => 'Valid domain input.'], false));
            $attributes = app(ReviewMapper::class)->toCreationAttributes($booking, $this->customer, $this->property->id,
                ['rating' => '5', 'comment' => '  Valid projection.  ']);
            $this->assertSame('Valid projection.', $attributes['comment']);
            try {
                $validator->validateCreationDetails($booking, ['rating' => 6, 'comment' => 'x'], true);
                $this->fail('Expected duplicate fact error.');
            } catch (\App\Services\Review\DuplicateReviewException) {
                $this->assertSame($before, serialize($booking));
            }
            $this->assertSame([], $connection->getQueryLog());
            foreach (['log', 'info', 'debug', 'warning', 'error'] as $method) Log::shouldNotHaveReceived($method);
        } finally {
            $connection->disableQueryLog();
            $connection->flushQueryLog();
        }
        $review = $this->repository->loadReviewRelations($this->reviews[0]->fresh());
        foreach (['booking', 'property', 'customer'] as $relation) $this->assertTrue($review->relationLoaded($relation));
    }

    public function test_property_facts_do_not_add_visibility_filters_or_cross_host_ownership(): void
    {
        $this->assertTrue($this->repository->propertyExists($this->property->id));
        $this->assertFalse($this->repository->propertyExists(999999));
        $this->assertTrue($this->repository->hostOwnsProperty($this->host->id, $this->property->id));
        $this->assertFalse($this->repository->hostOwnsProperty($this->host->id, $this->foreignProperty->id));
        $this->assertFalse($this->repository->hostOwnsProperty($this->host->id, 999999));
    }

    public function test_public_stable_order_and_clamping_preserve_property_scope(): void
    {
        foreach (['latest', 'rating_desc', 'rating_asc'] as $sort) {
            $page = $this->repository->getPublicPropertyReviews($this->property->id, ['sort_by' => $sort], 100);
            $this->assertSame([$this->reviews[1]->id, $this->reviews[0]->id], $page->getCollection()->modelKeys());
            $this->assertSame(50, $page->perPage());
            $this->assertSame(2, $page->total());
        }
        $this->assertSame(1, $this->repository->getPublicPropertyReviews($this->property->id, [], 0)->perPage());
        $this->assertSame(0, $this->repository->getPublicPropertyReviews($this->property->id, ['rating' => 2])->total());
    }

    public function test_host_search_is_grouped_and_customer_order_is_id_descending(): void
    {
        // Every foreign row matches both search branches: an ungrouped OR would leak it.
        foreach (['Common', 'Repository Fixture'] as $search) {
            $page = $this->repository->getHostReviews($this->host->id, ['search' => $search]);
            $this->assertSame([$this->reviews[1]->id, $this->reviews[0]->id, $this->reviews[2]->id], $page->getCollection()->modelKeys());
        }
        $this->assertSame(0, $this->repository->getHostReviews($this->host->id, ['property_id' => $this->foreignProperty->id])->total());
        $this->assertSame(1, $this->repository->getHostReviews($this->host->id, ['property_id' => $this->secondProperty->id, 'rating' => 2])->total());
        $page = $this->repository->getCustomerReviews($this->customer->id);
        // Newer id has an older created_at: Customer ordering must still be id DESC.
        $this->assertSame([$this->reviews[2]->id, $this->reviews[0]->id], $page->getCollection()->modelKeys());
    }

    public function test_aggregate_facts_and_service_rounding_keep_empty_and_host_scopes(): void
    {
        $this->reviews[2]->update(['rating' => 3]);
        $stats = $this->repository->getHostReviewStatistics($this->host->id);
        $this->assertSame(3, (int) $stats['total_count']);
        $this->assertSame(2, (int) $stats['star_5']);
        $service = app(ReviewQueryService::class);
        $this->assertSame([
            'total_reviews' => 3, 'average_rating' => 4.3,
            'rating_breakdown' => [5 => 2, 4 => 0, 3 => 1, 2 => 0, 1 => 0],
        ], $service->getHostReviewSummary($this->host));
        $this->assertSame(3.0, $service->getHostReviewSummary($this->host, $this->secondProperty->id)['average_rating']);
        $this->assertSame([
            'average_rating' => null, 'review_count' => 0,
            'rating_breakdown' => [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0],
        ], $service->getPropertyReviewSummary(999999));
        $this->assertSame(0, $service->getHostReviewSummary($this->host, $this->foreignProperty->id)['total_reviews']);
    }

    public function test_public_validator_does_not_query_or_log_for_valid_or_invalid_input(): void
    {
        Log::spy();
        $validator = app(ReviewValidator::class);
        $connection = $this->app['db']->connection();
        $connection->enableQueryLog();
        $connection->flushQueryLog();
        try {
            $this->assertSame(['rating' => '5'], $validator->validatePublicQuery(['rating' => '5']));
            $this->assertSame(1, $validator->validatePublicPropertyId('1'));
            $this->assertNull($validator->validatePublicPropertyId('01'));
            try {
                $validator->validatePublicQuery(['rating' => 6]);
                $this->fail('Expected validation failure.');
            } catch (ValidationException $error) {
                $this->assertArrayHasKey('rating', $error->errors());
            }
            $this->assertSame([], $connection->getQueryLog());
            foreach (['log', 'info', 'debug', 'warning', 'error'] as $method) {
                Log::shouldNotHaveReceived($method);
            }
        } finally {
            $connection->disableQueryLog();
            $connection->flushQueryLog();
        }
    }

    public function test_typed_customer_page_preserves_role_scope_id_order_and_pure_serialization(): void
    {
        $service = app(ReviewQueryService::class);
        $filters = new \App\DTOs\Review\Requests\CustomerReviewFilterRequest(1, 1);
        $connection = $this->app['db']->connection();
        Log::spy();
        $connection->enableQueryLog();
        $connection->flushQueryLog();
        try {
            foreach ([$this->host, new User(['role' => 'ADMIN'])] as $actor) {
                try {
                    $service->getCustomerReviewPage($actor, $filters);
                    $this->fail('Expected Customer role denial before queries.');
                } catch (AuthorizationException $error) {
                    $this->assertSame('Bạn không có quyền thực hiện chức năng này.', $error->getMessage());
                }
            }
            $this->assertSame([], $connection->getQueryLog());
            $page = $service->getCustomerReviewPage($this->customer, $filters);
            $this->assertSame(2, $page->total());
            $this->assertInstanceOf(\App\DTOs\Review\Responses\CustomerReviewResponse::class, $page->items()[0]);
            $this->assertSame($this->reviews[2]->id, $page->items()[0]->id);
            $second = $service->getCustomerReviewPage($this->customer,
                new \App\DTOs\Review\Requests\CustomerReviewFilterRequest(2, 1));
            $this->assertSame($this->reviews[0]->id, $second->items()[0]->id);
            $connection->flushQueryLog();
            $body = ApiResponse::page($page)->getData(true);
            $this->assertSame('2026-01-01T00:00:00.000000Z', $body['data'][0]['created_at']);
            $this->assertSame(['data', 'links', 'meta'], array_keys($body));
            $this->assertSame([], $connection->getQueryLog());
            foreach (['log', 'info', 'debug', 'warning', 'error'] as $method) Log::shouldNotHaveReceived($method);
        } finally {
            $connection->disableQueryLog();
            $connection->flushQueryLog();
        }
    }

    public function test_typed_host_role_denial_precedes_queries_and_host_mapping_does_not_query_or_log(): void
    {
        $service = app(ReviewQueryService::class);
        $filters = new HostReviewFilterRequest(2, 1, null, null, null);
        $connection = $this->app['db']->connection();
        Log::spy();
        $connection->enableQueryLog();
        $connection->flushQueryLog();
        try {
            foreach ([$this->customer, $this->otherCustomer] as $actor) {
                try {
                    $service->getHostReviewPage($actor, $filters);
                    $this->fail('Expected Host role denial before queries.');
                } catch (AuthorizationException $error) {
                    $this->assertSame('Bạn không có quyền truy cập chức năng này.', $error->getMessage());
                }
            }
            $validator = app(ReviewValidator::class);
            $this->assertSame(['search' => null], $validator->validateHostQuery(['search' => null]));
            try {
                $validator->validateHostQuery(['property_id' => '01']);
                $this->fail('Expected validation failure.');
            } catch (ValidationException $error) {
                $this->assertArrayHasKey('property_id', $error->errors());
            }
            $this->assertSame([], $connection->getQueryLog());
            $page = $service->getHostReviewPage($this->host, $filters);
            $this->assertSame(2, $page->currentPage());
            $this->assertSame(3, $page->total());
            $this->assertInstanceOf(HostReviewResponse::class, $page->items()[0]);
            $connection->flushQueryLog();
            $data = ApiResponse::page($page)->getData(true);
            $this->assertSame($this->reviews[0]->id, $data['data'][0]['id']);
            $this->assertSame('2026-01-02T00:00:00.000000Z', $data['data'][0]['created_at']);
            $this->assertSame([], $connection->getQueryLog());
            foreach (['log', 'info', 'debug', 'warning', 'error'] as $method) Log::shouldNotHaveReceived($method);
        } finally {
            $connection->disableQueryLog();
            $connection->flushQueryLog();
        }
    }

    public function test_typed_public_page_is_explicit_and_serializes_without_queries(): void
    {
        $page = app(ReviewQueryService::class)->getPublicReviewPage(
            new PublicReviewFilterRequest($this->property->id, 2, 1, null, 'latest'),
        );
        $this->assertSame(2, $page->currentPage());
        $this->assertSame(2, $page->total());
        $this->assertInstanceOf(PublicReviewResponse::class, $page->items()[0]);
        $connection = $this->app['db']->connection();
        $connection->enableQueryLog();
        $connection->flushQueryLog();
        try {
            $data = ApiResponse::page($page)->getData(true);
            $this->assertSame($this->reviews[0]->id, $data['data'][0]['id']);
            $this->assertSame('2026-01-02T00:00:00.000000Z', $data['data'][0]['created_at']);
            $this->assertSame([], $connection->getQueryLog());
        } finally {
            $connection->disableQueryLog();
            $connection->flushQueryLog();
        }
    }

    public function test_container_maps_all_review_response_dtos_through_symfony_without_queries(): void
    {
        $review = $this->reviews[0]->load(['booking.room', 'property', 'customer']);
        $review->forceFill(['updated_at' => '2026-01-03 00:00:00'])->syncOriginal();
        $before = serialize($review);
        $commonFields = [
            'id' => $review->id,
            'rating' => 5,
            'comment' => 'Common searchable phrase',
            'created_at' => '2026-01-02T00:00:00.000000Z',
        ];
        $relationFields = [
            'booking_id' => $review->booking_id,
            'booking_code' => $review->booking->booking_code,
            'property_id' => $this->property->id,
            'property_name' => 'Fixture property',
            'room_name' => 'Fixture room',
        ];
        $expected = [
            PublicReviewResponse::class => $commonFields + ['author' => 'Repository F.'],
            HostReviewResponse::class => $commonFields + $relationFields + ['customer_name' => 'Repository Fixture'],
            CustomerReviewResponse::class => $commonFields + $relationFields + ['updated_at' => '2026-01-03T00:00:00.000000Z'],
        ];
        $engine = app(ObjectMapperInterface::class);
        $targets = [];
        $spy = $this->createMock(ObjectMapperInterface::class);
        $spy->expects($this->exactly(3))->method('map')
            ->willReturnCallback(function (object $source, $target) use (&$targets, $engine, $expected) {
                $this->assertInstanceOf(\stdClass::class, $source);
                $this->assertArrayHasKey($target, $expected);
                $targets[] = $target;
                $fields = get_object_vars($source);
                foreach (['created_at', 'updated_at'] as $timestamp) {
                    if (array_key_exists($timestamp, $fields)) {
                        $this->assertInstanceOf(\Carbon\CarbonInterface::class, $fields[$timestamp]);
                        $fields[$timestamp] = $fields[$timestamp]->toISOString();
                    }
                }
                $expectedFields = $expected[$target];
                ksort($expectedFields);
                ksort($fields);
                $this->assertSame($expectedFields, $fields);

                return $engine->map($source, $target);
            });
        $this->app->instance(ObjectMapperInterface::class, $spy);
        $connection = $this->app['db']->connection();
        $connection->enableQueryLog();
        $connection->flushQueryLog();
        try {
            $mapper = app(ReviewMapper::class);
            $responses = [
                $mapper->toPublicResponse($review),
                $mapper->toHostResponse($review),
                $mapper->toCustomerResponse($review),
            ];
            foreach ($responses as $response) {
                $this->assertTrue((new \ReflectionClass($response))->isReadOnly());
                $fields = $response->jsonSerialize();
                $expectedFields = $expected[$response::class];
                ksort($expectedFields);
                ksort($fields);
                $this->assertSame($expectedFields, $fields);
            }
            $this->assertSame(array_keys($expected), $targets);
            $this->assertSame([], $connection->getQueryLog());
            $this->assertSame($before, serialize($review));
        } finally {
            $connection->disableQueryLog();
            $connection->flushQueryLog();
        }
    }

    public function test_all_read_dtos_serialize_loaded_relations_without_queries(): void
    {
        $public = $this->repository->getPublicPropertyReviews($this->property->id)->items();
        $host = $this->repository->getHostReviews($this->host->id)->items();
        $customer = $this->repository->getCustomerReviews($this->customer->id)->items();
        foreach ($public as $review) $this->assertTrue($review->relationLoaded('customer'));
        foreach (array_merge($host, $customer) as $review) {
            $this->assertTrue($review->relationLoaded('booking'));
            $this->assertTrue($review->booking->relationLoaded('room'));
            $this->assertTrue($review->relationLoaded('property'));
        }
        $connection = $this->app['db']->connection();
        $connection->enableQueryLog();
        $connection->flushQueryLog();
        try {
            $mapper = app(ReviewMapper::class);
            $publicData = ['data' => array_map(fn ($review) => $mapper->toPublicResponse($review)->jsonSerialize(), $public)];
            $hostData = ['data' => array_map(fn ($review) => $mapper->toHostResponse($review)->jsonSerialize(), $host)];
            $customerData = ['data' => array_map(fn ($review) => $mapper->toCustomerResponse($review)->jsonSerialize(), $customer)];
            $this->assertSame([], $connection->getQueryLog());
            $this->assertSame('Repository F.', $publicData['data'][0]['author']);
            $this->assertSame('Fixture room', $hostData['data'][0]['room_name']);
            $this->assertSame('Fixture property', $customerData['data'][0]['property_name']);
        } finally {
            $connection->disableQueryLog();
            $connection->flushQueryLog();
        }
    }
}
