<?php

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Tests\Support\InvoiceCreateBrowserFixtures;
use Tests\Support\InvoiceReadBrowserEnvironment;

if (PHP_SAPI === 'cli-server' && !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) { http_response_code(403); exit; }
require dirname(__DIR__, 2).'/vendor/autoload.php';
$backend = dirname(__DIR__, 2);
$manifest = InvoiceReadBrowserEnvironment::beforeBoot($backend);
if (($manifest['scenario'] ?? null) !== 'InvoiceCreate' || getenv('STAYHUB_TV3_AC_CREATE_BROWSER_OPT_IN') !== '1') {
    throw new RuntimeException('Create browser requires its own scenario and explicit opt-in.');
}
$directory = realpath(dirname(getenv('STAYHUB_TV3_AC_MANIFEST')));
$statePath = $directory.'/create-state.json';
$app = require $backend.'/bootstrap/app.php';
$kernel = $app->make(HttpKernel::class);
$kernel->bootstrap();
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
    if (is_file($statePath) || $app['db']->getSchemaBuilder()->getTables() !== []) throw new RuntimeException('Refuse nonempty create browser target.');
    if ($app->make(ConsoleKernel::class)->call('migrate', ['--no-interaction' => true, '--force' => true]) !== 0) throw new RuntimeException('Owned migration failed.');
    $fixture = InvoiceCreateBrowserFixtures::create($app, $manifest, (string) getenv('STAYHUB_TV3_AC_BROWSER_PASSWORD'));
    $state = ['fixture' => $fixture, 'before' => InvoiceCreateBrowserFixtures::snapshot()];
    file_put_contents($statePath, json_encode($state, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo json_encode(['identity' => $identity, 'fixture' => $fixture, 'csrf_enforced' => !$app->runningUnitTests()], JSON_THROW_ON_ERROR);
    exit;
}
if (!is_file($statePath)) throw new RuntimeException('Missing runner-owned create state.');
$state = json_decode(file_get_contents($statePath), true, 512, JSON_THROW_ON_ERROR);
if (in_array($mode, ['audit-probes', 'audit-browser', 'verify', 'seal'], true)) {
    $snapshot = InvoiceCreateBrowserFixtures::snapshot();
    $sealed = isset($state['probe_checkpoint']);
    if (($mode === 'audit-browser' && !$sealed) || (in_array($mode, ['audit-probes', 'seal'], true) && $sealed)) {
        throw new RuntimeException('Invalid probe/browser checkpoint phase.');
    }
    $allowed = [];
    foreach ($state['fixture']['bookings'] as $booking) {
        if ($booking['mutable'] && $booking['family'] === ($sealed ? 'UI' : 'P')) $allowed[] = $booking['id'];
    }
    $delta = InvoiceCreateBrowserFixtures::verifyDelta($state['probe_checkpoint'] ?? $state['before'], $snapshot, $state['fixture']['bookings'], $allowed);
    if ($mode === 'seal') {
        if ($snapshot['session_users'] !== []) throw new RuntimeException('Logout probes before sealing browser checkpoint.');
        $state['probe_checkpoint'] = $snapshot;
        file_put_contents($statePath.'.tmp', json_encode($state, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        if (!rename($statePath.'.tmp', $statePath)) throw new RuntimeException('Cannot seal browser checkpoint.');
        echo json_encode(['sealed' => true, 'delta' => $delta], JSON_THROW_ON_ERROR);
        exit;
    }
    if ($mode === 'verify') {
        $input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
        InvoiceCreateBrowserFixtures::verifyResponse($input['response'], ['fixture' => $state['fixture'], 'snapshot' => $snapshot], $input['booking_id']);
        echo json_encode(['matched' => true], JSON_THROW_ON_ERROR);
    } else {
        echo json_encode(['delta' => $delta, 'session_users' => $snapshot['session_users'], 'source' => $snapshot['source']], JSON_THROW_ON_ERROR);
    }
    exit;
}
if ($mode !== 'serve') throw new RuntimeException('Unknown create browser command.');
$request = Request::capture();
$path = '/'.ltrim($request->path(), '/');
$bookingSpec = null;
if (!in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
    $allowed = in_array($path, ['/login', '/logout'], true) && $request->method() === 'POST';
    if ($request->method() === 'POST' && preg_match('~\A/api/host/bookings/([1-9][0-9]*)/invoice\z~', $path, $match)) {
        foreach ($state['fixture']['bookings'] as $spec) {
            if ($spec['id'] === (int) $match[1]) {
                $bookingSpec = $spec;
                $allowed = $spec['family'] === (isset($state['probe_checkpoint']) ? 'UI' : 'P');
                break;
            }
        }
        if ((int) $match[1] === $state['fixture']['missing_id']) $allowed = true;
    }
    if (!$allowed) { http_response_code(403); header('Content-Type: application/json'); echo '{"message":"QA harness refuses mutations outside its fixture routes."}'; exit; }
}
$response = $kernel->handle($request);
// Deterministic QA-only ambiguous outcome: production has already committed, response delivery fails.
if ($bookingSpec && $bookingSpec['kind'] === 'UNKNOWN' && $response->getStatusCode() === 201) {
    $snapshot = InvoiceCreateBrowserFixtures::snapshot();
    $family = isset($state['probe_checkpoint']) ? 'UI' : 'P';
    InvoiceCreateBrowserFixtures::verifyDelta($state['probe_checkpoint'] ?? $state['before'], $snapshot, $state['fixture']['bookings'],
        array_values(array_map(static fn ($spec) => $spec['id'], array_filter($state['fixture']['bookings'], static fn ($spec) => $spec['mutable'] && $spec['family'] === $family))));
    file_put_contents($directory.'/post-commit-'.$bookingSpec['id'].'.json', json_encode([
        'booking_id' => $bookingSpec['id'], 'production_status' => 201, 'delivered_status' => 500,
        'transaction_level' => $app['db']->connection()->transactionLevel(), 'synthetic_response_fault' => true,
    ], JSON_THROW_ON_ERROR));
    // Preserve kernel CORS/session headers so the browser observes the attributed HTTP error.
    if (!$response instanceof \Illuminate\Http\JsonResponse) throw new RuntimeException('Expected production JSON creation response.');
    $response->setData(['message' => 'Đã xảy ra lỗi hệ thống. Vui lòng thử lại sau.', 'code' => 'INTERNAL_SERVER_ERROR']);
    $response->setStatusCode(500);
    $response->headers->set('X-TV3-QA-Fault', 'after-commit-response');
    $response->headers->remove('Content-Length');
}
$response->send();
$kernel->terminate($request, $response);
