<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\Uc39BrowserEnvironment;

final class Uc39BrowserEnvironmentTest extends TestCase
{
    private static function manifest(): array
    {
        $run = str_repeat('a', 32);
        return ['run_id' => $run, 'database' => 'stayhub_uc39_test_'.$run, 'username' => 'uc39_'.substr($run, 0, 16),
            'development_database' => 'stayhub', 'port' => '3307', 'host' => '127.0.0.1', 'server_uuid' => 'b4736273-e223-11f1-a722-aaf8fde9dbf9', 'version' => '8.4.13',
            'scenario' => 'Populated', 'backend_url' => 'http://localhost:19001', 'frontend_url' => 'http://localhost:19002'];
    }

    private static function env(): array
    {
        $m = self::manifest();
        return ['APP_ENV' => 'local', 'APP_KEY' => 'base64:'.base64_encode(str_repeat('x', 32)), 'DB_CONNECTION' => 'mysql',
            'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3307', 'DB_DATABASE' => $m['database'], 'DB_USERNAME' => $m['username'],
            'DB_PASSWORD' => str_repeat('x', 64), 'DB_URL' => '', 'DATABASE_URL' => '', 'DB_SOCKET' => '', 'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'database', 'QUEUE_CONNECTION' => 'sync', 'STAYHUB_UC39_OPT_IN' => '1', 'STAYHUB_UC39_BROWSER_OPT_IN' => '1'];
    }

    private static function config(): array
    {
        $m = self::manifest(); $e = self::env();
        return ['url' => $m['backend_url'], 'cors_patterns' => [], 'debug' => false, 'default' => 'mysql', 'app_env' => 'local', 'cache' => 'array', 'session' => 'database', 'queue' => 'sync',
            'mysql' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => '3307', 'database' => $m['database'],
                'username' => $m['username'], 'password' => $e['DB_PASSWORD'], 'url' => null, 'unix_socket' => null],
            'session_connection' => 'mysql', 'session_table' => 'sessions', 'session_cookie' => 'uc39_'.$m['run_id'].'_session',
            'session_domain' => null, 'session_secure' => false, 'session_same_site' => 'lax',
            'cors' => [$m['frontend_url']], 'credentials' => true, 'stateful' => ['localhost:19002']];
    }

    public function test_accepts_only_owned_local_browser_configuration(): void
    {
        Uc39BrowserEnvironment::assertEnvironment(self::env(), self::manifest());
        Uc39BrowserEnvironment::assertConfiguration(self::config(), self::env(), self::manifest());
        $this->addToAssertionCount(2);
    }

    public static function invalidEnvironment(): iterable
    {
        foreach (['STAYHUB_UC39_BROWSER_OPT_IN' => '', 'STAYHUB_UC39_OPT_IN' => '', 'APP_ENV' => 'testing', 'APP_KEY' => '',
            'SESSION_DRIVER' => 'array', 'DB_DATABASE' => 'stayhub', 'DB_HOST' => 'remote', 'DB_PORT' => '3306',
            'DB_USERNAME' => 'root', 'DB_PASSWORD' => 'short', 'DATABASE_URL' => 'mysql://override', 'DB_URL' => 'mysql://override', 'CACHE_STORE' => 'database'] as $key => $value)
            yield $key => [[$key => $value], []];
        foreach (['database' => 'stayhub', 'username' => 'root', 'run_id' => '../escape', 'backend_url' => 'http://0.0.0.0:19001',
            'frontend_url' => 'https://example.com', 'scenario' => 'unknown', 'port' => '0'] as $key => $value) yield 'manifest-'.$key => [[], [$key => $value]];
        yield 'same-port' => [[], ['frontend_url' => 'http://localhost:19001']];
    }

    #[DataProvider('invalidEnvironment')]
    public function test_refuses_wrong_environment_before_database_access(array $env, array $manifest): void
    {
        $this->expectException(\RuntimeException::class);
        Uc39BrowserEnvironment::assertEnvironment(array_replace(self::env(), $env), array_replace(self::manifest(), $manifest));
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
        Uc39BrowserEnvironment::assertConfiguration(array_replace(self::config(), $override), self::env(), self::manifest());
    }

    public function test_cached_config_is_rejected_before_manifest_or_connection(): void
    {
        $root = dirname(__DIR__, 3).'/.agent-workspace/tv3/uc39-qa-ui-01/guard-'.bin2hex(random_bytes(8));
        mkdir($root.'/bootstrap/cache', 0777, true);
        file_put_contents($root.'/bootstrap/cache/config.php', '<?php return [];');
        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('cached');
            Uc39BrowserEnvironment::beforeBoot($root);
        } finally {
            unlink($root.'/bootstrap/cache/config.php'); rmdir($root.'/bootstrap/cache'); rmdir($root.'/bootstrap'); rmdir($root);
        }
    }

}
