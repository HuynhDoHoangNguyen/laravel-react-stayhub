<?php

namespace Tests\Support;

use App\Mappers\Invoice\InvoiceMapper;
use App\Models\Invoice;
use App\Models\User;
use App\Repositories\Invoice\InvoiceRepository;
use App\Services\Invoice\InvoicePaymentService;
use App\Validators\Invoice\InvoicePaymentValidator;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Throwable;

/** CLI-only fixture worker. Credentials are inherited through process environment, never argv. */
final class InvoicePaymentRaceWorker
{
    public static function run(): void
    {
        $input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
        $keys = ['APP_ENV', 'DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_URL', 'DB_SOCKET', 'DB_DATABASE',
            'DB_USERNAME', 'CACHE_STORE', 'SESSION_DRIVER', 'STAYHUB_UC36_TEST_DATABASE', 'STAYHUB_UC36_TEST_SERVER_UUID'];
        $env = [];
        foreach ($keys as $key) $env[$key] = getenv($key);
        // Windows proc_open can omit empty environment entries. Transport the two explicitly
        // empty options separately; missing protocol fields still fail the unchanged strict guard.
        foreach (['DB_URL', 'DB_SOCKET'] as $key) {
            if ($env[$key] === false && array_key_exists($key, $input['empty_environment'] ?? [])) {
                $env[$key] = $input['empty_environment'][$key];
            }
        }
        $database = InvoicePaymentMySqlTestCase::assertTestEnvironment($env);
        foreach (['DB_URL', 'DB_SOCKET'] as $key) {
            $_ENV[$key] = $_SERVER[$key] = $env[$key];
        }
        $root = dirname(__DIR__, 2);
        if (is_file($root.'/bootstrap/cache/config.php')) throw new RuntimeException('Worker refuses cached configuration.');
        $dir = realpath($input['directory'] ?? '');
        $artifact = realpath(getenv('STAYHUB_UC36_RACE_ARTIFACT_DIR') ?: '');
        if (!$artifact || !$dir || dirname($dir) !== $artifact || !preg_match('/\Arace-[a-f0-9]{32}\z/', basename($dir))
            || !in_array($input['label'] ?? '', ['A', 'B'], true)
            || !in_array($input['mode'] ?? '', ['commit', 'rollback', 'timeout'], true)
            || str_starts_with(strtolower($artifact.DIRECTORY_SEPARATOR), strtolower(realpath(dirname($root)).DIRECTORY_SEPARATOR))) {
            throw new RuntimeException('Worker requires an owned artifact directory and valid protocol.');
        }
        $label = $input['label'];
        $emit = static function (string $phase, array $data = []) use ($dir, $label): void {
            file_put_contents($dir.'/'.$label.'-'.$phase.'.json', json_encode($data, JSON_THROW_ON_ERROR), LOCK_EX);
        };
        $app = require $root.'/bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $config = $app['config'];
        $effective = $config->get('database.connections.mysql');
        if ($config->get('database.default') !== 'mysql' || $config->get('app.env') !== 'testing'
            || ($effective['host'] ?? '') !== '127.0.0.1' || (string) ($effective['port'] ?? '') !== '3307'
            || ($effective['database'] ?? '') !== $database || ($effective['username'] ?? '') !== $env['DB_USERNAME']
            || !empty($effective['url']) || !empty($effective['unix_socket'])
            || $config->get('cache.default') !== 'array' || $config->get('session.driver') !== 'array'
            || $config->get('app.debug') !== false) {
            throw new RuntimeException('Worker effective configuration differs from guarded target.');
        }
        $db = $app['db']->connection();
        $identity = $db->selectOne('SELECT DATABASE() AS db, VERSION() AS version, @@server_uuid AS uuid, CONNECTION_ID() AS connection_id');
        if ($identity->db !== $database || $identity->uuid !== $env['STAYHUB_UC36_TEST_SERVER_UUID']
            || !str_starts_with($identity->version, '8.4.')) throw new RuntimeException('Worker selected unexpected instance.');
        $emit('ready', ['pid' => getmypid(), ...get_object_vars($identity)]);
        $db->statement('SET SESSION innodb_lock_wait_timeout = '.($input['mode'] === 'timeout' ? '2' : '15'));
        CarbonImmutable::setTestNow(CarbonImmutable::parse($input['clock']));
        \Carbon\Carbon::setTestNow(CarbonImmutable::parse($input['clock']));
        $updates = 0;
        $lockingAttempts = 0;
        $db->beforeExecuting(static function (string $sql) use ($emit, &$lockingAttempts): void {
            if (str_contains(strtolower($sql), 'from `invoices`') && str_contains(strtolower($sql), 'for update')) {
                $lockingAttempts++;
                $emit('locking');
            }
        });
        $db->listen(static function (QueryExecuted $query) use (&$updates): void {
            if (str_starts_with(strtolower($query->sql), 'update `invoices`') && str_contains($query->sql, 'payment_status')) $updates++;
        });
        $reported = [];
        $app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class)->reportable(
            static function (Throwable $exception) use (&$reported): void {
                $reported[] = ['class' => $exception::class,
                    'sqlstate' => $exception instanceof QueryException ? ($exception->errorInfo[0] ?? null) : null,
                    'driver_code' => $exception instanceof QueryException ? ($exception->errorInfo[1] ?? null) : null];
            }
        )->stop();
        $logger = new class($dir, $label, $db) extends AbstractLogger {
            public function __construct(private string $directory, private string $label, private \Illuminate\Database\Connection $db) {}

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                file_put_contents($this->directory.'/'.$this->label.'-logs.jsonl', json_encode([
                    'level' => $level, 'message' => (string) $message, 'context' => $context,
                    'transaction_level' => $this->db->transactionLevel(), 'pdo_transaction' => $this->db->getPdo()->inTransaction(),
                ], JSON_THROW_ON_ERROR)."\n", FILE_APPEND | LOCK_EX);
            }
        };
        $app->instance(InvoicePaymentService::class, new InvoicePaymentService(
            $app->make(InvoiceRepository::class), $app->make(InvoicePaymentValidator::class),
            $app->make(InvoiceMapper::class), $logger,
        ));
        // Boot model before registering a listener; restore dispatcher even on failure.
        Invoice::query();
        $dispatcher = Invoice::getEventDispatcher();
        Invoice::setEventDispatcher(clone $dispatcher);
        if ($input['hold']) {
            Invoice::updated(static function (Invoice $invoice) use ($input, $emit, $db, $dir): void {
                if ((int) $invoice->id !== $input['invoice_id']) return;
                $state = $db->table('invoices')->where('id', $invoice->id)->value('payment_status');
                if ($state !== 'PAID' || !$db->getPdo()->inTransaction()) throw new RuntimeException('Holder did not reach uncommitted PAID.');
                $emit('written', ['state' => $state, 'transaction_level' => $db->transactionLevel()]);
                $deadline = microtime(true) + 12;
                while (!is_file($dir.'/release.json')) {
                    if (microtime(true) > $deadline) throw new RuntimeException('Holder barrier deadline exceeded.');
                    usleep(10000);
                }
                if ($input['mode'] === 'rollback') throw new RuntimeException('Injected holder rollback after SQL.');
            });
        }
        $kernel = $app->make(Kernel::class);
        try {
            $actor = User::findOrFail($input['host_id']);
            $app['auth']->guard('web')->setUser($actor);
            $request = Request::create('/api/host/invoices/'.$input['invoice_id'].'/payment-status', 'PATCH', [], [], [], [
                'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            ], '{"payment_status":"PAID"}');
            $response = $kernel->handle($request);
            $emit('result', ['pid' => getmypid(), 'connection_id' => $identity->connection_id,
                'status' => $response->getStatusCode(), 'body' => json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR),
                'successful_update_sql' => $updates, 'locking_attempts' => $lockingAttempts,
                'reported' => $reported, 'transaction_level' => $db->transactionLevel()]);
            $kernel->terminate($request, $response);
        } finally {
            Invoice::setEventDispatcher($dispatcher);
            while ($db->transactionLevel() > 0) $db->rollBack();
            $db->disconnect();
            CarbonImmutable::setTestNow();
            \Carbon\Carbon::setTestNow();
        }
    }
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    require dirname(__DIR__, 2).'/vendor/autoload.php';
    try {
        InvoicePaymentRaceWorker::run();
    } catch (Throwable $exception) {
        // No SQL, bindings, credentials or request data in worker infrastructure errors.
        fwrite(STDERR, 'Race worker infrastructure failure: '.$exception::class."\n");
        if ($exception::class === RuntimeException::class) fwrite(STDERR, $exception->getMessage()."\n");
        exit(2);
    }
}
