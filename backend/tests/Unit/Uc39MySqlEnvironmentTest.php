<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\Uc39MySqlEnvironment;
use Symfony\Component\Process\Process;

final class Uc39MySqlEnvironmentTest extends TestCase
{
    private static function scratch(): string
    {
        static $path;
        if ($path === null) {
            $path = dirname(__DIR__, 3).'/.agent-workspace/tv3/uc39-env-01/'.bin2hex(random_bytes(16));
            mkdir($path, 0777, true);
        }
        return $path;
    }

    public static function manifestFiles(): array
    {
        return ['missing' => [null], 'broken JSON' => ['{'], 'scalar' => ['42'],
            'null' => ['null'], 'empty object' => ['{}'], 'wrong field types' => ['{"run_id":[]}']];
    }

    #[DataProvider('manifestFiles')]
    public function test_invalid_manifest_file_is_rejected_without_booting(?string $contents): void
    {
        $path = self::scratch().'/uc39-manifest-'.bin2hex(random_bytes(8)).'.json';
        if ($contents !== null) {
            file_put_contents($path, $contents);
        }
        try {
            $process = new Process([PHP_BINARY, '-r', <<<'PHP'
require 'vendor/autoload.php';
try {
    Tests\Support\Uc39MySqlEnvironment::beforeBoot('.');
    exit(10);
} catch (RuntimeException|JsonException $error) {
    echo 'UC39_REJECTED_BEFORE_BOOT';
}
PHP], dirname(__DIR__, 2), ['STAYHUB_UC39_MANIFEST' => $path, 'STAYHUB_UC39_OPT_IN' => '1']);
            $process->mustRun();
            $this->assertSame('UC39_REJECTED_BEFORE_BOOT', $process->getOutput());
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_split_connection_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        Uc39MySqlEnvironment::assertConfiguration([
            'default' => 'mysql', 'app_env' => 'testing', 'cache' => 'array',
            'session' => 'array', 'queue' => 'sync', 'mysql' => ['read' => ['host' => 'unsafe']],
        ], $this->environment());
    }

    private function manifest(): array
    {
        return ['run_id' => str_repeat('a', 32), 'database' => 'stayhub_uc39_test_'.str_repeat('a', 32),
            'username' => 'uc39_'.str_repeat('a', 16), 'port' => '3307', 'host' => '127.0.0.1',
            'server_uuid' => 'b4736273-e223-11f1-a722-aaf8fde9dbf9', 'version' => '8.4.13', 'development_database' => 'stayhub'];
    }

    private function environment(): array
    {
        $m = $this->manifest();
        return ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_HOST' => '127.0.0.1',
            'DB_PORT' => $m['port'], 'DB_DATABASE' => $m['database'], 'DB_USERNAME' => $m['username'],
            'DB_PASSWORD' => str_repeat('x', 48), 'DB_URL' => '', 'DATABASE_URL' => '', 'DB_SOCKET' => '',
            'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync',
            'STAYHUB_UC39_OPT_IN' => '1'];
    }

    public function test_owned_environment_is_accepted_without_booting_an_application(): void
    {
        Uc39MySqlEnvironment::assertEnvironment($this->environment(), $this->manifest());
        $this->addToAssertionCount(1);
    }

    public static function unsafeEnvironment(): array
    {
        return [
            'no opt-in' => ['STAYHUB_UC39_OPT_IN', null], 'wrong opt-in' => ['STAYHUB_UC39_OPT_IN', 'yes'],
            'production' => ['APP_ENV', 'production'], 'sqlite' => ['DB_CONNECTION', 'sqlite'],
            'development schema' => ['DB_DATABASE', 'stayhub'],
            'other run' => ['DB_DATABASE', 'stayhub_uc39_test_'.str_repeat('b', 32)],
            'root user' => ['DB_USERNAME', 'root'], 'wrong host' => ['DB_HOST', 'localhost'],
            'wrong port' => ['DB_PORT', '3306'], 'url override' => ['DB_URL', 'mysql://unsafe'],
            'legacy URL override' => ['DATABASE_URL', 'mysql://unsafe'],
            'socket override' => ['DB_SOCKET', '/tmp/mysql.sock'], 'empty password' => ['DB_PASSWORD', ''],
            'database cache' => ['CACHE_STORE', 'database'], 'database session' => ['SESSION_DRIVER', 'database'],
            'database queue' => ['QUEUE_CONNECTION', 'database'],
        ];
    }

    #[DataProvider('unsafeEnvironment')]
    public function test_unsafe_environment_is_rejected_before_any_connection(string $key, mixed $value): void
    {
        $env = $this->environment();
        $env[$key] = $value;
        $this->expectException(RuntimeException::class);
        Uc39MySqlEnvironment::assertEnvironment($env, $this->manifest());
    }

    public static function unsafeManifest(): array
    {
        return ['run id' => ['run_id', '../stayhub'], 'schema' => ['database', 'stayhub'],
            'user' => ['username', 'root'], 'port' => ['port', '0'],
            'uuid' => ['server_uuid', ''], 'malformed uuid' => ['server_uuid', str_repeat('-', 36)],
            'host' => ['host', 'localhost'], 'version array' => ['version', []], 'port array' => ['port', []],
            'schema array' => ['database', []], 'run array' => ['run_id', []], 'engine' => ['version', '10.4.0-MariaDB'],
            'unsafe dev identifier' => ['development_database', 'unsafe`name'],
            'system schema' => ['development_database', 'mysql'],
            'qa schema as dev' => ['development_database', 'stayhub_uc39_test_'.str_repeat('b', 32)]];
    }

    #[DataProvider('unsafeManifest')]
    public function test_invalid_manifest_is_rejected(string $key, mixed $value): void
    {
        $m = $this->manifest();
        $m[$key] = $value;
        $this->expectException(RuntimeException::class);
        Uc39MySqlEnvironment::assertEnvironment($this->environment(), $m);
    }

    public static function identityDrift(): array
    {
        return ['schema' => ['db', 'stayhub'], 'instance' => ['uuid', 'another-instance'],
            'version' => ['version', '8.4.99'], 'account' => ['account', 'root@localhost']];
    }

    #[DataProvider('identityDrift')]
    public function test_observed_identity_must_match(string $key, string $value): void
    {
        $m = $this->manifest();
        $identity = ['db' => $m['database'], 'uuid' => $m['server_uuid'], 'version' => $m['version'],
            'account' => $m['username'].'@%'];
        $identity[$key] = $value;
        $this->expectException(RuntimeException::class);
        Uc39MySqlEnvironment::assertIdentity($identity, $m);
    }

    public static function foreignGrants(): array
    {
        return ['global' => [['GRANT ALL PRIVILEGES ON *.* TO `x`@`%`']],
            'global grant option' => [['GRANT USAGE ON *.* TO `x`@`%` WITH GRANT OPTION']],
            'foreign schema' => [['GRANT ALL PRIVILEGES ON `stayhub`.* TO `x`@`%`']],
            'unescaped wildcard schema' => [['GRANT ALL PRIVILEGES ON `stayhub_uc39_test_'.str_repeat('a', 32).'`.* TO `x`@`%`']],
            'role grant' => [['GRANT `admin`@`%` TO `x`@`%`']], 'no privileges' => [[]],
            'nonstring' => [[[]]], 'usage with suffix' => [['GRANT USAGE ON *.* TO `x`@`%` WITH MAX_USER_CONNECTIONS 1']]];
    }

    #[DataProvider('foreignGrants')]
    public function test_privileges_outside_owned_schema_are_rejected(array $grants): void
    {
        $this->expectException(RuntimeException::class);
        Uc39MySqlEnvironment::assertGrants($grants, $this->manifest()['database']);
    }

    public function test_schema_privileges_without_grant_option_are_accepted(): void
    {
        $schema = $this->manifest()['database'];
        $literal = str_replace('_', '\\_', $schema);
        Uc39MySqlEnvironment::assertGrants(['GRANT USAGE ON *.* TO `x`@`%`',
            'GRANT ALL PRIVILEGES ON `'.$literal.'`.* TO `x`@`%`'], $schema);
        $this->expectException(RuntimeException::class);
        Uc39MySqlEnvironment::assertGrants(['GRANT ALL PRIVILEGES ON `'.$literal.'`.* TO `x`@`%` WITH GRANT OPTION'], $schema);
    }

    public function test_effective_configuration_is_compared_including_password(): void
    {
        $env = $this->environment();
        $config = ['default' => 'mysql', 'app_env' => 'testing', 'cache' => 'array', 'session' => 'array',
            'queue' => 'sync', 'mysql' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => '3307',
                'database' => $env['DB_DATABASE'], 'username' => $env['DB_USERNAME'], 'password' => $env['DB_PASSWORD'],
                'url' => null, 'unix_socket' => '']];
        Uc39MySqlEnvironment::assertConfiguration($config, $env);
        foreach (['default', 'app_env', 'cache', 'session', 'queue'] as $key) {
            $bad = $config;
            $bad[$key] = 'unsafe';
            try {
                Uc39MySqlEnvironment::assertConfiguration($bad, $env);
                $this->fail('Unsafe effective configuration accepted.');
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
        foreach (array_keys($config['mysql']) as $key) {
            $bad = $config;
            $bad['mysql'][$key] = 'unsafe';
            try {
                Uc39MySqlEnvironment::assertConfiguration($bad, $env);
                $this->fail('Unsafe effective connection accepted.');
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_cached_configuration_is_rejected_before_manifest_or_database_access(): void
    {
        $root = self::scratch().'/cache-guard-'.bin2hex(random_bytes(8));
        $cache = $root.'/bootstrap/cache';
        mkdir($cache, 0777, true);
        file_put_contents($cache.'/config.php', '<?php return [];');
        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('cached configuration');
            Uc39MySqlEnvironment::beforeBoot($root);
        } finally {
            unlink($cache.'/config.php');
            rmdir($cache);
            rmdir($root.'/bootstrap');
            rmdir($root);
        }
    }
    public function test_uc48_target_is_not_owned_by_uc39(): void
    {
        $m = $this->manifest();
        $m['database'] = str_replace('uc39', 'uc48', $m['database']);
        $this->expectException(RuntimeException::class);
        Uc39MySqlEnvironment::assertEnvironment($this->environment(), $m);
    }

    public static function cacheOverrides(): array
    {
        return [['elsewhere/config.php'], [''], [null], [false]];
    }

    #[DataProvider('cacheOverrides')]
    public function test_cache_override_policy(mixed $override): void
    {
        if ($override === 'elsewhere/config.php') {
            $this->expectException(RuntimeException::class);
        }
        Uc39MySqlEnvironment::assertNoConfigCache(self::scratch().'/uc39-absent-'.bin2hex(random_bytes(8)), $override);
        $this->addToAssertionCount(1);
    }

    public static function collisions(): array
    {
        return [[1, 0], [0, 1], [1, 1]];
    }

    #[DataProvider('collisions')]
    public function test_existing_resources_are_never_adopted(int $schemas, int $accounts): void
    {
        $this->expectException(RuntimeException::class);
        Uc39MySqlEnvironment::assertNamesAvailable($schemas, $accounts);
    }

    public function test_new_resource_names_are_available(): void
    {
        Uc39MySqlEnvironment::assertNamesAvailable(0, 0);
        $this->addToAssertionCount(1);
    }

    public static function connectionOverrides(): array
    {
        return [['read', null], ['write', []], ['prefix', 'foreign_'], ['options', [12 => true]], ['sticky', true]];
    }

    public function test_alternate_environment_file_is_refused_before_boot(): void
    {
        $root = self::scratch().'/alternate-env-'.bin2hex(random_bytes(8));
        mkdir($root.'/backend', 0777, true);
        file_put_contents($root.'/.env.testing', 'APP_CONFIG_CACHE=unsafe');
        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('alternate environment');
            Uc39MySqlEnvironment::beforeBoot($root.'/backend');
        } finally {
            unlink($root.'/.env.testing');
            rmdir($root.'/backend');
            rmdir($root);
        }
    }

    public function test_application_base_path_override_is_refused_before_boot(): void
    {
        $process = new Process([PHP_BINARY, '-r', <<<'PHP'
require 'vendor/autoload.php';
try { Tests\Support\Uc39MySqlEnvironment::beforeBoot('.'); exit(10); }
catch (RuntimeException $e) { echo $e->getMessage(); }
PHP], dirname(__DIR__, 2), ['APP_BASE_PATH' => 'unsafe']);
        $process->mustRun();
        $this->assertStringContainsString('alternate environment', $process->getOutput());
    }

    #[DataProvider('connectionOverrides')]
    public function test_connection_overrides_are_refused(string $key, mixed $value): void
    {
        $env = $this->environment();
        $mysql = ['driver' => 'mysql', 'host' => $env['DB_HOST'], 'port' => $env['DB_PORT'],
            'database' => $env['DB_DATABASE'], 'username' => $env['DB_USERNAME'], 'password' => $env['DB_PASSWORD']];
        $mysql[$key] = $value;
        $this->expectException(RuntimeException::class);
        Uc39MySqlEnvironment::assertConfiguration(['default' => 'mysql', 'app_env' => 'testing',
            'cache' => 'array', 'session' => 'array', 'queue' => 'sync', 'mysql' => $mysql], $env);
    }

    public function test_resolved_connection_drift_is_rejected_before_any_select(): void
    {
        $env = $this->environment();
        $originalEnv = $_ENV;
        $_ENV = array_merge($_ENV, $env);
        try {
            $mysql = ['driver' => 'mysql', 'host' => $env['DB_HOST'], 'port' => $env['DB_PORT'],
                'database' => $env['DB_DATABASE'], 'username' => $env['DB_USERNAME'],
                'password' => $env['DB_PASSWORD'], 'url' => '', 'unix_socket' => ''];
            $app = new \Illuminate\Foundation\Application(dirname(__DIR__, 2));
            $app->instance('config', new \Illuminate\Config\Repository([
                'app' => ['env' => 'testing'], 'cache' => ['default' => 'array'],
                'session' => ['driver' => 'array'], 'queue' => ['default' => 'sync'],
                'database' => ['default' => 'mysql', 'connections' => ['mysql' => $mysql]],
            ]));
            $connection = $this->createMock(\Illuminate\Database\Connection::class);
            $connection->method('getConfig')->willReturn(array_replace($mysql, ['database' => 'stayhub']));
            $connection->expects($this->never())->method('selectOne');
            $connection->expects($this->never())->method('select');
            $manager = $this->createMock(\Illuminate\Database\DatabaseManager::class);
            $manager->method('connection')->willReturn($connection);
            $app->instance('db', $manager);
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('effective connection differs');
            Uc39MySqlEnvironment::assertApplication($app, $this->manifest());
        } finally {
            $_ENV = $originalEnv;
        }
    }

}
