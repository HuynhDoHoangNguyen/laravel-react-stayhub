<?php

namespace Tests\Feature\Review;

use App\Mappers\Review\ReviewMapper;
use App\Models\Booking;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Repositories\Review\ReviewRepository;
use App\Services\Review\ReviewService;
use App\Validators\Review\ReviewCreationValidator;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Psr\Log\LoggerInterface;
use RuntimeException;

class ReviewCreationCommitTest extends TestCase
{
    private const CONNECTION = 'review_commit_test';
    private ?string $previousConnection = null;
    private Booking $booking;
    private User $customer;

    public function createApplication()
    {
        foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:',
            'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'] as $key => $expected) {
            if (($_ENV[$key] ?? getenv($key)) !== $expected) throw new RuntimeException('Commit tests require isolated SQLite in-memory.');
        }
        if (is_file(dirname(__DIR__, 3).'/bootstrap/cache/config.php')) throw new RuntimeException('Cached configuration is unsafe.');
        $app = parent::createApplication();
        $config = $app['config'];
        if ($config->get('database.default') !== 'sqlite'
            || $config->get('database.connections.sqlite.database') !== ':memory:'
            || !empty($config->get('database.connections.sqlite.url'))
            || $config->get('cache.default') !== 'array' || $config->get('session.driver') !== 'array') {
            throw new RuntimeException('Effective commit test configuration is unsafe.');
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousConnection = DB::getDefaultConnection();
        config(['database.connections.'.self::CONNECTION => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        DB::setDefaultConnection(self::CONNECTION);
        $connection = DB::connection();
        if ($connection->getDriverName() !== 'sqlite' || $connection->getDatabaseName() !== ':memory:') {
            throw new RuntimeException('Owned connection must be SQLite in-memory before migrations.');
        }
        $connection->setTransactionManager(new DatabaseTransactionsManager());
        $this->artisan('migrate', ['--database' => self::CONNECTION, '--force' => true])->assertExitCode(0);
        $this->customer = User::factory()->create(['role' => 'CUSTOMER', 'status' => 'ACTIVE']);
        $host = User::factory()->create(['role' => 'HOST', 'status' => 'ACTIVE']);
        $property = Property::create(['host_id' => $host->id, 'name' => 'Commit fixture', 'address' => 'Fixture address', 'type' => 'HOTEL']);
        $type = RoomType::create(['property_id' => $property->id, 'name' => 'Fixture type']);
        $room = Room::create(['property_id' => $property->id, 'room_type_id' => $type->id, 'room_number' => '101',
            'name' => 'Fixture room', 'capacity' => 2, 'price_per_night' => '100.00', 'status' => 'ACTIVE']);
        $this->booking = Booking::create(['booking_code' => 'REVIEW-COMMIT', 'customer_id' => $this->customer->id,
            'room_id' => $room->id, 'check_in_date' => '2026-01-01', 'check_out_date' => '2026-01-02',
            'guest_count' => 1, 'number_of_nights' => 1, 'price_per_night' => '100.00', 'room_total' => '100.00', 'status' => 'COMPLETED']);
        $this->assertSame(0, $connection->transactionLevel());
        $this->assertFalse($connection->getPdo()->inTransaction());
    }

    protected function tearDown(): void
    {
        try {
            if ($this->previousConnection !== null) {
                $connection = DB::connection(self::CONNECTION);
                while ($connection->transactionLevel() > 0) $connection->rollBack();
                DB::purge(self::CONNECTION);
                DB::setDefaultConnection($this->previousConnection);
            }
        } finally {
            parent::tearDown();
        }
    }

    private function service(LoggerInterface $logger, ?ReviewRepository $repository = null): ReviewService
    {
        return new ReviewService($repository ?? app(ReviewRepository::class), app(ReviewCreationValidator::class), app(ReviewMapper::class), $logger);
    }

    public function test_success_log_waits_for_actual_outer_commit_and_has_exact_safe_context(): void
    {
        $calls = [];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')->willReturnCallback(function ($message, $context) use (&$calls) {
            $this->assertSame(0, DB::transactionLevel());
            $this->assertFalse(DB::connection()->getPdo()->inTransaction());
            $calls[] = [$message, $context];
        });
        DB::beginTransaction();
        $review = $this->service($logger)->createReview($this->booking->id, $this->customer,
            ['rating' => 5, 'comment' => 'Actual commit fixture.']);
        $this->assertSame([], $calls);
        $this->assertTrue(DB::connection()->getPdo()->inTransaction());
        DB::commit();
        $this->assertSame([['Tạo đánh giá thành công', ['review_id' => $review->id, 'booking_id' => $this->booking->id,
            'property_id' => $review->property_id, 'rating' => 5]]], $calls);
        $this->assertDatabaseCount('reviews', 1);
    }

    public function test_outer_rollback_removes_review_and_discards_success_callback(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('info');
        DB::beginTransaction();
        $this->service($logger)->createReview($this->booking->id, $this->customer, ['rating' => 5, 'comment' => 'Rollback fixture.']);
        $this->assertDatabaseCount('reviews', 1);
        DB::rollBack();
        $this->assertDatabaseCount('reviews', 0);
        DB::transaction(static fn () => null);
    }

    public function test_failure_after_insert_rolls_back_and_does_not_emit_success_on_later_commit(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('info');
        $repository = $this->getMockBuilder(ReviewRepository::class)->onlyMethods(['loadReviewRelations'])->getMock();
        $failure = new RuntimeException('Controlled post-insert failure.');
        $repository->expects($this->once())->method('loadReviewRelations')->willReturnCallback(function () use ($failure) {
            $this->assertDatabaseCount('reviews', 1);
            throw $failure;
        });
        try {
            $this->service($logger, $repository)->createReview($this->booking->id, $this->customer,
                ['rating' => 5, 'comment' => 'Post-insert failure fixture.']);
            $this->fail('Expected controlled failure.');
        } catch (RuntimeException $error) {
            $this->assertSame($failure, $error);
        }
        $this->assertDatabaseCount('reviews', 0);
        $this->assertSame(0, DB::transactionLevel());
        DB::transaction(static fn () => null);
    }
}
