<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Tests\Support\Uc44MySqlEnvironment;

$backend = dirname(__DIR__, 2);
require $backend.'/vendor/autoload.php';

$manifest = Uc44MySqlEnvironment::beforeBoot($backend);
$origin = static fn (mixed $value): bool => is_string($value)
    && preg_match('/\Ahttp:\/\/127\.0\.0\.1:[1-9][0-9]{0,4}\z/', $value) === 1
    && (int) parse_url($value, PHP_URL_PORT) <= 65535;
if (($manifest['suite'] ?? null) !== 'Browser' || !$origin($manifest['frontend_url'] ?? null)
    || !$origin($manifest['backend_url'] ?? null) || $manifest['frontend_url'] === $manifest['backend_url']) {
    throw new RuntimeException('UC44 browser requires its own loopback manifest.');
}

$http = PHP_SAPI === 'cli-server';
if ($http && (($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1'
    || 'http://'.($_SERVER['HTTP_HOST'] ?? '') !== $manifest['backend_url'])) {
    http_response_code(403);
    exit;
}

$app = require $backend.'/bootstrap/app.php';
$app->make(HttpKernel::class)->bootstrap();
Uc44MySqlEnvironment::assertApplication($app, $manifest);
if ($http && $app->runningInConsole()) {
    throw new RuntimeException('UC44 refuses HTTP with console-mode CSRF behavior.');
}

$app['config']->set([
    'app.debug' => false,
    'app.url' => $manifest['backend_url'],
    'session.driver' => 'database',
    'session.connection' => 'mysql',
    'session.table' => 'sessions',
    'session.cookie' => 'uc44_'.$manifest['run_id'].'_session',
    'session.domain' => null,
    'session.path' => '/',
    'session.secure' => false,
    'session.http_only' => true,
    'session.same_site' => 'lax',
    'cors.allowed_origins' => [$manifest['frontend_url']],
    'cors.allowed_origins_patterns' => [],
    'cors.supports_credentials' => true,
    'sanctum.stateful' => [substr($manifest['frontend_url'], strlen('http://'))],
]);

if ($http) {
    $app->handleRequest(Request::capture());
    exit;
}

$db = $app['db']->connection();
$mode = $argv[1] ?? '';
if ($mode === 'prepare') {
    $password = getenv('STAYHUB_UC44_BROWSER_PASSWORD');
    if (!is_string($password) || strlen($password) < 32 || $db->getSchemaBuilder()->getTables() !== []) {
        throw new RuntimeException('UC44 requires an empty owned schema and an ephemeral fixture password.');
    }
    if ($app->make(ConsoleKernel::class)->call('migrate', ['--force' => true, '--no-interaction' => true]) !== 0) {
        throw new RuntimeException('UC44 browser migration failed.');
    }

    $db->transaction(function () use ($db, $app, $password): void {
        $hash = $app['hash']->make($password);
        foreach ([
            [1, 'admin', 'ADMIN', 'ACTIVE', 'Quản trị QA'],
            [21, 'host', 'HOST', 'ACTIVE', 'Host QA Alfa'],
            [22, 'host-b', 'HOST', 'ACTIVE', 'Host QA Bravo'],
            [31, 'customer', 'CUSTOMER', 'ACTIVE', 'Khách QA Một'],
            [32, 'customer-b', 'CUSTOMER', 'ACTIVE', 'Khách QA Hai'],
            [41, 'blocked', 'ADMIN', 'BLOCKED', 'Quản trị bị khóa'],
            [42, 'inactive', 'ADMIN', 'INACTIVE', 'Quản trị chưa kích hoạt'],
        ] as [$id, $account, $role, $status, $name]) {
            $db->table('users')->insert(['id' => $id, 'role' => $role, 'status' => $status,
                'name' => $name, 'email' => $account.'@example.test', 'password' => $hash]);
        }

        foreach ([
            [51, 21, 'Cơ sở QA Alfa '.str_repeat('Dài', 23)],
            [52, 22, 'Cơ sở QA Bravo'],
            [53, 21, 'Cơ sở QA Charlie'],
        ] as [$id, $host, $name]) {
            $db->table('properties')->insert(['id' => $id, 'host_id' => $host, 'name' => $name,
                'status' => 'ACTIVE', 'type' => 'HOTEL', 'address' => 'Fixture']);
            $db->table('room_types')->insert(['id' => $id, 'property_id' => $id, 'name' => 'QA']);
            $db->table('rooms')->insert(['id' => $id, 'property_id' => $id, 'room_type_id' => $id,
                'name' => 'Phòng '.$id, 'room_number' => (string) $id, 'capacity' => 2,
                'price_per_night' => '900000.00', 'status' => 'ACTIVE']);
        }

        $statuses = ['PENDING', 'CONFIRMED', 'CHECKED_IN', 'COMPLETED', 'CANCELLED', 'REJECTED'];
        for ($index = 0; $index < 12; $index++) {
            $id = 101 + $index;
            $db->table('bookings')->insert([
                'id' => $id, 'booking_code' => "UI44-$id",
                'customer_id' => $index % 3 === 1 ? 32 : 31, 'room_id' => 51 + $index % 3,
                'status' => $statuses[$index % 6], 'check_in_date' => '2026-11-'.(21 + $index % 3),
                'check_out_date' => '2026-11-25', 'guest_count' => 2, 'number_of_nights' => 4 - $index % 3,
                'price_per_night' => '123456.00', 'room_total' => '777777.00',
                'created_at' => $index === 0 ? null : '2026-11-20 17:30:00',
            ]);
        }
    });
}

if (!in_array($mode, ['audit', 'prepare'], true)) {
    throw new RuntimeException('Unknown UC44 browser bootstrap mode.');
}

$tables = ['users', 'properties', 'room_types', 'rooms', 'bookings', 'booking_services', 'invoices', 'invoice_items'];
$fingerprints = [];
foreach ($tables as $table) {
    $rows = $db->table($table)->orderBy('id')->get();
    $fingerprints[$table] = ['count' => $rows->count(), 'sha256' => hash('sha256', $rows->toJson(JSON_THROW_ON_ERROR))];
}

echo json_encode([
    'database' => $manifest['database'],
    'driver' => $app['config']->get('session.driver'),
    'tables' => $fingerprints,
], JSON_THROW_ON_ERROR);

