<?php

namespace Tests\Support;

use App\DTOs\Booking\Requests\StoreBookingRequest;
use App\DTOs\Catalog\Requests\CreateHostRoomMaintenanceRequest;
use App\Exceptions\AppException;
use App\Exceptions\BookingDomainException;
use App\Exceptions\RoomNotAvailableException;
use App\Models\Booking;
use App\Models\User;
use App\Services\Booking\BookingService;
use App\Services\Booking\BookingWorkflowService;
use App\Services\Catalog\HostRoomMaintenanceService;
use Closure;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Connection;
use Illuminate\Database\DeadlockException;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Date;
use PDOException;
use RuntimeException;
use Throwable;

final class HostRoomMaintenanceRaceWorker
{
    public static function target(): string
    {
        $schema = getenv('STAYHUB_UC23_TEST_DATABASE') ?: '';
        if (preg_match('/\Astayhub_uc23_[a-f0-9]{32}_test\z/', $schema) !== 1
            || getenv('DB_DATABASE') !== $schema
            || getenv('APP_ENV') !== 'testing'
            || getenv('ALLOW_EXTERNAL_TEST_DB') !== 'true'
            || getenv('DB_CONNECTION') !== 'mysql'
            || getenv('DB_HOST') !== '127.0.0.1'
            || filter_var(getenv('DB_PORT'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]) === false
            || preg_match('/\Auc23_[a-f0-9]{16}\z/', getenv('DB_USERNAME') ?: '') !== 1
            || !getenv('STAYHUB_UC23_TEST_SERVER_HOSTNAME')
            || getenv('DB_URL') || getenv('DATABASE_URL') || getenv('DB_SOCKET')) {
            throw new RuntimeException('UC23 refuses any target other than its explicitly isolated InnoDB fixture.');
        }

        return $schema;
    }

    public static function connect(Application $app): Connection
    {
        $schema = self::target();
        $config = $app['config'];
        $mysql = $config->get('database.connections.mysql');
        if ($app->configurationIsCached()
            || $config->get('app.env') !== 'testing'
            || $config->get('database.default') !== 'mysql'
            || $config->get('cache.default') !== 'array'
            || $config->get('session.driver') !== 'array'
            || $mysql['host'] !== '127.0.0.1'
            || (string) $mysql['port'] !== getenv('DB_PORT')
            || $mysql['database'] !== $schema
            || $mysql['username'] !== getenv('DB_USERNAME')
            || $mysql['url'] || $mysql['unix_socket'] || isset($mysql['read']) || isset($mysql['write'])) {
            throw new RuntimeException('Laravel does not point to the approved UC23 InnoDB connection.');
        }

        $db = $app['db']->connection();
        $identity = self::identity($db);
        if ($identity->schema_name !== $schema
            || $identity->server_host !== getenv('STAYHUB_UC23_TEST_SERVER_HOSTNAME')
            || strstr($identity->account_name, '@', true) !== getenv('DB_USERNAME')) {
            throw new RuntimeException('Connection did not select the isolated UC23 schema, server and account.');
        }

        return $db;
    }

    public static function identity(Connection $db): object
    {
        return $db->selectOne('SELECT DATABASE() AS schema_name, @@hostname AS server_host, '
            .'CURRENT_USER() AS account_name, CONNECTION_ID() AS connection_id');
    }

    /** Run a waiting request, optionally with a snapshot established before any write lock. */
    public static function run(array $args): void
    {
        self::target();
        [$actorId, $roomId, $start, $end] = $args;
        $action = $args[4] ?? 'maintenance';
        $snapshotMode = $args[5] ?? 'snapshot';
        $bookingId = (int) ($args[6] ?? 0);
        $maintenanceId = (int) ($args[7] ?? 0);
        if (! in_array($action, ['booking', 'maintenance', 'confirm', 'check-in', 'start'], true)
            || ! in_array($snapshotMode, ['fresh', 'snapshot'], true)
            || (in_array($action, ['confirm', 'check-in'], true) && $bookingId < 1)
            || ($action === 'start' && $maintenanceId < 1)) {
            throw new RuntimeException('Invalid UC23 race worker mode.');
        }

        $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
        $db = self::connect($app);
        Date::setTestNow('2026-12-10 12:00:00');
        $db->statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $db->statement('SET SESSION innodb_lock_wait_timeout = 30');
        $actor = User::query()->findOrFail($actorId);
        $rows = $db->table('maintenances')->where('room_id', $roomId);
        $primed = $snapshotMode === 'snapshot';

        try {
            if ($primed) {
                $db->beginTransaction();
            }
            self::emit([
                'phase' => $primed ? 'snapshot' : 'ready',
                'connection_id' => (int) self::identity($db)->connection_id,
            ] + ($primed ? ['count' => $rows->count()] : []));

            $run = self::observe($db, fn () => self::invoke($app, $action, $actor, (int) $roomId, $start, $end, $bookingId, $maintenanceId));
            $outcome = $run['outcome'];
            self::emit(['phase' => 'trace', 'attempts' => $run['attempts'], 'locks' => $run['locks']]);
            if ($primed && !isset($outcome['db_error'])) {
                // A plain read still sees the old snapshot, plus this transaction's own successful insert.
                $outcome['snapshot_count'] = $rows->count();
                $db->commit();
            }

            self::emit(['phase' => 'result'] + $outcome);
        } finally {
            if ($db->transactionLevel() > 0) {
                $db->rollBack(0);
            }
        }
    }

    public static function invoke(
        Application $app,
        string $action,
        User $actor,
        int $roomId,
        string $start,
        string $end,
        int $bookingId = 0,
        int $maintenanceId = 0,
    ): array
    {
        try {
            if ($action === 'confirm' || $action === 'check-in') {
                $pending = Booking::query()->where('room_id', $roomId)->findOrFail($bookingId);
                $service = $app->make(BookingWorkflowService::class);
                $booking = $action === 'confirm' ? $service->confirm($pending, $actor) : $service->checkIn($pending, $actor);

                return ['booking_status' => $booking->getRawOriginal('status')];
            }
            if ($action === 'start') {
                $maintenance = $app->make(HostRoomMaintenanceService::class)->start($actor, $maintenanceId);

                return ['maintenance_status' => $maintenance?->status];
            }
            if ($action === 'booking') {
                $input = new StoreBookingRequest($roomId, $start, $end, guestCount: 2);
                $booking = $app->make(BookingService::class)->create($actor, $input);

                return ['booking_status' => $booking->getRawOriginal('status')];
            }

            $input = new CreateHostRoomMaintenanceRequest($roomId, $start, $end, 'waiting');
            $maintenance = $app->make(HostRoomMaintenanceService::class)->create($actor, $input);
            if ($maintenance === null) {
                throw new RuntimeException('Waiting request could not resolve its fixture room.');
            }

            return ['maintenance' => $maintenance->jsonSerialize()];
        } catch (AppException $error) {
            return ['status' => $error->status, 'code' => $error->errorCode];
        } catch (RoomNotAvailableException|BookingDomainException $error) {
            $response = $error->render();

            return ['status' => $response->getStatusCode(), 'reasons' => $response->getData(true)['errors']['reasons']];
        }
    }

    /** Observe writer attempts and locking queries while running the actual service. */
    public static function observe(Connection $db, Closure $operation, ?Closure $beforeLock = null): array
    {
        $attempts = 0;
        $locks = [];
        $active = true;
        $writerLevel = $db->transactionLevel() + 1;
        $db->getEventDispatcher()->listen(TransactionBeginning::class,
            static function (TransactionBeginning $event) use ($db, $writerLevel, &$attempts, &$active): void {
                if ($active && $event->connection === $db && $db->transactionLevel() === $writerLevel) {
                    $attempts++;
                }
            });
        $db->beforeExecuting(static function (string $sql) use (&$active, &$locks, $beforeLock): void {
            if (!$active || ($table = self::lockTable($sql)) === null) {
                return;
            }
            $locks[] = $table;
            $beforeLock?->__invoke($table);
        });

        try {
            $outcome = $operation();
        } catch (QueryException | DeadlockException $exception) {
            $error = $exception instanceof DeadlockException ? $exception->getPrevious() : $exception;
            $driverCode = $error instanceof PDOException ? (int) ($error->errorInfo[1] ?? 0) : 0;
            if (!in_array($driverCode, [1205, 1213], true)) {
                throw $exception;
            }
            $outcome = ['db_error' => ['sqlstate' => (string) $error->errorInfo[0], 'driver_code' => $driverCode]];
        } finally {
            $active = false;
        }

        return ['outcome' => $outcome, 'attempts' => $attempts, 'locks' => $locks];
    }

    public static function lockTable(string $sql): ?string
    {
        if (preg_match('/\bfrom\s+`(properties|rooms|bookings|maintenances)`.*\bfor\s+update\b/is', $sql, $matches) === 1) {
            return strtolower($matches[1]);
        }

        return null;
    }

    private static function emit(array $message): void
    {
        fwrite(STDOUT, json_encode($message, JSON_THROW_ON_ERROR).PHP_EOL);
        fflush(STDOUT);
    }
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    require dirname(__DIR__, 2).'/vendor/autoload.php';
    try {
        HostRoomMaintenanceRaceWorker::run(array_slice($argv, 1));
    } catch (Throwable $error) {
        fwrite(STDERR, $error::class.': '.$error->getMessage().PHP_EOL);
        exit(2);
    }
}
