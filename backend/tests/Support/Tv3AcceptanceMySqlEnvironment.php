<?php

namespace Tests\Support;

use Illuminate\Foundation\Application;
use RuntimeException;

/** TV3_AC only. All methods before assertApplication() are database-free. */
final class Tv3AcceptanceMySqlEnvironment
{
    public static function processEnvironment(): array
    {
        $keys = ['APP_ENV', 'DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME',
            'DB_PASSWORD', 'DB_URL', 'DB_SOCKET', 'CACHE_STORE', 'SESSION_DRIVER', 'QUEUE_CONNECTION',
            'STAYHUB_TV3_AC_OPT_IN', 'STAYHUB_TV3_AC_MANIFEST'];
        $env = [];
        foreach ($keys as $key) {
            $env[$key] = $_ENV[$key] ?? getenv($key);
        }
        return $env;
    }

    public static function assertEnvironment(array $env, array $manifest): void
    {
        $run = $manifest['run_id'] ?? '';
        $schema = $manifest['database'] ?? '';
        $user = $manifest['username'] ?? '';
        $port = $manifest['port'] ?? '';
        $development = $manifest['development_database'] ?? '';
        if (!is_string($run) || preg_match('/\A[a-f0-9]{32}\z/', $run) !== 1
            || $schema !== 'stayhub_tv3_ac_test_'.$run || $user !== 'tv3ac_'.substr($run, 0, 16)
            || !is_string($port) || !ctype_digit($port) || (int) $port < 1 || (int) $port > 65535
            || !is_string($manifest['server_uuid'] ?? null)
            || preg_match('/\A[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}\z/', $manifest['server_uuid']) !== 1
            || !is_string($manifest['version'] ?? null)
            || preg_match('/\A8\.4\.[0-9]+(?:[-.][a-zA-Z0-9.-]+)?\z/', $manifest['version']) !== 1
            || ($manifest['host'] ?? null) !== '127.0.0.1'
            || !is_string($development) || preg_match('/\A[a-zA-Z0-9_]{1,64}\z/', $development) !== 1
            || $development === $schema || str_starts_with($development, 'stayhub_tv3_ac_test_')
            || in_array(strtolower($development), ['mysql', 'information_schema', 'performance_schema', 'sys'], true)
            || ($env['STAYHUB_TV3_AC_OPT_IN'] ?? null) !== '1'
            || ($env['APP_ENV'] ?? null) !== 'testing' || ($env['DB_CONNECTION'] ?? null) !== 'mysql'
            || ($env['DB_HOST'] ?? null) !== '127.0.0.1' || (string) ($env['DB_PORT'] ?? '') !== $port
            || ($env['DB_DATABASE'] ?? null) !== $schema || ($env['DB_USERNAME'] ?? null) !== $user
            || !is_string($env['DB_PASSWORD'] ?? null) || strlen($env['DB_PASSWORD']) < 32
            || ($env['DB_URL'] ?? null) !== '' || ($env['DB_SOCKET'] ?? null) !== ''
            || ($env['CACHE_STORE'] ?? null) !== 'array' || ($env['SESSION_DRIVER'] ?? null) !== 'array'
            || ($env['QUEUE_CONNECTION'] ?? null) !== 'sync') {
            throw new RuntimeException('TV3_AC refuses a missing opt-in or an unowned MySQL target.');
        }
    }

    public static function assertNoConfigCache(string $backend, mixed $override): void
    {
        if ($override !== false && $override !== null && $override !== '') {
            throw new RuntimeException('TV3_AC refuses configuration cache path overrides.');
        }
        if (is_file($backend.'/bootstrap/cache/config.php')) {
            throw new RuntimeException('TV3_AC refuses cached configuration; no cache was removed.');
        }
    }

    public static function assertNamesAvailable(int $schemas, int $accounts): void
    {
        if ($schemas !== 0 || $accounts !== 0) {
            throw new RuntimeException('TV3_AC owned names already exist; refuse adoption.');
        }
    }

    public static function beforeBoot(string $backend): array
    {
        self::assertNoConfigCache($backend, $_ENV['APP_CONFIG_CACHE'] ?? getenv('APP_CONFIG_CACHE'));
        if (is_file(dirname($backend).'/.env.testing') || !empty($_ENV['APP_BASE_PATH']) || getenv('APP_BASE_PATH')) {
            throw new RuntimeException('TV3_AC refuses alternate environment files or application base paths.');
        }
        $rootEnv = dirname($backend).'/.env';
        if (is_file($rootEnv)) {
            $values = \Dotenv\Dotenv::parse(file_get_contents($rootEnv));
            self::assertNoConfigCache($backend, $values['APP_CONFIG_CACHE'] ?? null);
        }
        $env = self::processEnvironment();
        $path = $env['STAYHUB_TV3_AC_MANIFEST'] ?? '';
        if (!is_string($path) || !is_file($path)) {
            throw new RuntimeException('TV3_AC requires a runner-owned manifest.');
        }
        $manifest = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($manifest)) {
            throw new RuntimeException('Invalid TV3_AC manifest.');
        }
        self::assertEnvironment($env, $manifest);
        return $manifest;
    }

    /** Check Laravel's resolved configuration before opening its connection. */
    public static function assertConfiguration(array $config, array $env): void
    {
        $mysql = $config['mysql'] ?? [];
        if (($config['default'] ?? null) !== 'mysql' || ($config['app_env'] ?? null) !== 'testing'
            || ($config['cache'] ?? null) !== 'array' || ($config['session'] ?? null) !== 'array'
            || ($config['queue'] ?? null) !== 'sync') {
            throw new RuntimeException('TV3_AC effective application configuration differs from the owned target.');
        }
        if (!is_array($mysql) || array_key_exists('read', $mysql) || array_key_exists('write', $mysql) || !empty($mysql['prefix'])
            || !empty($mysql['options']) || !empty($mysql['sticky'])) {
            throw new RuntimeException('TV3_AC refuses split connections or table prefixes.');
        }
        foreach (['host' => 'DB_HOST', 'port' => 'DB_PORT', 'database' => 'DB_DATABASE',
            'username' => 'DB_USERNAME', 'password' => 'DB_PASSWORD', 'url' => 'DB_URL', 'unix_socket' => 'DB_SOCKET'] as $key => $var) {
            if (isset($mysql[$key]) && !is_scalar($mysql[$key])) {
                throw new RuntimeException('TV3_AC requires scalar connection settings.');
            }
            // Laravel may resolve an explicitly empty environment value to null.
            if ((string) ($mysql[$key] ?? '') !== (string) ($env[$var] ?? '')) {
                throw new RuntimeException('TV3_AC effective connection differs from the owned target.');
            }
        }
        if (($mysql['driver'] ?? null) !== 'mysql') {
            throw new RuntimeException('TV3_AC requires the MySQL driver.');
        }
    }

    public static function assertIdentity(array $identity, array $manifest): void
    {
        if (($identity['db'] ?? null) !== $manifest['database']
            || ($identity['uuid'] ?? null) !== $manifest['server_uuid']
            || ($identity['version'] ?? null) !== $manifest['version']
            || ($identity['account'] ?? null) !== $manifest['username'].'@%') {
            throw new RuntimeException('TV3_AC selected database, instance or account does not match its manifest.');
        }
    }

    public static function assertGrants(array $grants, string $schema): void
    {
        // MySQL database grants treat unescaped underscores as wildcards.
        $literalSchema = str_replace('_', '\\_', $schema);
        $schemaGrant = false;
        foreach ($grants as $grant) {
            if (!is_string($grant)) {
                throw new RuntimeException('TV3_AC requires scalar grants.');
            }
            if (str_contains($grant, 'WITH GRANT OPTION')) {
                throw new RuntimeException('TV3_AC refuses delegable privileges.');
            }
            if (preg_match('/\AGRANT USAGE ON \*\.\* TO `[^`]+`@`%`\z/', $grant) === 1) {
                continue;
            }
            if (preg_match('/\AGRANT ALL PRIVILEGES ON '.preg_quote('`'.$literalSchema.'`.*', '/').' TO `[^`]+`@`%`\z/', $grant) !== 1) {
                throw new RuntimeException('TV3_AC refuses global, foreign-schema or delegable privileges.');
            }
            $schemaGrant = true;
        }
        if (!$schemaGrant) {
            throw new RuntimeException('TV3_AC requires privileges on its owned schema.');
        }
    }

    /** Only SELECTs until identity and grants have both passed. */
    public static function assertApplication(Application $app, array $manifest): array
    {
        $config = $app['config'];
        self::assertConfiguration([
            'default' => $config->get('database.default'), 'app_env' => $config->get('app.env'),
            'cache' => $config->get('cache.default'), 'session' => $config->get('session.driver'),
            'queue' => $config->get('queue.default'), 'mysql' => $config->get('database.connections.mysql'),
        ], self::processEnvironment());
        $db = $app['db']->connection();
        $identity = (array) $db->selectOne('SELECT DATABASE() AS db, VERSION() AS version, @@server_uuid AS uuid, CURRENT_USER() AS account');
        self::assertIdentity($identity, $manifest);
        self::assertGrants(array_map(static fn ($row) => array_values((array) $row)[0], $db->select('SHOW GRANTS')), $manifest['database']);
        return $identity;
    }
}
