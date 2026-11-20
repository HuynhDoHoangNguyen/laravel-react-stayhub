<?php

namespace Tests\Support;

use App\Services\Invoice\InvoiceService;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\TestCase;
use Psr\Log\AbstractLogger;
use RuntimeException;

/** Dedicated suite: committed fixtures, no RefreshDatabase or automatic wrapping transaction. */
abstract class InvoiceCreateLifecycleMySqlTestCase extends TestCase
{
    private static array $migrated = [];
    protected array $manifest;
    protected InvoiceCreateAcceptanceFixtures $fixture;
    protected string $caseDirectory;
    protected array $sourceBefore;
    protected array $successLogs = [];

    public function createApplication()
    {
        if (getenv('STAYHUB_TV3_AC_CREATE_LIFECYCLE') !== '1') {
            $this->markTestSkipped('Invoice create lifecycle requires its explicit owned MySQL suite; no database booted.');
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
            if ($db->getSchemaBuilder()->getTables() !== []) {
                throw new RuntimeException('Lifecycle refuses to adopt a nonempty schema.');
            }
            $this->artisan('migrate', ['--no-interaction' => true])->assertExitCode(0);
            self::$migrated[$this->manifest['database']] = true;
        }
        $root = realpath(getenv('STAYHUB_TV3_AC_LIFECYCLE_ARTIFACTS') ?: '');
        $manifestDirectory = realpath(dirname(getenv('STAYHUB_TV3_AC_MANIFEST')));
        if (!$root || dirname($root) !== $manifestDirectory || basename($root) !== 'lifecycle') {
            throw new RuntimeException('Lifecycle requires its runner-owned artifact directory.');
        }
        $this->caseDirectory = $root.'/case-'.bin2hex(random_bytes(16));
        if (!mkdir($this->caseDirectory)) throw new RuntimeException('Cannot create case evidence.');
        $this->fixture = $db->transaction(fn () => (new InvoiceCreateAcceptanceFixtures())->create());
        $this->assertSame(0, $db->transactionLevel());
        $this->assertFalse($db->getPdo()->inTransaction());
        $this->sourceBefore = $this->fixture->fingerprint();
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-19T05:00:00Z'));
    }

    protected function observer(): Connection
    {
        config(['database.connections.invoice_create_observer' => config('database.connections.mysql')]);
        $reader = $this->app['db']->connection('invoice_create_observer');
        $identity = (array) $reader->selectOne('SELECT DATABASE() AS db, VERSION() AS version, @@server_uuid AS uuid, CURRENT_USER() AS account, CONNECTION_ID() AS connection_id');
        Tv3AcceptanceMySqlEnvironment::assertIdentity($identity, $this->manifest);
        $writer = $this->app['db']->connection()->selectOne('SELECT CONNECTION_ID() AS id');
        $this->assertNotSame((int) $writer->id, (int) $identity['connection_id']);
        $this->assertSame(0, $reader->transactionLevel());
        $this->assertFalse($reader->getPdo()->inTransaction());
        $this->assertSame(1, $reader->table('bookings')->where('id', $this->fixture->bookingId)->count());
        return $reader;
    }

    protected function captureSuccessLogs(): void
    {
        $logger = new class($this) extends AbstractLogger {
            public function __construct(private InvoiceCreateLifecycleMySqlTestCase $test) {}
            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->test->recordLog($level, (string) $message, $context);
            }
        };
        $this->app->instance(InvoiceService::class, $this->app->make(InvoiceService::class, ['logger' => $logger]));
    }

    public function recordLog(string $level, string $message, array $context): void
    {
        $db = $this->app['db']->connection();
        $reader = $this->observer();
        $this->successLogs[] = ['level' => $level, 'message' => $message, 'context' => $context,
            'transaction_level' => $db->transactionLevel(), 'pdo_transaction' => $db->getPdo()->inTransaction(),
            'observer_invoices' => $reader->table('invoices')->where('booking_id', $this->fixture->bookingId)->count(),
            'observer_items' => $reader->table('invoice_items')->whereIn('invoice_id',
                $reader->table('invoices')->where('booking_id', $this->fixture->bookingId)->select('id'))->count()];
    }

    protected function assertOutput(Connection $reader): int
    {
        $rows = $reader->table('invoices')->where('booking_id', $this->fixture->bookingId)->get();
        $this->assertCount(1, $rows);
        $invoice = $rows[0];
        $this->assertSame('225.78', $invoice->subtotal);
        $this->assertSame('225.78', $invoice->total_amount);
        $this->assertSame('0.00', $invoice->discount_amount);
        $this->assertSame('0.00', $invoice->extra_fee);
        $this->assertSame('UNPAID', $invoice->payment_status);
        $this->assertNull($invoice->paid_at);
        $items = $reader->table('invoice_items')->where('invoice_id', $invoice->id)->orderBy('id')
            ->get(['item_type', 'item_name', 'description', 'quantity', 'unit_price', 'amount'])
            ->map(static fn ($row) => (array) $row)->all();
        $this->assertSame($this->fixture->items, $items);
        $this->assertSame(0, $reader->table('invoice_items')->leftJoin('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')->whereNull('invoices.id')->count());
        return (int) $invoice->id;
    }

    protected function assertNoOutput(Connection $reader): void
    {
        $this->assertSame(0, $reader->table('invoices')->where('booking_id', $this->fixture->bookingId)->count());
        $this->assertSame(0, $reader->table('invoice_items')->count());
    }

    protected function assertOneCommittedLog(array $logs, int $invoiceId): void
    {
        $this->assertCount(1, $logs);
        $this->assertSame('info', $logs[0]['level']);
        $this->assertSame('Tạo hóa đơn thành công', $logs[0]['message']);
        $this->assertSame(['invoice_id' => $invoiceId,
            'invoice_code' => $this->app['db']->table('invoices')->where('id', $invoiceId)->value('invoice_code'),
            'booking_id' => $this->fixture->bookingId, 'total_amount' => '225.78'], $logs[0]['context']);
        $this->assertSame(0, $logs[0]['transaction_level']);
        $this->assertFalse($logs[0]['pdo_transaction']);
        $this->assertSame(1, $logs[0]['observer_invoices']);
        $this->assertSame(3, $logs[0]['observer_items']);
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
                $this->assertSame($this->sourceBefore, $this->fixture->fingerprint(), 'Business source tables must remain unchanged.');
                Tv3AcceptanceMySqlEnvironment::assertApplication($this->app, $this->manifest);
                $this->evidence('case', ['test' => $this->name(), 'logs' => $this->successLogs,
                    'source_unchanged' => true, 'transaction_level' => $db->transactionLevel()]);
                // Only rows belonging to the actors/booking created by this case; preserve FK enforcement.
                $actorIds = array_map(static fn ($actor) => $actor->id, $this->fixture->actors);
                $db->transaction(function () use ($db, $actorIds): void {
                    $invoiceIds = $db->table('invoices')->where('booking_id', $this->fixture->bookingId)->pluck('id');
                    $db->table('invoice_items')->whereIn('invoice_id', $invoiceIds)->delete();
                    $db->table('invoices')->whereIn('id', $invoiceIds)->delete();
                    $db->table('booking_services')->where('booking_id', $this->fixture->bookingId)->delete();
                    $db->table('bookings')->where('id', $this->fixture->bookingId)->delete();
                    $propertyIds = $db->table('properties')->whereIn('host_id', $actorIds)->pluck('id');
                    $db->table('services')->whereIn('property_id', $propertyIds)->delete();
                    $db->table('rooms')->whereIn('property_id', $propertyIds)->delete();
                    $db->table('room_types')->whereIn('property_id', $propertyIds)->delete();
                    $db->table('properties')->whereIn('id', $propertyIds)->delete();
                    $db->table('users')->whereIn('id', $actorIds)->delete();
                });
                $this->app['db']->purge('invoice_create_observer');
                $this->app['db']->purge('mysql');
            }
        } finally {
            parent::tearDown();
        }
    }
}
