<?php

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Tests\Support\ReviewCreateBrowserFixtures;
use Tests\Support\InvoiceReadBrowserEnvironment;

if (PHP_SAPI === 'cli-server' && !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) { http_response_code(403); exit; }
require dirname(__DIR__, 2).'/vendor/autoload.php';
$backend = dirname(__DIR__, 2);
$manifest = InvoiceReadBrowserEnvironment::beforeBoot($backend);
ReviewCreateBrowserFixtures::assertScenario($manifest, getenv('STAYHUB_TV3_AC_REVIEW_CREATE_BROWSER_OPT_IN'));
$directory = realpath(dirname(getenv('STAYHUB_TV3_AC_MANIFEST')));
$statePath = $directory.'/create-state.json';
$journalPath = $directory.'/requests.jsonl';
function requestJournal(string $path): array {
    return is_file($path) ? array_map(static fn ($s) => json_decode($s, true, 512, JSON_THROW_ON_ERROR), file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) : [];
}
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
    $fixture = ReviewCreateBrowserFixtures::create($app, $manifest, (string) getenv('STAYHUB_TV3_AC_BROWSER_PASSWORD'));
    $state = ['fixture' => $fixture, 'before' => ReviewCreateBrowserFixtures::snapshot()];
    file_put_contents($statePath, json_encode($state, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo json_encode(['identity' => $identity, 'fixture' => $fixture, 'csrf_enforced' => !$app->runningUnitTests()], JSON_THROW_ON_ERROR);
    exit;
}
if (!is_file($statePath)) throw new RuntimeException('Missing runner-owned create state.');
$state = json_decode(file_get_contents($statePath), true, 512, JSON_THROW_ON_ERROR);
if (in_array($mode, ['audit-probes', 'audit-browser', 'verify', 'seal'], true)) {
    $snapshot = ReviewCreateBrowserFixtures::snapshot(requestJournal($journalPath));
    $sealed = isset($state['probe_checkpoint']);
    if (($mode === 'audit-browser' && !$sealed) || (in_array($mode, ['audit-probes', 'seal'], true) && $sealed)) {
        throw new RuntimeException('Invalid probe/browser checkpoint phase.');
    }
    $allowed = [];
    foreach ($state['fixture']['bookings'] as $booking) {
        if ($booking['mutable'] && $booking['family'] === ($sealed ? 'UI' : 'P')) $allowed[] = $booking['id'];
    }
    $delta = ReviewCreateBrowserFixtures::verifyDelta($state['probe_checkpoint'] ?? $state['before'], $snapshot, $state['fixture']['bookings'], $allowed);
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
        if (isset($input['list_response'])) {
            $rows = array_values(array_filter($input['list_response']['data'], static fn ($row) => $row['booking_id'] === $input['booking_id']));
            if (count($rows) !== 1) throw new RuntimeException('Expected one Customer list read-back row.');
            $input['response'] = ['data' => $rows[0]];
        }
        ReviewCreateBrowserFixtures::verifyResponse($input['response'], $state['fixture'], $snapshot, $input['booking_id'], $input['literal_payload'] ?? null);
        echo json_encode(['matched' => true], JSON_THROW_ON_ERROR);
    } else {
        echo json_encode(['delta' => $delta, 'session_users' => $snapshot['session_users'], 'source' => $snapshot['source']], JSON_THROW_ON_ERROR);
    }
    exit;
}
if ($mode !== 'serve') throw new RuntimeException('Unknown create browser command.');
$request = Request::capture();
$path = '/'.ltrim($request->path(), '/');
$family = isset($state['probe_checkpoint']) ? 'UI' : 'P';
if (!ReviewCreateBrowserFixtures::allowsRequest($request->method(), $path, $state['fixture']['bookings'], $state['fixture']['missing_id'], $family)) {
    (new \Symfony\Component\HttpFoundation\JsonResponse(['message' => 'QA harness refuses mutations outside its fixture routes.', 'code' => 'QA_SCOPE_DENIED'], 403))->send();
    exit;
}
$bookingSpec = null;
if ($request->method() === 'POST' && preg_match('~\A/api/customer/bookings/([1-9][0-9]*)/review\z~', $path, $match)) {
    foreach ($state['fixture']['bookings'] as $spec) if ($spec['id'] === (int) $match[1]) $bookingSpec = $spec;
}
$response = $kernel->handle($request);
if ($bookingSpec) {
    $success = $response->getStatusCode() === 201;
    $data = json_decode($response->getContent(), true);
    $entry = ['booking_id' => $bookingSpec['id'], 'actor_id' => $request->user()?->id,
        'status' => $response->getStatusCode(), 'review_id' => $success ? ($data['data']['id'] ?? null) : null,
        'rating' => $success ? (int) $request->input('rating') : null,
        'comment' => $success ? trim($request->input('comment')) : null];
    file_put_contents($journalPath, json_encode($entry, JSON_THROW_ON_ERROR)."\n", FILE_APPEND | LOCK_EX);
}
// QA-only response delivery fault, after the unchanged production workflow committed.
if ($bookingSpec && $bookingSpec['kind'] === 'UNKNOWN' && $response->getStatusCode() === 201) {
    if ($app['db']->connection()->transactionLevel() !== 0) throw new RuntimeException('Fault requires durable commit.');
    $snapshot = ReviewCreateBrowserFixtures::snapshot(requestJournal($journalPath));
    ReviewCreateBrowserFixtures::verifyDelta($state['probe_checkpoint'] ?? $state['before'], $snapshot, $state['fixture']['bookings'],
        array_values(array_map(static fn ($spec) => $spec['id'], array_filter($state['fixture']['bookings'], static fn ($spec) => $spec['mutable'] && $spec['family'] === $family))));
    file_put_contents($directory.'/post-commit-'.$bookingSpec['id'].'.json', json_encode(['booking_id' => $bookingSpec['id'],
        'production_status' => 201, 'delivered_status' => 500, 'transaction_level' => 0, 'synthetic_response_fault' => true], JSON_THROW_ON_ERROR));
    if (!$response instanceof \Illuminate\Http\JsonResponse) throw new RuntimeException('Expected production JSON response.');
    $response->setData(['message' => 'Đã xảy ra lỗi hệ thống. Vui lòng thử lại sau.', 'code' => 'INTERNAL_SERVER_ERROR']);
    $response->setStatusCode(500);
    $response->headers->set('X-TV3-QA-Fault', 'after-commit-response');
    $response->headers->remove('Content-Length');
}
$response->send();
$kernel->terminate($request, $response);
