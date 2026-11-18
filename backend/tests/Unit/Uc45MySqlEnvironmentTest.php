<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\Uc45MySqlEnvironment;

final class Uc45MySqlEnvironmentTest extends TestCase
{
    private function manifest(): array
    {
        return ['run_id' => str_repeat('a', 32), 'database' => 'stayhub_uc45_test_'.str_repeat('a', 32),
            'username' => 'uc45_'.str_repeat('a', 16), 'port' => '3307',
            'server_uuid' => 'b4736273-e223-11f1-a722-aaf8fde9dbf9', 'version' => '8.4.13'];
    }

    private function environment(): array
    {
        $m = $this->manifest();
        return ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_HOST' => '127.0.0.1',
            'DB_PORT' => $m['port'], 'DB_DATABASE' => $m['database'], 'DB_USERNAME' => $m['username'],
            'DB_PASSWORD' => str_repeat('x', 48), 'DB_URL' => '', 'DB_SOCKET' => '',
            'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync',
            'STAYHUB_UC45_OPT_IN' => '1'];
    }

    public function test_owned_environment_is_accepted_without_booting_an_application(): void
    {
        Uc45MySqlEnvironment::assertEnvironment($this->environment(), $this->manifest());
        $this->addToAssertionCount(1);
    }

    public static function unsafeEnvironment(): array
    {
        return [
            'no opt-in' => ['STAYHUB_UC45_OPT_IN', null], 'wrong opt-in' => ['STAYHUB_UC45_OPT_IN', 'yes'],
            'production' => ['APP_ENV', 'production'], 'sqlite' => ['DB_CONNECTION', 'sqlite'],
            'development schema' => ['DB_DATABASE', 'stayhub'],
            'other run' => ['DB_DATABASE', 'stayhub_uc45_test_'.str_repeat('b', 32)],
            'root user' => ['DB_USERNAME', 'root'], 'wrong host' => ['DB_HOST', 'localhost'],
            'wrong port' => ['DB_PORT', '3306'], 'url override' => ['DB_URL', 'mysql://unsafe'],
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
        Uc45MySqlEnvironment::assertEnvironment($env, $this->manifest());
    }

    public static function unsafeManifest(): array
    {
        return ['run id' => ['run_id', '../stayhub'], 'schema' => ['database', 'stayhub'],
            'user' => ['username', 'root'], 'port' => ['port', '0'],
            'uuid' => ['server_uuid', ''], 'engine' => ['version', '10.4.0-MariaDB']];
    }

    #[DataProvider('unsafeManifest')]
    public function test_invalid_manifest_is_rejected(string $key, mixed $value): void
    {
        $m = $this->manifest();
        $m[$key] = $value;
        $this->expectException(RuntimeException::class);
        Uc45MySqlEnvironment::assertEnvironment($this->environment(), $m);
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
        Uc45MySqlEnvironment::assertIdentity($identity, $m);
    }

    public static function foreignGrants(): array
    {
        return ['global' => [['GRANT ALL PRIVILEGES ON *.* TO `x`@`%`']],
            'global grant option' => [['GRANT USAGE ON *.* TO `x`@`%` WITH GRANT OPTION']],
            'foreign schema' => [['GRANT ALL PRIVILEGES ON `stayhub`.* TO `x`@`%`']],
            'unescaped wildcard schema' => [['GRANT ALL PRIVILEGES ON `stayhub_uc45_test_'.str_repeat('a', 32).'`.* TO `x`@`%`']],
            'role grant' => [['GRANT `admin`@`%` TO `x`@`%`']], 'no privileges' => [[]]];
    }

    #[DataProvider('foreignGrants')]
    public function test_privileges_outside_owned_schema_are_rejected(array $grants): void
    {
        $this->expectException(RuntimeException::class);
        Uc45MySqlEnvironment::assertGrants($grants, $this->manifest()['database']);
    }

    public function test_schema_privileges_without_grant_option_are_accepted(): void
    {
        $schema = $this->manifest()['database'];
        $literal = str_replace('_', '\\_', $schema);
        Uc45MySqlEnvironment::assertGrants(['GRANT USAGE ON *.* TO `x`@`%`',
            'GRANT ALL PRIVILEGES ON `'.$literal.'`.* TO `x`@`%`'], $schema);
        $this->expectException(RuntimeException::class);
        Uc45MySqlEnvironment::assertGrants(['GRANT ALL PRIVILEGES ON `'.$literal.'`.* TO `x`@`%` WITH GRANT OPTION'], $schema);
    }

    public function test_effective_configuration_is_compared_including_password(): void
    {
        $env = $this->environment();
        $config = ['default' => 'mysql', 'app_env' => 'testing', 'cache' => 'array', 'session' => 'array',
            'queue' => 'sync', 'mysql' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => '3307',
                'database' => $env['DB_DATABASE'], 'username' => $env['DB_USERNAME'], 'password' => $env['DB_PASSWORD'],
                'url' => null, 'unix_socket' => '']];
        Uc45MySqlEnvironment::assertConfiguration($config, $env);
        foreach (['default', 'app_env', 'cache', 'session', 'queue'] as $key) {
            $bad = $config;
            $bad[$key] = 'unsafe';
            try {
                Uc45MySqlEnvironment::assertConfiguration($bad, $env);
                $this->fail('Unsafe effective configuration accepted.');
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
        foreach (array_keys($config['mysql']) as $key) {
            $bad = $config;
            $bad['mysql'][$key] = 'unsafe';
            try {
                Uc45MySqlEnvironment::assertConfiguration($bad, $env);
                $this->fail('Unsafe effective connection accepted.');
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_cached_configuration_is_rejected_before_manifest_or_database_access(): void
    {
        $root = dirname(__DIR__, 3).'/.agent-workspace/tv3/uc45-qa-env-01/cache-guard-'.bin2hex(random_bytes(8));
        $cache = $root.'/bootstrap/cache';
        mkdir($cache, 0777, true);
        file_put_contents($cache.'/config.php', '<?php return [];');
        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('cached configuration');
            Uc45MySqlEnvironment::beforeBoot($root);
        } finally {
            unlink($cache.'/config.php');
            rmdir($cache);
            rmdir($root.'/bootstrap');
            rmdir($root);
        }
    }
}
