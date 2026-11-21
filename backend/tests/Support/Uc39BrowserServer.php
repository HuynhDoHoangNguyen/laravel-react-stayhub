<?php

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Tests\Support\Uc39BrowserEnvironment;
use Tests\Support\Uc39BrowserFixtures;

if (PHP_SAPI === 'cli-server' && !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) { http_response_code(403); exit; }
require dirname(__DIR__, 2).'/vendor/autoload.php';
$backend = dirname(__DIR__, 2);
$manifest = Uc39BrowserEnvironment::beforeBoot($backend);
$app = require $backend.'/bootstrap/app.php';
$app->make(HttpKernel::class)->bootstrap();
// Process-local QA overrides only; normal production HTTP routing/middleware is unchanged.
$app['config']->set([
    'app.debug' => false, 'session.connection' => 'mysql', 'session.table' => 'sessions',
    'session.cookie' => 'uc39_'.$manifest['run_id'].'_session', 'session.domain' => null,
    'session.secure' => false, 'session.same_site' => 'lax',
    'cors.allowed_origins' => [$manifest['frontend_url']], 'cors.allowed_origins_patterns' => [], 'cors.supports_credentials' => true,
    'sanctum.stateful' => [substr($manifest['frontend_url'], 7)],
]);
$identity = Uc39BrowserEnvironment::assertApplication($app, $manifest);
$app['db']->connection()->statement("SET time_zone = '+00:00'");
$mode = PHP_SAPI === 'cli-server' ? 'serve' : ($argv[1] ?? '');
if ($mode === 'prepare') {
    $db = $app['db']->connection();
    if ($db->getSchemaBuilder()->getTables() !== []) throw new RuntimeException('Refuse to initialize a nonempty schema.');
    if ($app->make(ConsoleKernel::class)->call('migrate', ['--no-interaction' => true, '--force' => true]) !== 0) throw new RuntimeException('Owned migration failed.');
    $fixture = Uc39BrowserFixtures::create($app, $manifest, (string) getenv('STAYHUB_UC39_BROWSER_PASSWORD'));
    echo json_encode(['identity' => $identity, 'fixture' => $fixture, 'csrf_enforced' => !$app->runningUnitTests()], JSON_THROW_ON_ERROR);
    exit;
}
if ($mode === 'audit') {
    echo json_encode(Uc39BrowserFixtures::audit($app, $manifest), JSON_THROW_ON_ERROR);
    exit;
}
if ($mode === 'verify') {
    $input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
    // Keep ISO string precision; PowerShell's JSON date conversion can drop zero microseconds.
    $input['spec'] = json_decode(file_get_contents(dirname(getenv('STAYHUB_UC39_MANIFEST')).'/fixture-native.json'), true, flags: JSON_THROW_ON_ERROR)['fixture']['spec'];
    $data = $input['response']['data'];
    $expected = Uc39BrowserFixtures::oracle($input['spec'], $data['generated_at'], $input['query'] ?? [], $input['actor']);
    if (Uc39BrowserFixtures::canonical($expected) !== Uc39BrowserFixtures::canonical($data)) { file_put_contents(dirname(getenv('STAYHUB_UC39_MANIFEST')).'/verify-failure.json', json_encode(['expected' => $expected, 'actual' => $data], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)); throw new RuntimeException('HTTP dashboard differs from the independent fixture oracle.'); }
    echo json_encode(['matched' => true, 'expected' => $expected], JSON_THROW_ON_ERROR);
    exit;
}
if ($mode !== 'serve') throw new RuntimeException('Unknown browser bootstrap mode.');
$app->handleRequest(Request::capture());
