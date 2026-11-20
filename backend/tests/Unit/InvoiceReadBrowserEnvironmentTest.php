<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\InvoiceReadBrowserEnvironment as Guard;
use Tests\Support\InvoiceReadBrowserFixtures as Fixtures;

final class InvoiceReadBrowserEnvironmentTest extends TestCase
{
    private function manifest(): array
    {
        return ['run_id' => str_repeat('a', 32), 'database' => 'stayhub_tv3_ac_test_'.str_repeat('a', 32),
            'username' => 'tv3ac_'.str_repeat('a', 16), 'host' => '127.0.0.1', 'port' => '3307',
            'server_uuid' => 'b4736273-e223-11f1-a722-aaf8fde9dbf9', 'version' => '8.4.13',
            'development_database' => 'stayhub', 'backend_url' => 'http://localhost:18001', 'frontend_url' => 'http://localhost:18002'];
    }

    private function environment(): array
    {
        $m = $this->manifest();
        return ['APP_ENV' => 'local', 'APP_KEY' => 'base64:'.base64_encode(str_repeat('k', 32)),
            'DB_CONNECTION' => 'mysql', 'DB_HOST' => $m['host'], 'DB_PORT' => $m['port'],
            'DB_DATABASE' => $m['database'], 'DB_USERNAME' => $m['username'], 'DB_PASSWORD' => str_repeat('p', 64),
            'DB_URL' => '', 'DB_SOCKET' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'database',
            'QUEUE_CONNECTION' => 'sync', 'STAYHUB_TV3_AC_OPT_IN' => '1', 'STAYHUB_TV3_AC_BROWSER_OPT_IN' => '1'];
    }

    private function configuration(): array
    {
        $m = $this->manifest(); $e = $this->environment();
        return ['url' => $m['backend_url'], 'cors_patterns' => [], 'debug' => false, 'default' => 'mysql',
            'app_env' => 'local', 'cache' => 'array', 'session' => 'database', 'queue' => 'sync',
            'mysql' => ['driver' => 'mysql', 'host' => $m['host'], 'port' => $m['port'], 'database' => $m['database'],
                'username' => $m['username'], 'password' => $e['DB_PASSWORD'], 'url' => '', 'unix_socket' => ''],
            'session_connection' => 'mysql', 'session_table' => 'sessions',
            'session_cookie' => 'tv3ac_ui_'.$m['run_id'].'_session', 'session_domain' => null,
            'session_secure' => false, 'session_same_site' => 'lax', 'cors' => [$m['frontend_url']],
            'credentials' => true, 'stateful' => ['localhost:18002']];
    }

    public function test_valid_browser_target_and_configuration(): void
    {
        Guard::assertEnvironment($this->environment(), $this->manifest());
        Guard::assertConfiguration($this->configuration(), $this->environment(), $this->manifest());
        $this->addToAssertionCount(2);
    }

    public static function unsafeEnvironment(): array
    {
        return [['STAYHUB_TV3_AC_BROWSER_OPT_IN', null], ['STAYHUB_TV3_AC_OPT_IN', '0'],
            ['APP_ENV', 'testing'], ['APP_ENV', 'production'], ['SESSION_DRIVER', 'array'],
            ['APP_KEY', ''], ['APP_KEY', []], ['DB_DATABASE', 'stayhub'], ['DB_USERNAME', 'root'],
            ['DB_HOST', 'localhost'], ['DB_URL', 'mysql://foreign'], ['DB_SOCKET', '/tmp/mysql.sock']];
    }

    #[DataProvider('unsafeEnvironment')]
    public function test_browser_environment_rejects_unsafe_values(string $key, mixed $value): void
    {
        $env = $this->environment(); $env[$key] = $value;
        $this->expectException(RuntimeException::class);
        Guard::assertEnvironment($env, $this->manifest());
    }

    public static function unsafeOrigins(): array
    {
        return [['frontend_url', 'http://example.com:18002'], ['backend_url', 'http://localhost:65536'],
            ['frontend_url', 'http://localhost:18001'], ['frontend_url', 'http://localhost:18002/path'],
            ['backend_url', []], ['backend_url', 'https://localhost:18001']];
    }

    #[DataProvider('unsafeOrigins')]
    public function test_browser_origin_guard(string $key, mixed $value): void
    {
        $m = $this->manifest(); $m[$key] = $value;
        $this->expectException(RuntimeException::class);
        Guard::assertEnvironment($this->environment(), $m);
    }

    public static function unsafeConfiguration(): array
    {
        return [['url', 'http://foreign:8000'], ['cors_patterns', ['.*']], ['debug', true], ['app_env', 'testing'],
            ['session', 'array'], ['session_connection', 'sqlite'], ['session_table', 'foreign_sessions'],
            ['session_cookie', 'development_session'], ['session_domain', 'localhost'], ['session_secure', true],
            ['session_same_site', 'none'], ['cors', ['*']], ['credentials', false], ['stateful', ['*']],
            ['default', 'sqlite'], ['cache', 'database'], ['queue', 'database']];
    }

    #[DataProvider('unsafeConfiguration')]
    public function test_effective_browser_configuration_is_enforced(string $key, mixed $value): void
    {
        $c = $this->configuration(); $c[$key] = $value;
        $this->expectException(RuntimeException::class);
        Guard::assertConfiguration($c, $this->environment(), $this->manifest());
    }

    public function test_browser_configuration_still_checks_database_password_and_split_connection(): void
    {
        foreach (['password' => 'different', 'read' => ['host' => 'foreign']] as $key => $value) {
            $c = $this->configuration(); $c['mysql'][$key] = $value;
            try {
                Guard::assertConfiguration($c, $this->environment(), $this->manifest());
                $this->fail('Database override accepted');
            } catch (RuntimeException) { $this->addToAssertionCount(1); }
        }
    }

    public function test_fixture_orders_cross_both_real_page_sizes(): void
    {
        $orders = Fixtures::orders();
        $this->assertCount(16, $orders['H_A']);
        $this->assertCount(15, $orders['C_A']);
        $this->assertSame(['I04', 'I06', 'I02', 'I01'], array_slice($orders['H_A'], 12));
        $this->assertSame(['I04', 'I03', 'I01'], array_slice($orders['C_A'], 12));
        $this->assertSame(['E12', 'E11'], array_slice($orders['H_A'], 0, 2));
        $this->assertSame(['I05', 'I03'], $orders['H_B']);
        $this->assertSame([], $orders['C_EMPTY']);
        $this->assertSame([], $orders['H_EMPTY']);
    }

    public function test_oracle_accepts_projection_and_rejects_wrong_value_type_order_and_envelope(): void
    {
        $data = [['id' => 2, 'total_amount' => '100.25'], ['id' => 1, 'total_amount' => '0.00']];
        $expected = ['data' => $data, 'total' => 2, 'current_page' => 1, 'per_page' => 9, 'last_page' => 1];
        $response = ['data' => $data, 'links' => ['first' => 'x', 'last' => 'x', 'prev' => null, 'next' => null],
            'meta' => ['total' => 2, 'current_page' => 1, 'per_page' => 9, 'last_page' => 1]];
        Fixtures::verify($response, $expected); $this->addToAssertionCount(1);
        $changes = [$response, $response, $response, $response, $response];
        $changes[0]['data'][0]['total_amount'] = '100.26';
        $changes[1]['data'][0]['id'] = '2';
        $changes[2]['data'] = array_reverse($data);
        $changes[3]['meta']['total'] = 3;
        $changes[4]['extra'] = 'unexpected';
        foreach ($changes as $bad) {
            try { Fixtures::verify($bad, $expected); $this->fail('Malformed response accepted'); }
            catch (RuntimeException) { $this->addToAssertionCount(1); }
        }
        Fixtures::verify(['data' => ['id' => 1, 'items' => []]], ['data' => ['items' => [], 'id' => 1]]);
        $this->addToAssertionCount(1);
    }
}
