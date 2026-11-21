<?php

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Tests\Support\InvoiceReadBrowserEnvironment;
use Tests\Support\ReviewReadBrowserFixtures;

if (PHP_SAPI === 'cli-server' && !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) { http_response_code(403); exit; }
require dirname(__DIR__, 2).'/vendor/autoload.php';
$backend = dirname(__DIR__, 2);
$manifest = InvoiceReadBrowserEnvironment::beforeBoot($backend);
ReviewReadBrowserFixtures::assertScenario($manifest, getenv('STAYHUB_TV3_AC_REVIEW_BROWSER_OPT_IN'));
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
try {
if ($mode === 'prepare') {
    $db = $app['db']->connection();
    if ($db->getSchemaBuilder()->getTables() !== []) throw new RuntimeException('Refuse to initialize a nonempty schema.');
    if ($app->make(ConsoleKernel::class)->call('migrate', ['--no-interaction' => true, '--force' => true]) !== 0) throw new RuntimeException('Owned migration failed.');
    $fixture = ReviewReadBrowserFixtures::create($app, $manifest, (string) getenv('STAYHUB_TV3_AC_BROWSER_PASSWORD'));
    echo json_encode(['identity' => $identity, 'fixture' => $fixture, 'csrf_enforced' => !$app->runningUnitTests()], JSON_THROW_ON_ERROR);
    exit;
}
if ($mode === 'audit') {
    echo json_encode(ReviewReadBrowserFixtures::audit($app, $manifest), JSON_THROW_ON_ERROR);
    exit;
}
if ($mode === 'verify-audit') {
    $input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
    ReviewReadBrowserFixtures::verifyAudit($input['before'], $input['after']);
    echo json_encode(['matched' => true], JSON_THROW_ON_ERROR);
    exit;
}
if ($mode === 'verify') {
    $input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
    // Read immutable literal fixture JSON directly: PowerShell must not normalize ISO timestamps.
    $fixture = json_decode(ltrim(file_get_contents(dirname(getenv('STAYHUB_TV3_AC_MANIFEST')).'/fixture.json'), "\xEF\xBB\xBF"), true, flags: JSON_THROW_ON_ERROR)['fixture'];
    $expected = $input['expected'];
    $expected['data'] = array_map(static fn ($label) => $fixture['rows'][$label], $expected['labels']);
    $expected['summary'] = $fixture['summaries'][$expected['scope']];
    ReviewReadBrowserFixtures::verify($input['response'], $expected);
    echo json_encode(['matched' => true], JSON_THROW_ON_ERROR);
    exit;
}
if ($mode !== 'serve') throw new RuntimeException('Unknown browser bootstrap mode.');
} catch (\Throwable $error) {
    if ($mode === 'serve') throw $error;
    fwrite(STDERR, $error->getMessage().PHP_EOL);
    exit(2);
}
$request = Request::capture();
if (!ReviewReadBrowserFixtures::allowsRequest($request->method(), $request->getPathInfo())) {
    (new \Symfony\Component\HttpFoundation\JsonResponse(
        ['message' => 'QA read-only harness refuses business mutations.', 'code' => 'QA_READ_ONLY'], 403,
        ['Access-Control-Allow-Origin' => $manifest['frontend_url'], 'Access-Control-Allow-Credentials' => 'true', 'Vary' => 'Origin']
    ))->send();
    exit;
}
$app->handleRequest($request);
