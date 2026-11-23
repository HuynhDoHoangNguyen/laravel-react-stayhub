<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\Uc47BrowserEnvironment;
use Tests\Support\Uc47BrowserFixtures;
use Symfony\Component\Process\Process;

final class Uc47BrowserEnvironmentTest extends TestCase
{
    private static function manifest(): array
    {
        $run = str_repeat('a', 32);
        return ['run_id' => $run, 'database' => 'stayhub_uc47_test_'.$run, 'username' => 'uc47_'.substr($run, 0, 16),
            'port' => '3307', 'host' => '127.0.0.1', 'server_uuid' => 'b4736273-e223-11f1-a722-aaf8fde9dbf9', 'version' => '8.4.13',
            'scenario' => 'Populated', 'backend_url' => 'http://localhost:19001', 'frontend_url' => 'http://localhost:19002'];
    }

    private static function env(): array
    {
        $m = self::manifest();
        return ['APP_ENV' => 'local', 'APP_KEY' => 'base64:'.base64_encode(str_repeat('x', 32)), 'DB_CONNECTION' => 'mysql',
            'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3307', 'DB_DATABASE' => $m['database'], 'DB_USERNAME' => $m['username'],
            'DB_PASSWORD' => str_repeat('x', 64), 'DB_URL' => '', 'DB_SOCKET' => '', 'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'database', 'QUEUE_CONNECTION' => 'sync', 'STAYHUB_UC47_OPT_IN' => '1', 'STAYHUB_UC47_BROWSER_OPT_IN' => '1'];
    }

    private static function config(): array
    {
        $m = self::manifest(); $e = self::env();
        return ['url' => $m['backend_url'], 'cors_patterns' => [], 'debug' => false, 'default' => 'mysql', 'app_env' => 'local', 'cache' => 'array', 'session' => 'database', 'queue' => 'sync',
            'mysql' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => '3307', 'database' => $m['database'],
                'username' => $m['username'], 'password' => $e['DB_PASSWORD'], 'url' => null, 'unix_socket' => null],
            'session_connection' => 'mysql', 'session_table' => 'sessions', 'session_cookie' => 'uc47_'.$m['run_id'].'_session',
            'session_domain' => null, 'session_secure' => false, 'session_same_site' => 'lax',
            'cors' => [$m['frontend_url']], 'credentials' => true, 'stateful' => ['localhost:19002']];
    }

    public function test_accepts_only_owned_local_browser_configuration(): void
    {
        Uc47BrowserEnvironment::assertEnvironment(self::env(), self::manifest());
        Uc47BrowserEnvironment::assertConfiguration(self::config(), self::env(), self::manifest());
        $this->addToAssertionCount(2);
    }

    public static function invalidEnvironment(): iterable
    {
        foreach (['STAYHUB_UC47_BROWSER_OPT_IN' => '', 'STAYHUB_UC47_OPT_IN' => '', 'APP_ENV' => 'testing', 'APP_KEY' => '',
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
        Uc47BrowserEnvironment::assertEnvironment(array_replace(self::env(), $env), array_replace(self::manifest(), $manifest));
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
        Uc47BrowserEnvironment::assertConfiguration(array_replace(self::config(), $override), self::env(), self::manifest());
    }

    public function test_cached_config_is_rejected_before_manifest_or_connection(): void
    {
        $root = dirname(__DIR__, 3).'/.agent-workspace/tv3/uc47-qa-01a/guard-'.bin2hex(random_bytes(8));
        mkdir($root.'/bootstrap/cache', 0777, true);
        file_put_contents($root.'/bootstrap/cache/config.php', '<?php return [];');
        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('cached');
            Uc47BrowserEnvironment::beforeBoot($root);
        } finally {
            unlink($root.'/bootstrap/cache/config.php'); rmdir($root.'/bootstrap/cache'); rmdir($root.'/bootstrap'); rmdir($root);
        }
    }

    public static function scenarios(): array
    {
        return [['Populated', 8, 3, 10, '300.75', 1], ['Zero', 2, 0, 0, '0.00', 0],
            ['NoRevenue', 8, 3, 10, '0.00', 0], ['Large', 8, 3, 10, '19999999999.98', 1]];
    }

    #[DataProvider('scenarios')]
    public function test_fixture_oracle_has_independent_golden_values(string $scenario, int $users, int $hosts, int $bookings, string $revenue, int $excluded): void
    {
        $spec = Uc47BrowserFixtures::specification($scenario, '2026-11-18T07:00:00Z');
        $oracle = Uc47BrowserFixtures::oracle($spec, '2026-11-18T07:00:00.000000Z');
        $this->assertSame($users, $oracle['summary']['users_count']);
        $this->assertSame($hosts, $oracle['summary']['hosts_count']);
        $this->assertSame($bookings, $oracle['summary']['bookings_count']);
        $this->assertSame($revenue, $oracle['summary']['revenue_total']);
        $this->assertSame($excluded, $oracle['excluded_paid_invoices_count']);
        $this->assertSame($bookings ? [1, 1, 1, 5, 1, 1] : [0, 0, 0, 0, 0, 0], array_column($oracle['booking_status_counts'], 'count'));
        $this->assertSame($scenario === 'Zero' ? 0 : 4, $oracle['summary']['properties_count']);
        $this->assertSame($scenario === 'Zero' ? 0 : 4, $oracle['summary']['rooms_count']);
        $this->assertCount(12, $oracle['revenue_by_month']);
        $this->assertSame('2025-12', $oracle['revenue_by_month'][0]['month']);
        $this->assertSame('2026-11', $oracle['revenue_by_month'][11]['month']);
        $amounts = array_fill(0, 12, '0.00');
        if (in_array($scenario, ['Populated', 'Large'], true)) {
            $amounts[10] = $scenario === 'Large' ? '9999999999.99' : '100.25';
            $amounts[11] = $scenario === 'Large' ? '9999999999.99' : '200.50';
        }
        $this->assertSame($amounts, array_column($oracle['revenue_by_month'], 'total_amount'));
    }

    public function test_oracle_uses_response_period_and_handles_cutoff_without_production_helpers(): void
    {
        $spec = Uc47BrowserFixtures::specification('Populated', '2026-11-18T07:00:00Z');
        $spec['invoices'][] = ['payment_status' => 'PAID', 'paid_at' => '2026-11-18 07:00:01', 'total_amount' => '0.01'];
        $data = Uc47BrowserFixtures::oracle($spec, '2026-11-18T07:00:00Z');
        $this->assertSame('300.76', $data['summary']['revenue_total']);
        $this->assertSame('200.50', $data['revenue_by_month'][11]['total_amount']);
        $next = Uc47BrowserFixtures::oracle($spec, '2026-11-30T17:00:00Z');
        $this->assertSame('2026-01', $next['revenue_by_month'][0]['month']);
        $this->assertSame('2026-12', $next['revenue_by_month'][11]['month']);
        $this->assertSame('200.51', $next['revenue_by_month'][10]['total_amount']);
    }

    public function test_integer_cents_rejects_overflow_and_noncanonical_values(): void
    {
        $this->assertSame(999999999999, Uc47BrowserFixtures::cents('9999999999.99'));
        foreach (['-1.00', '1e2', '1.001', '01.00', '92233720368547758.08'] as $value) {
            try { Uc47BrowserFixtures::cents($value); $this->fail('Unsafe cents accepted.'); }
            catch (\InvalidArgumentException|\OverflowException) { $this->addToAssertionCount(1); }
        }
        $spec = Uc47BrowserFixtures::specification('Large', '2026-11-18T07:00:00Z');
        $spec['invoices'][0]['total_amount'] = '92233720368547758.07';
        $this->expectException(\OverflowException::class);
        Uc47BrowserFixtures::oracle($spec, '2026-11-18T07:00:00Z');
    }


    public static function invalidManifestFiles(): array
    {
        return [[null], ['{'], ['null'], ['42'], ['{}'], ['{"run_id":[]}']];
    }

    #[DataProvider('invalidManifestFiles')]
    public function test_missing_or_malformed_manifest_never_boots_an_application(?string $contents): void
    {
        $path = sys_get_temp_dir().'/uc47-browser-'.bin2hex(random_bytes(8)).'.json';
        if ($contents !== null) file_put_contents($path, $contents);
        try {
            $process = new Process([PHP_BINARY, '-r', <<<'PHP'
require 'vendor/autoload.php';
try { Tests\Support\Uc47BrowserEnvironment::beforeBoot('.'); exit(10); }
catch (RuntimeException|JsonException $error) { echo 'REJECTED_WITHOUT_BOOT'; }
PHP], dirname(__DIR__, 2), ['STAYHUB_UC47_MANIFEST' => $path]);
            $process->mustRun();
            $this->assertSame('REJECTED_WITHOUT_BOOT', $process->getOutput());
        } finally { if (is_file($path)) unlink($path); }
    }

}
