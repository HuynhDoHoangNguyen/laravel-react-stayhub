<?php

namespace Tests\Support;

use App\Services\Review\ReviewService;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\TestCase;
use Psr\Log\AbstractLogger;
use RuntimeException;

/** No automatic outer transaction: these tests must observe durable MySQL commits. */
abstract class ReviewCreateLifecycleMySqlTestCase extends TestCase
{
    protected array $manifest;
    protected ReviewCreateAcceptanceFixtures $fixture;
    protected string $caseDirectory;
    protected array $sourceBefore;
    protected array $reviewsBefore;
    protected array $expectedNew = [];
    protected array $successLogs = [];
    private static array $migrated = [];

    public function createApplication()
    {
        if (getenv('STAYHUB_TV3_AC_REVIEW_LIFECYCLE') !== '1') {
            $this->markTestSkipped('Review lifecycle requires its explicit owned MySQL suite; no database booted.');
        }
        $this->manifest = Tv3AcceptanceMySqlEnvironment::beforeBoot(dirname(__DIR__, 2));
        $app = parent::createApplication();
        Tv3AcceptanceMySqlEnvironment::assertApplication($app, $this->manifest);
        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $db = $this->app['db']->connection();
        Tv3AcceptanceMySqlEnvironment::assertApplication($this->app, $this->manifest);
        if (!isset(self::$migrated[$this->manifest['database']])) {
            if ($db->getSchemaBuilder()->getTables() !== []) throw new RuntimeException('Review lifecycle refuses a nonempty schema.');
            $this->artisan('migrate', ['--no-interaction' => true])->assertExitCode(0);
            self::$migrated[$this->manifest['database']] = true;
        }
        $root = realpath(getenv('STAYHUB_TV3_AC_LIFECYCLE_ARTIFACTS') ?: '');
        $manifestDirectory = realpath(dirname(getenv('STAYHUB_TV3_AC_MANIFEST')));
        if (!$root || dirname($root) !== $manifestDirectory || basename($root) !== 'lifecycle') {
            throw new RuntimeException('Review lifecycle requires runner-owned artifacts.');
        }
        $this->caseDirectory = $root.'/case-'.bin2hex(random_bytes(16));
        if (!mkdir($this->caseDirectory)) throw new RuntimeException('Cannot create case evidence.');
        $this->fixture = $db->transaction(fn () => (new ReviewCreateAcceptanceFixtures())->create());
        $this->assertSame(0, $db->transactionLevel());
        $this->assertFalse($db->getPdo()->inTransaction());
        $this->sourceBefore = $this->fixture->sourceFingerprint();
        $this->reviewsBefore = $this->fixture->reviewRows();
        $this->travelTo(\Carbon\CarbonImmutable::parse(ReviewCreateAcceptanceFixtures::ISO));
    }

    protected function bookingId(): int { return $this->fixture->bookings['SUCCESS_A']['id']; }
    protected function url(): string { return '/api/customer/bookings/'.$this->bookingId().'/review'; }
    protected function payload(string $label = 'A'): array
    {
        return ['rating' => $label === 'A' ? 5 : 2, 'comment' => 'Review lifecycle worker '.$label];
    }

    protected function observer(): Connection
    {
        config(['database.connections.review_create_observer' => config('database.connections.mysql')]);
        $reader = $this->app['db']->connection('review_create_observer');
        $identity = (array) $reader->selectOne('SELECT DATABASE() AS db, VERSION() AS version, @@server_uuid AS uuid, CURRENT_USER() AS account, CONNECTION_ID() AS connection_id');
        Tv3AcceptanceMySqlEnvironment::assertIdentity($identity, $this->manifest);
        $writer = $this->app['db']->connection()->selectOne('SELECT CONNECTION_ID() AS id');
        $this->assertNotSame((int) $writer->id, (int) $identity['connection_id']);
        $this->assertSame(0, $reader->transactionLevel());
        $this->assertFalse($reader->getPdo()->inTransaction());
        $this->assertSame(1, $reader->table('bookings')->where('id', $this->bookingId())->count());
        return $reader;
    }

    protected function captureSuccessLogs(): void
    {
        $logger = new class($this) extends AbstractLogger {
            public function __construct(private ReviewCreateLifecycleMySqlTestCase $test) {}
            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->test->recordLog($level, (string) $message, $context);
            }
        };
        $this->app->instance(ReviewService::class, $this->app->make(ReviewService::class, ['logger' => $logger]));
    }

    public function recordLog(string $level, string $message, array $context): void
    {
        $db = $this->app['db']->connection();
        $this->successLogs[] = ['level' => $level, 'message' => $message, 'context' => $context,
            'transaction_level' => $db->transactionLevel(), 'pdo_transaction' => $db->getPdo()->inTransaction(),
            'observer_reviews' => $this->observer()->table('reviews')->where('booking_id', $this->bookingId())->count()];
    }

    protected function assertOutput(Connection $reader, string $label = 'A'): int
    {
        $rows = $reader->table('reviews')->where('booking_id', $this->bookingId())->get();
        $this->assertCount(1, $rows);
        $id = (int) $rows[0]->id;
        $payload = $this->payload($label);
        $expected = $this->fixture->expectedStored('SUCCESS_A', $id, $payload['rating'], $payload['comment']);
        $this->assertSame($expected, (array) $rows[0]);
        $this->expectedNew = [$expected];
        return $id;
    }

    protected function assertNoOutput(Connection $reader): void
    {
        $this->assertSame(0, $reader->table('reviews')->where('booking_id', $this->bookingId())->count());
        $this->assertSame($this->reviewsBefore, $reader->table('reviews')->orderBy('id')->get()->map(static fn ($r) => (array) $r)->all());
    }

    protected function assertOneCommittedLog(array $logs, int $id, string $label = 'A'): void
    {
        $this->assertCount(1, $logs);
        $this->assertSame('info', $logs[0]['level']);
        $this->assertSame('Tạo đánh giá thành công', $logs[0]['message']);
        $this->assertSame(['review_id' => $id, 'booking_id' => $this->bookingId(),
            'property_id' => $this->fixture->bookings['SUCCESS_A']['property_id'], 'rating' => $this->payload($label)['rating']], $logs[0]['context']);
        $this->assertSame(0, $logs[0]['transaction_level']);
        $this->assertFalse($logs[0]['pdo_transaction']);
        $this->assertSame(1, $logs[0]['observer_reviews']);
    }

    protected function assertCustomerReadBack(int $id, string $label = 'A'): void
    {
        $payload = $this->payload($label);
        $response = $this->actingAs($this->fixture->actors['C_A'])->getJson('/api/customer/reviews')->assertOk();
        $rows = array_values(array_filter($response->json('data'), static fn ($row) => $row['id'] === $id));
        $this->assertSame([$this->fixture->expectedCustomer('SUCCESS_A', $id, $payload['rating'], $payload['comment'])], $rows);
    }

    protected function evidence(string $name, array $data): void
    {
        file_put_contents($this->caseDirectory.'/'.$name.'.json', json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    protected function tearDown(): void
    {
        try {
            if (isset($this->app, $this->fixture)) {
                $db = $this->app['db']->connection();
                while ($db->transactionLevel() > 0) $db->rollBack();
                $this->assertSame($this->sourceBefore, $this->fixture->sourceFingerprint());
                $this->assertSame([...$this->reviewsBefore, ...$this->expectedNew], $this->fixture->reviewRows(), 'Only the explicitly expected Review may persist.');
                Tv3AcceptanceMySqlEnvironment::assertApplication($this->app, $this->manifest);
                $this->evidence('case', ['test' => $this->name(), 'logs' => $this->successLogs,
                    'source_unchanged' => true, 'review_ledger_valid' => true, 'transaction_level' => $db->transactionLevel()]);
                $actorIds = array_map(static fn ($actor) => $actor->id, $this->fixture->actors);
                $bookingIds = array_column($this->fixture->bookings, 'id');
                $propertyIds = array_values($this->fixture->properties);
                $db->transaction(function () use ($db, $actorIds, $bookingIds, $propertyIds): void {
                    $db->table('reviews')->whereIn('booking_id', $bookingIds)->delete();
                    $db->table('bookings')->whereIn('id', $bookingIds)->delete();
                    $db->table('rooms')->whereIn('property_id', $propertyIds)->delete();
                    $db->table('room_types')->whereIn('property_id', $propertyIds)->delete();
                    $db->table('properties')->whereIn('id', $propertyIds)->delete();
                    $db->table('users')->whereIn('id', $actorIds)->delete();
                });
                $this->app['db']->purge('review_create_observer');
                $this->app['db']->purge('mysql');
            }
        } finally {
            parent::tearDown();
        }
    }
}
