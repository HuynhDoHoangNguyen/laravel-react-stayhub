<?php

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Tests\Support\Uc45BrowserEnvironment;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$backend = dirname(__DIR__, 2);
$manifest = Uc45BrowserEnvironment::beforeBoot($backend);
$app = require $backend.'/bootstrap/app.php';
$app->make(HttpKernel::class)->bootstrap();
// QA process overrides only. Production config files and middleware remain unchanged.
$app['config']->set([
    'app.debug' => false, 'session.connection' => 'mysql', 'session.table' => 'sessions',
    'session.cookie' => 'uc45_'.$manifest['run_id'].'_session', 'session.domain' => null,
    'session.secure' => false, 'session.same_site' => 'lax',
    'cors.allowed_origins' => [$manifest['frontend_url']], 'cors.supports_credentials' => true,
    'sanctum.stateful' => [substr($manifest['frontend_url'], 7)],
]);
$identity = Uc45BrowserEnvironment::assertApplication($app, $manifest);
$mode = PHP_SAPI === 'cli-server' ? 'serve' : ($argv[1] ?? '');

if ($mode === 'prepare') {
    $db = $app['db']->connection();
    if ($db->getSchemaBuilder()->getTables() !== []) throw new RuntimeException('Refuse to initialize a nonempty schema.');
    $console = $app->make(ConsoleKernel::class);
    if ($console->call('migrate', ['--no-interaction' => true, '--force' => true]) !== 0) throw new RuntimeException('Owned migration failed.');
    $password = getenv('STAYHUB_UC45_BROWSER_PASSWORD');
    if (!is_string($password) || strlen($password) < 24) throw new RuntimeException('Ephemeral browser fixture password is required.');
    $fixture = $db->transaction(function () use ($db, $password): array {
        $stamp = ['created_at' => '2026-11-18 02:00:00', 'updated_at' => '2026-11-18 02:00:00'];
        $actors = []; $hosts = []; $properties = []; $rooms = []; $services = []; $invoices = [];
        foreach (['admin' => ['ADMIN', 'ACTIVE'], 'customer' => ['CUSTOMER', 'ACTIVE'],
            'blocked' => ['ADMIN', 'BLOCKED'], 'inactive' => ['ADMIN', 'INACTIVE']] as $key => [$role, $status]) {
            $actors[$key] = $db->table('users')->insertGetId(['name' => 'UC45 Browser '.$key,
                'email' => $key.'@uc45.example.test', 'password' => password_hash($password, PASSWORD_BCRYPT),
                'phone' => '0900000000', 'role' => $role, 'status' => $status, ...$stamp]);
        }
        for ($h = 0; $h < 12; ++$h) {
            $host = $db->table('users')->insertGetId(['name' => sprintf('Host QA %02d', $h), 'email' => 'host'.$h.'@uc45.example.test',
                'password' => password_hash($password, PASSWORD_BCRYPT), 'phone' => '0900000000', 'role' => 'HOST', 'status' => 'ACTIVE', ...$stamp]);
            $hosts[] = $host;
            for ($p = 0; $p < 2; ++$p) {
                $property = $db->table('properties')->insertGetId(['host_id' => $host, 'name' => sprintf('Cơ sở QA %02d-%d', $h, $p),
                    'type' => 'HOTEL', 'address' => 'Địa chỉ fixture', 'status' => $p ? 'INACTIVE' : 'ACTIVE', ...$stamp]);
                $properties[] = $property;
                $type = $db->table('room_types')->insertGetId(['property_id' => $property, 'name' => 'Loại QA', ...$stamp]);
                $rooms[] = $db->table('rooms')->insertGetId(['property_id' => $property, 'room_type_id' => $type, 'room_number' => '001',
                    'name' => 'Phòng hiện tại', 'capacity' => 2, 'price_per_night' => '999.99', 'status' => 'ACTIVE', ...$stamp]);
                $services[] = $db->table('services')->insertGetId(['property_id' => $property, 'name' => 'Dịch vụ hiện tại', 'unit' => 'Lần', 'price' => '999.99', 'status' => true, ...$stamp]);
            }
        }
        for ($i = 0; $i < 60; ++$i) {
            $slot = intdiv($i, 5) * 2 + $i % 2;
            $booking = $db->table('bookings')->insertGetId(['booking_code' => sprintf('QA-B-%03d', $i), 'customer_id' => $actors['customer'],
                'room_id' => $rooms[$slot], 'check_in_date' => '2026-11-10', 'check_out_date' => '2026-11-12', 'guest_count' => 2,
                'number_of_nights' => 2, 'price_per_night' => '100.25', 'room_total' => '200.50', 'status' => 'COMPLETED', ...$stamp]);
            $db->table('booking_services')->insert(['booking_id' => $booking, 'service_id' => $services[$slot], 'service_name' => 'Dịch vụ snapshot',
                'unit' => 'Lần', 'quantity' => '1.00', 'unit_price' => '12.75', 'amount' => '12.75', ...$stamp]);
            $status = ['UNPAID', 'PAID', 'REFUNDED'][$i % 3];
            $id = $db->table('invoices')->insertGetId(['invoice_code' => sprintf('QA-I-%03d', $i), 'booking_id' => $booking,
                'customer_name' => 'Khách snapshot %_ '.$i, 'customer_email' => 'snapshot@uc45.example.test', 'customer_phone' => '0900000000',
                'property_name' => 'Cơ sở snapshot '.$slot, 'room_name' => 'Phòng snapshot', 'subtotal' => '213.25',
                'discount_amount' => '10.00', 'extra_fee' => '5.25', 'total_amount' => '208.50', 'payment_status' => $status,
                'paid_at' => $status === 'PAID' ? '2026-11-18 03:00:00' : null, ...$stamp]);
            $invoices[] = $id;
            foreach ([['ROOM', 'Phòng snapshot', '2.00', '100.25', '200.50'], ['SERVICE', 'Dịch vụ snapshot', '1.00', '12.75', '12.75']] as [$kind, $name, $qty, $price, $amount])
                $db->table('invoice_items')->insert(['invoice_id' => $id, 'item_type' => $kind, 'item_name' => $name,
                    'quantity' => $qty, 'unit_price' => $price, 'amount' => $amount, ...$stamp]);
        }
        return compact('actors', 'hosts', 'properties', 'invoices');
    });
    echo json_encode(['identity' => $identity, 'fixture' => $fixture, 'csrf_enforced' => !$app->runningUnitTests()], JSON_THROW_ON_ERROR);
    exit;
}

if ($mode !== 'serve') throw new RuntimeException('Unknown browser bootstrap mode.');
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) { http_response_code(403); exit; }
// Only the normal Laravel HTTP pipeline is exposed; no QA login or mutation backdoors.
$app->handleRequest(Request::capture());
