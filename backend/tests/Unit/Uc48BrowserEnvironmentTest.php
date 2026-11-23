<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\Uc48BrowserEnvironment;
use Tests\Support\Uc48BrowserFixtures;
use Symfony\Component\Process\Process;

final class Uc48BrowserEnvironmentTest extends TestCase
{
    private static function manifest(): array
    {
        $run = str_repeat('a', 32);
        return ['run_id' => $run, 'database' => 'stayhub_uc48_test_'.$run, 'username' => 'uc48_'.substr($run, 0, 16),
            'development_database' => 'stayhub', 'port' => '3307', 'host' => '127.0.0.1', 'server_uuid' => 'b4736273-e223-11f1-a722-aaf8fde9dbf9', 'version' => '8.4.13',
            'scenario' => 'Populated', 'backend_url' => 'http://localhost:19001', 'frontend_url' => 'http://localhost:19002'];
    }

    private static function env(): array
    {
        $m = self::manifest();
        return ['APP_ENV' => 'local', 'APP_KEY' => 'base64:'.base64_encode(str_repeat('x', 32)), 'DB_CONNECTION' => 'mysql',
            'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3307', 'DB_DATABASE' => $m['database'], 'DB_USERNAME' => $m['username'],
            'DB_PASSWORD' => str_repeat('x', 64), 'DB_URL' => '', 'DB_SOCKET' => '', 'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'database', 'QUEUE_CONNECTION' => 'sync', 'STAYHUB_UC48_OPT_IN' => '1', 'STAYHUB_UC48_BROWSER_OPT_IN' => '1'];
    }

    private static function config(): array
    {
        $m = self::manifest(); $e = self::env();
        return ['url' => $m['backend_url'], 'cors_patterns' => [], 'debug' => false, 'default' => 'mysql', 'app_env' => 'local', 'cache' => 'array', 'session' => 'database', 'queue' => 'sync',
            'mysql' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => '3307', 'database' => $m['database'],
                'username' => $m['username'], 'password' => $e['DB_PASSWORD'], 'url' => null, 'unix_socket' => null],
            'session_connection' => 'mysql', 'session_table' => 'sessions', 'session_cookie' => 'uc48_'.$m['run_id'].'_session',
            'session_domain' => null, 'session_secure' => false, 'session_same_site' => 'lax',
            'cors' => [$m['frontend_url']], 'credentials' => true, 'stateful' => ['localhost:19002']];
    }

    public function test_accepts_only_owned_local_browser_configuration(): void
    {
        Uc48BrowserEnvironment::assertEnvironment(self::env(), self::manifest());
        Uc48BrowserEnvironment::assertConfiguration(self::config(), self::env(), self::manifest());
        $this->addToAssertionCount(2);
    }

    public static function invalidEnvironment(): iterable
    {
        foreach (['STAYHUB_UC48_BROWSER_OPT_IN' => '', 'STAYHUB_UC48_OPT_IN' => '', 'APP_ENV' => 'testing', 'APP_KEY' => '',
            'SESSION_DRIVER' => 'array', 'DB_DATABASE' => 'stayhub', 'DB_HOST' => 'remote', 'DB_PORT' => '3306',
            'DB_USERNAME' => 'root', 'DB_PASSWORD' => 'short', 'DB_URL' => 'mysql://override', 'CACHE_STORE' => 'database'] as $key => $value)
            yield $key => [[$key => $value], []];
        foreach (['database' => 'stayhub', 'username' => 'root', 'run_id' => '../escape', 'backend_url' => 'http://0.0.0.0:19001',
            'frontend_url' => 'https://example.com', 'scenario' => 'unknown', 'port' => '0'] as $key => $value) yield 'manifest-'.$key => [[], [$key => $value]];
        yield 'same-port' => [[], ['frontend_url' => 'http://localhost:19001']];
    }

    #[DataProvider('invalidEnvironment')]
    public function test_refuses_wrong_environment_before_database_access(array $env, array $manifest): void
    {
        $this->expectException(\RuntimeException::class);
        Uc48BrowserEnvironment::assertEnvironment(array_replace(self::env(), $env), array_replace(self::manifest(), $manifest));
    }

    public static function invalidConfig(): iterable
    {
        foreach (['url' => 'http://localhost:9999', 'cors_patterns' => ['.*'], 'debug' => true, 'app_env' => 'testing', 'session' => 'array', 'session_connection' => 'other', 'session_table' => 'other',
            'session_cookie' => 'stayhub_session', 'session_domain' => '.localhost', 'session_secure' => true,
            'session_same_site' => 'none', 'cors' => ['*'], 'credentials' => false, 'stateful' => ['example.com']] as $key => $value)
            yield $key => [[$key => $value]];
        yield 'wrong-db' => [['mysql' => array_replace(self::config()['mysql'], ['database' => 'stayhub'])]];
    }

    #[DataProvider('invalidConfig')]
    public function test_refuses_effective_session_origin_or_database_drift(array $override): void
    {
        $this->expectException(\RuntimeException::class);
        Uc48BrowserEnvironment::assertConfiguration(array_replace(self::config(), $override), self::env(), self::manifest());
    }

    public function test_cached_config_is_rejected_before_manifest_or_connection(): void
    {
        $root = dirname(__DIR__, 3).'/.agent-workspace/tv3/uc48-qa-ui-01/guard-'.bin2hex(random_bytes(8));
        mkdir($root.'/bootstrap/cache', 0777, true);
        file_put_contents($root.'/bootstrap/cache/config.php', '<?php return [];');
        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('cached');
            Uc48BrowserEnvironment::beforeBoot($root);
        } finally {
            unlink($root.'/bootstrap/cache/config.php'); rmdir($root.'/bootstrap/cache'); rmdir($root.'/bootstrap'); rmdir($root);
        }
    }

    public static function scenarios(): array
    {
        return [['Populated', 8, '20000000301.49', 1], ['Zero', 0, '0.00', 0],
            ['BookingOnly', 6, '0.00', 0], ['RevenueOnly', 0, '300.76', 0],
            ['MissingDate', 0, '0.00', 1], ['Large', 0, '19999999999.98', 0]];
    }

    #[DataProvider('scenarios')]
    public function test_scenarios_have_independent_golden_values(string $scenario, int $bookings, string $revenue, int $missing): void
    {
        $spec = Uc48BrowserFixtures::specification($scenario, '2026-11-20T05:00:00Z');
        $data = Uc48BrowserFixtures::oracle($spec, '2026-11-20T05:00:00Z');
        $this->assertSame(['bookings_count' => $bookings, 'revenue_total' => $revenue, 'commission_revenue_total' => '0.00'], $data['summary']);
        $this->assertSame($missing, $data['warnings']['paid_invoices_missing_paid_at_global_count']);
        $this->assertSame('2025-12', $data['period']['from_month']);
        $this->assertSame('2026-11', $data['period']['to_month']);
        $this->assertCount(12, $data['monthly']);
        $this->assertSame($bookings, array_sum(array_column($data['booking_status_counts'], 'count')));
    }

    public function test_populated_period_controls_and_cutoff_use_native_calendar(): void
    {
        $spec = Uc48BrowserFixtures::specification('Populated', '2026-11-20T05:00:00Z');
        foreach ([['2026-10', '2026-11', 6, '300.76'], ['2026-07', '2026-07', 2, '0.00'],
            ['2026-09', '2026-09', 0, '0.75'], ['2026-08', '2026-08', 0, '19999999999.98'],
            ['2026-12', '2026-12', 0, '0.00']] as [$from, $to, $bookings, $revenue]) {
            $data = Uc48BrowserFixtures::oracle($spec, '2026-11-20T05:00:00Z', ['from_month' => $from, 'to_month' => $to]);
            $this->assertSame(['bookings_count' => $bookings, 'revenue_total' => $revenue, 'commission_revenue_total' => '0.00'], $data['summary']);
            $this->assertSame(1, $data['warnings']['paid_invoices_missing_paid_at_global_count']);
        }
        $data = Uc48BrowserFixtures::oracle($spec, '2026-10-31T17:00:00Z', ['from_month' => '2026-10', 'to_month' => '2026-11']);
        $this->assertSame('2026-09-30T17:00:00.000000Z', $data['period']['start_at']);
        $this->assertSame('2026-11-30T17:00:00.000000Z', $data['period']['end_at_exclusive']);
        $this->assertSame('300.76', $data['summary']['revenue_total']);
        $spec['bookings'][0]['invoice'][1] = '2026-11-20 05:00:01';
        $data = Uc48BrowserFixtures::oracle($spec, '2026-11-20T05:00:00Z', ['from_month' => '2026-10', 'to_month' => '2026-11']);
        $this->assertSame('200.51', $data['summary']['revenue_total']);
    }

    public static function invalidManifestFiles(): array
    {
        return [[null], ['{'], ['null'], ['42'], ['{}'], ['{"run_id":[]}']];
    }

    #[DataProvider('invalidManifestFiles')]
    public function test_missing_or_malformed_manifest_never_boots_an_application(?string $contents): void
    {
        $path = sys_get_temp_dir().'/uc48-browser-'.bin2hex(random_bytes(8)).'.json';
        if ($contents !== null) file_put_contents($path, $contents);
        try {
            $process = new Process([PHP_BINARY, '-r', <<<'PHP'
require 'vendor/autoload.php';
try { Tests\Support\Uc48BrowserEnvironment::beforeBoot('.'); exit(10); }
catch (RuntimeException|JsonException $error) { echo 'REJECTED_WITHOUT_BOOT'; }
PHP], dirname(__DIR__, 2), ['STAYHUB_UC48_MANIFEST' => $path]);
            $process->mustRun();
            $this->assertSame('REJECTED_WITHOUT_BOOT', $process->getOutput());
        } finally { if (is_file($path)) unlink($path); }
    }
}
