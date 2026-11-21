<?php

namespace Tests\Support;

use App\Models\InvoiceItem;
use App\Models\User;
use App\Services\Invoice\InvoiceService;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Throwable;

/** CLI-only test worker: real kernel/service/SQL; credentials only in inherited environment. */
final class InvoiceCreateRaceWorker
{
    public static function run(): void
    {
        $input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
        // Windows may omit explicitly empty environment entries; accept only protocol-supplied empties.
        foreach (['DB_URL', 'DB_SOCKET'] as $key) {
            if (getenv($key) === false && ($input['empty_environment'][$key] ?? null) === '') {
                $_ENV[$key] = $_SERVER[$key] = '';
            }
        }
        $root = dirname(__DIR__, 2);
        if (getenv('STAYHUB_TV3_AC_CREATE_LIFECYCLE') !== '1') throw new RuntimeException('Missing lifecycle opt-in.');
        $manifest = Tv3AcceptanceMySqlEnvironment::beforeBoot($root);
        $artifact = realpath(getenv('STAYHUB_TV3_AC_LIFECYCLE_ARTIFACTS') ?: '');
        $dir = realpath($input['directory'] ?? '');
        if (!$artifact || dirname($artifact) !== realpath(dirname(getenv('STAYHUB_TV3_AC_MANIFEST')))
            || basename($artifact) !== 'lifecycle' || !$dir || dirname($dir) !== $artifact
            || !preg_match('/\Acase-[a-f0-9]{32}\z/', basename($dir))
            || !in_array($input['label'] ?? '', ['A', 'B'], true)
            || !in_array($input['mode'] ?? '', ['commit', 'rollback', 'timeout'], true)
            || !is_bool($input['hold'] ?? null)
            || !is_int($input['booking_id'] ?? null) || $input['booking_id'] < 1
            || !is_int($input['host_id'] ?? null) || $input['host_id'] < 1) {
            throw new RuntimeException('Invalid runner-owned worker protocol.');
        }
        $label = $input['label'];
        $emit = static function (string $phase, array $data = []) use ($dir, $label): void {
            $path = $dir.'/'.$label.'-'.$phase.'.json';
            file_put_contents($path.'.tmp', json_encode($data, JSON_THROW_ON_ERROR), LOCK_EX);
            if (!rename($path.'.tmp', $path)) throw new RuntimeException('Cannot publish worker phase.');
        };
        $app = require $root.'/bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        Tv3AcceptanceMySqlEnvironment::assertApplication($app, $manifest);
        if ($app['config']->get('app.debug') !== false) throw new RuntimeException('Worker requires debug disabled.');
        $db = $app['db']->connection();
        $identity = (array) $db->selectOne('SELECT DATABASE() AS db, VERSION() AS version, @@server_uuid AS uuid, CONNECTION_ID() AS connection_id');
        $emit('ready', ['pid' => getmypid(), ...$identity]);
        $db->statement('SET SESSION innodb_lock_wait_timeout = '.($input['mode'] === 'timeout' ? '2' : '20'));
        $inserts = ['invoices' => 0, 'invoice_items' => 0];
        $lockingAttempts = 0;
        $db->beforeExecuting(static function (string $sql) use ($emit, &$lockingAttempts): void {
            if (str_contains(strtolower($sql), 'from `bookings`') && str_contains(strtolower($sql), 'for update')) {
                $lockingAttempts++;
                $emit('locking');
            }
        });
        $db->listen(static function (QueryExecuted $query) use (&$inserts): void {
            foreach (array_keys($inserts) as $table) {
                if (str_starts_with(strtolower($query->sql), 'insert into `'.$table.'`')) $inserts[$table]++;
            }
        });
        $reported = [];
        $app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class)->reportable(
            static function (Throwable $exception) use (&$reported): void {
                $reported[] = ['class' => $exception::class,
                    'sqlstate' => $exception instanceof QueryException ? ($exception->errorInfo[0] ?? null) : null,
                    'driver_code' => $exception instanceof QueryException ? ($exception->errorInfo[1] ?? null) : null];
            }
        )->stop();
        $logger = new class($dir, $label, $db, $app, $input['booking_id'], $manifest) extends AbstractLogger {
            public function __construct(private string $directory, private string $label,
                private \Illuminate\Database\Connection $db, private \Illuminate\Foundation\Application $app,
                private int $bookingId, private array $manifest) {}
            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->app['config']->set('database.connections.invoice_create_log_observer', $this->app['config']->get('database.connections.mysql'));
                $reader = $this->app['db']->connection('invoice_create_log_observer');
                $identity = (array) $reader->selectOne('SELECT DATABASE() AS db, VERSION() AS version, @@server_uuid AS uuid, CURRENT_USER() AS account, CONNECTION_ID() AS connection_id');
                Tv3AcceptanceMySqlEnvironment::assertIdentity($identity, $this->manifest);
                $writer = $this->db->selectOne('SELECT CONNECTION_ID() AS id');
                if ((int) $identity['connection_id'] === (int) $writer->id || $reader->getPdo()->inTransaction()) {
                    throw new RuntimeException('Log observer must be independent and autocommit.');
                }
                $invoiceIds = $reader->table('invoices')->where('booking_id', $this->bookingId)->pluck('id');
                file_put_contents($this->directory.'/'.$this->label.'-logs.jsonl', json_encode([
                    'level' => $level, 'message' => (string) $message, 'context' => $context,
                    'transaction_level' => $this->db->transactionLevel(), 'pdo_transaction' => $this->db->getPdo()->inTransaction(),
                    'observer_invoices' => count($invoiceIds),
                    'observer_items' => $reader->table('invoice_items')->whereIn('invoice_id', $invoiceIds)->count(),
                ], JSON_THROW_ON_ERROR)."\n", FILE_APPEND | LOCK_EX);
                $this->app['db']->purge('invoice_create_log_observer');
            }
        };
        $app->instance(InvoiceService::class, $app->make(InvoiceService::class, ['logger' => $logger]));
        InvoiceItem::query();
        $dispatcher = InvoiceItem::getEventDispatcher();
        InvoiceItem::setEventDispatcher(clone $dispatcher);
        if ($input['hold']) {
            InvoiceItem::created(static function (InvoiceItem $item) use ($input, $emit, $db, $dir): void {
                $invoice = $db->table('invoices')->where('id', $item->invoice_id)->first();
                if ((int) $invoice->booking_id !== $input['booking_id']) return;
                $count = $db->table('invoice_items')->where('invoice_id', $item->invoice_id)->count();
                if ($count !== 3) return;
                if (!$db->getPdo()->inTransaction()) throw new RuntimeException('Holder must retain an uncommitted transaction.');
                $emit('written', ['invoice_id' => (int) $item->invoice_id, 'items' => $count,
                    'total' => $invoice->total_amount, 'transaction_level' => $db->transactionLevel()]);
                $deadline = microtime(true) + 18;
                while (!is_file($dir.'/release.json')) {
                    if (microtime(true) > $deadline) throw new RuntimeException('Holder barrier deadline exceeded.');
                    usleep(10000);
                }
                if ($input['mode'] === 'rollback') throw new RuntimeException('Injected holder rollback after all item SQL.');
            });
        }
        $kernel = $app->make(Kernel::class);
        try {
            $app['auth']->guard('web')->setUser(User::findOrFail($input['host_id']));
            $request = Request::create('/api/host/bookings/'.$input['booking_id'].'/invoice', 'POST', [], [], [], [
                'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            ], '{}');
            $response = $kernel->handle($request);
            $emit('result', ['pid' => getmypid(), 'connection_id' => $identity['connection_id'],
                'status' => $response->getStatusCode(), 'body' => json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR),
                'inserts' => $inserts, 'locking_attempts' => $lockingAttempts, 'reported' => $reported,
                'transaction_level' => $db->transactionLevel(), 'pdo_transaction' => $db->getPdo()->inTransaction()]);
            $kernel->terminate($request, $response);
        } finally {
            InvoiceItem::setEventDispatcher($dispatcher);
            while ($db->transactionLevel() > 0) $db->rollBack();
            $db->disconnect();
        }
    }
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    require dirname(__DIR__, 2).'/vendor/autoload.php';
    try {
        InvoiceCreateRaceWorker::run();
    } catch (Throwable $exception) {
        fwrite(STDERR, 'Invoice create worker infrastructure failure: '.$exception::class."\n");
        if ($exception::class === RuntimeException::class) fwrite(STDERR, $exception->getMessage()."\n");
        exit(2);
    }
}
