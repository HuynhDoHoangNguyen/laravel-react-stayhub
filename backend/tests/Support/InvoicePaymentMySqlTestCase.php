<?php

namespace Tests\Support;

use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase;
use RuntimeException;

abstract class InvoicePaymentMySqlTestCase extends TestCase
{
    private const OBSERVER = 'uc36_payment_observer';

    /** Independent, autocommit reader on the same guarded schema, never a transaction snapshot. */
    protected function observer(): \Illuminate\Database\Connection
    {
        $config = config('database.connections.mysql');
        config(['database.connections.'.self::OBSERVER => $config]);
        $reader = $this->app['db']->connection(self::OBSERVER);
        $identity = $reader->selectOne('SELECT DATABASE() AS db, @@server_uuid AS uuid, CONNECTION_ID() AS id');
        $writer = $this->app['db']->connection()->selectOne('SELECT CONNECTION_ID() AS id');
        $this->assertSame(getenv('STAYHUB_UC36_TEST_DATABASE'), $identity->db);
        $this->assertSame(getenv('STAYHUB_UC36_TEST_SERVER_UUID'), $identity->uuid);
        $this->assertNotSame($writer->id, $identity->id);
        $this->assertSame(0, $reader->transactionLevel());

        return $reader;
    }

    protected function paymentService(\Psr\Log\LoggerInterface $logger, ?\App\Mappers\Invoice\InvoiceMapper $mapper = null): \App\Services\Invoice\InvoicePaymentService
    {
        return new \App\Services\Invoice\InvoicePaymentService(
            $this->app->make(\App\Repositories\Invoice\InvoiceRepository::class),
            $this->app->make(\App\Validators\Invoice\InvoicePaymentValidator::class),
            $mapper ?? $this->app->make(\App\Mappers\Invoice\InvoiceMapper::class), $logger,
            $this->app->make(\App\Services\Commission\CommissionChargeService::class),
        );
    }

    protected function tearDown(): void
    {
        try {
            if (isset($this->app)) {
                $db = $this->app['db'];
                while ($db->connection()->transactionLevel() > 0) {
                    $db->connection()->rollBack();
                }
                $db->purge(self::OBSERVER);
            }
        } finally {
            parent::tearDown();
        }
    }

    /** Pure pre-boot guard, also exercised with unsafe targets without opening a connection. */
    public static function assertTestEnvironment(array $env): string
    {
        $database = $env['DB_DATABASE'] ?? '';
        if (($env['APP_ENV'] ?? null) !== 'testing' || ($env['DB_CONNECTION'] ?? null) !== 'mysql'
            || ($env['DB_HOST'] ?? null) !== '127.0.0.1' || ($env['DB_PORT'] ?? null) !== '3307'
            || ($env['DB_URL'] ?? null) !== '' || ($env['DB_SOCKET'] ?? null) !== ''
            || ($env['CACHE_STORE'] ?? null) !== 'array' || ($env['SESSION_DRIVER'] ?? null) !== 'array'
            || !is_string($database) || preg_match('/\Astayhub_uc36_test_[a-f0-9]{32}\z/', $database) !== 1
            || $database !== ($env['STAYHUB_UC36_TEST_DATABASE'] ?? null)
            || preg_match('/\Auc36_[a-f0-9]{16}\z/', $env['DB_USERNAME'] ?? '') !== 1
            || empty($env['STAYHUB_UC36_TEST_SERVER_UUID'])) {
            throw new RuntimeException('UC36 tests refuse any target other than an explicitly owned MySQL fixture.');
        }

        return $database;
    }

    public function createApplication()
    {
        if (!getenv('STAYHUB_UC36_TEST_DATABASE')) {
            $this->markTestSkipped('UC36 requires an explicitly provisioned MySQL Docker schema; no database booted.');
        }
        $keys = ['APP_ENV', 'DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_URL', 'DB_SOCKET', 'DB_DATABASE',
            'DB_USERNAME', 'CACHE_STORE', 'SESSION_DRIVER', 'STAYHUB_UC36_TEST_DATABASE', 'STAYHUB_UC36_TEST_SERVER_UUID'];
        $env = [];
        foreach ($keys as $key) {
            $env[$key] = $_ENV[$key] ?? getenv($key);
        }
        $database = self::assertTestEnvironment($env);
        if (is_file(dirname(__DIR__, 2).'/bootstrap/cache/config.php')) {
            throw new RuntimeException('UC36 tests refuse cached configuration.');
        }
        $app = parent::createApplication();
        $config = $app['config'];
        $connection = $config->get('database.connections.mysql');
        if ($config->get('database.default') !== 'mysql' || $config->get('app.env') !== 'testing'
            || ($connection['host'] ?? null) !== '127.0.0.1' || (string) ($connection['port'] ?? '') !== '3307'
            || ($connection['database'] ?? null) !== $database || ($connection['username'] ?? null) !== $env['DB_USERNAME']
            || !empty($connection['url']) || !empty($connection['unix_socket'])
            || $config->get('cache.default') !== 'array' || $config->get('session.driver') !== 'array') {
            throw new RuntimeException('Effective UC36 configuration differs from the owned target.');
        }
        $target = $app['db']->connection()->selectOne('SELECT DATABASE() AS db, VERSION() AS version, @@server_uuid AS uuid');
        if ($target->db !== $database || !str_starts_with($target->version, '8.4.')
            || $target->uuid !== $env['STAYHUB_UC36_TEST_SERVER_UUID']) {
            throw new RuntimeException('Selected MySQL schema or instance differs from the owned target.');
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        // No wrapping transaction: the actual commit/no-op log is observable in the primary tests.
        if (!$this->app['db']->getSchemaBuilder()->hasTable('invoices')) {
            if ($this->app['db']->getSchemaBuilder()->getTables() !== []) {
                throw new RuntimeException('Refuse migration of a nonempty, uninitialized fixture schema.');
            }
            $this->artisan('migrate', ['--no-interaction' => true])->assertExitCode(0);
        }
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-17T01:00:00Z'));
    }

    protected function actor(string $role): User
    {
        // Faker's uniqueness registry resets between app boots; persisted MySQL fixtures do not.
        return User::factory()->create(['email' => bin2hex(random_bytes(16)).'@uc36.example.test',
            'phone' => '0900000000', 'role' => $role, 'status' => 'ACTIVE']);
    }

    protected function fixture(string $status = 'UNPAID', ?string $paidAt = null): array
    {
        $host = $this->actor('HOST');
        $customer = $this->actor('CUSTOMER');
        $property = Property::create(['host_id' => $host->id, 'name' => 'UC36 fixture', 'type' => 'HOTEL',
            'address' => 'Fixture address', 'status' => 'ACTIVE']);
        $type = RoomType::create(['property_id' => $property->id, 'name' => 'UC36 room type']);
        $room = Room::create(['property_id' => $property->id, 'room_type_id' => $type->id,
            'room_number' => '001', 'name' => 'UC36 room', 'capacity' => 2, 'price_per_night' => '100.25', 'status' => 'ACTIVE']);
        $booking = Booking::create(['booking_code' => 'UC36-'.bin2hex(random_bytes(8)),
            'customer_id' => $customer->id, 'room_id' => $room->id, 'check_in_date' => '2026-11-10',
            'check_out_date' => '2026-11-12', 'guest_count' => 2, 'number_of_nights' => 2,
            'price_per_night' => '100.25', 'room_total' => '200.50', 'status' => 'COMPLETED']);
        $invoice = Invoice::create(['invoice_code' => 'UC36-'.bin2hex(random_bytes(8)), 'booking_id' => $booking->id,
            'customer_name' => 'Customer snapshot', 'customer_email' => 'fixture@example.test', 'customer_phone' => '0900000000',
            'property_name' => 'Property snapshot', 'room_name' => 'Room snapshot', 'subtotal' => '213.25',
            'discount_amount' => '10.00', 'extra_fee' => '5.25', 'total_amount' => '208.50',
            'payment_status' => $status, 'paid_at' => $paidAt]);
        $invoice->items()->createMany([
            ['item_type' => 'ROOM', 'item_name' => 'Room snapshot', 'quantity' => '2.00', 'unit_price' => '100.25', 'amount' => '200.50'],
            ['item_type' => 'SERVICE', 'item_name' => 'Service snapshot', 'quantity' => '1.00', 'unit_price' => '12.75', 'amount' => '12.75'],
        ]);
        // Snapshot persisted column order, not the attribute insertion order of a newly created model.
        $invoice->refresh();
        $booking->refresh();

        return compact('host', 'customer', 'property', 'room', 'booking', 'invoice');
    }

    protected function endpoint(int|string $id): string
    {
        return '/api/host/invoices/'.$id.'/payment-status';
    }
}
