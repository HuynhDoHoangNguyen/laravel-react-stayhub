<?php

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Tests\Support\InvoiceReadBrowserEnvironment;
use Tests\Support\InvoiceReadBrowserFixtures;

if (PHP_SAPI === 'cli-server' && !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) { http_response_code(403); exit; }
require dirname(__DIR__, 2).'/vendor/autoload.php';
$backend = dirname(__DIR__, 2);
$manifest = InvoiceReadBrowserEnvironment::beforeBoot($backend);
$app = require $backend.'/bootstrap/app.php';
$app->make(HttpKernel::class)->bootstrap();
// Process-local QA overrides only; normal production HTTP routing/middleware is unchanged.
$app['config']->set([
    'app.debug' => false, 'session.connection' => 'mysql', 'session.table' => 'sessions',
    'session.cookie' => 'tv3ac_ui_'.$manifest['run_id'].'_session', 'session.domain' => null,
    'session.secure' => false, 'session.same_site' => 'lax',
    'cors.allowed_origins' => [$manifest['frontend_url']], 'cors.allowed_origins_patterns' => [], 'cors.supports_credentials' => true,
    'sanctum.stateful' => [substr($manifest['frontend_url'], 7)],
]);
$identity = InvoiceReadBrowserEnvironment::assertApplication($app, $manifest);
$mode = PHP_SAPI === 'cli-server' ? 'serve' : ($argv[1] ?? '');
if ($mode === 'prepare') {
    $db = $app['db']->connection();
    if ($db->getSchemaBuilder()->getTables() !== []) throw new RuntimeException('Refuse to initialize a nonempty schema.');
    if ($app->make(ConsoleKernel::class)->call('migrate', ['--no-interaction' => true, '--force' => true]) !== 0) throw new RuntimeException('Owned migration failed.');
    $fixture = InvoiceReadBrowserFixtures::create($app, $manifest, (string) getenv('STAYHUB_TV3_AC_BROWSER_PASSWORD'));
    echo json_encode(['identity' => $identity, 'fixture' => $fixture, 'csrf_enforced' => !$app->runningUnitTests()], JSON_THROW_ON_ERROR);
    exit;
}
if ($mode === 'audit') {
    echo json_encode(InvoiceReadBrowserFixtures::audit($app, $manifest), JSON_THROW_ON_ERROR);
    exit;
}
if ($mode === 'verify') {
    $input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
    InvoiceReadBrowserFixtures::verify($input['response'], $input['expected']);
    echo json_encode(['matched' => true], JSON_THROW_ON_ERROR);
    exit;
}
if ($mode !== 'serve') throw new RuntimeException('Unknown browser bootstrap mode.');
$app->handleRequest(Request::capture());
