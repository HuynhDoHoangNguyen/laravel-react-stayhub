<?php

namespace Tests\Support;

use Illuminate\Foundation\Application;
use RuntimeException;

/** UC45 only. All methods before assertApplication() are database-free. */
final class Uc45MySqlEnvironment
{
    public static function processEnvironment(): array
    {
        $keys = ['APP_ENV', 'DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME',
            'DB_PASSWORD', 'DB_URL', 'DB_SOCKET', 'CACHE_STORE', 'SESSION_DRIVER', 'QUEUE_CONNECTION',
            'STAYHUB_UC45_OPT_IN', 'STAYHUB_UC45_MANIFEST'];
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
        if (!is_string($run) || preg_match('/\A[a-f0-9]{32}\z/', $run) !== 1
            || $schema !== 'stayhub_uc45_test_'.$run || $user !== 'uc45_'.substr($run, 0, 16)
            || !is_string($port) || !ctype_digit($port) || (int) $port < 1 || (int) $port > 65535
            || !is_string($manifest['server_uuid'] ?? null)
            || preg_match('/\A[a-f0-9-]{36}\z/', $manifest['server_uuid']) !== 1
            || !str_starts_with($manifest['version'] ?? '', '8.4.')
            || ($env['STAYHUB_UC45_OPT_IN'] ?? null) !== '1'
            || ($env['APP_ENV'] ?? null) !== 'testing' || ($env['DB_CONNECTION'] ?? null) !== 'mysql'
            || ($env['DB_HOST'] ?? null) !== '127.0.0.1' || (string) ($env['DB_PORT'] ?? '') !== $port
            || ($env['DB_DATABASE'] ?? null) !== $schema || ($env['DB_USERNAME'] ?? null) !== $user
            || !is_string($env['DB_PASSWORD'] ?? null) || strlen($env['DB_PASSWORD']) < 32
            || ($env['DB_URL'] ?? null) !== '' || ($env['DB_SOCKET'] ?? null) !== ''
            || ($env['CACHE_STORE'] ?? null) !== 'array' || ($env['SESSION_DRIVER'] ?? null) !== 'array'
            || ($env['QUEUE_CONNECTION'] ?? null) !== 'sync') {
            throw new RuntimeException('UC45 refuses a missing opt-in or an unowned MySQL target.');
        }
    }

    public static function beforeBoot(string $backend): array
    {
        if (is_file($backend.'/bootstrap/cache/config.php')) {
            throw new RuntimeException('UC45 refuses cached configuration; no cache was removed.');
        }
        $env = self::processEnvironment();
        $path = $env['STAYHUB_UC45_MANIFEST'] ?? '';
        if (!is_string($path) || !is_file($path)) {
            throw new RuntimeException('UC45 requires a runner-owned manifest.');
        }
        $manifest = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($manifest)) {
            throw new RuntimeException('Invalid UC45 manifest.');
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
            throw new RuntimeException('UC45 effective application configuration differs from the owned target.');
        }
        foreach (['host' => 'DB_HOST', 'port' => 'DB_PORT', 'database' => 'DB_DATABASE',
            'username' => 'DB_USERNAME', 'password' => 'DB_PASSWORD', 'url' => 'DB_URL', 'unix_socket' => 'DB_SOCKET'] as $key => $var) {
            // Laravel may resolve an explicitly empty environment value to null.
            if ((string) ($mysql[$key] ?? '') !== (string) ($env[$var] ?? '')) {
                throw new RuntimeException('UC45 effective connection differs from the owned target.');
            }
        }
        if (($mysql['driver'] ?? null) !== 'mysql') {
            throw new RuntimeException('UC45 requires the MySQL driver.');
        }
    }

    public static function assertIdentity(array $identity, array $manifest): void
    {
        if (($identity['db'] ?? null) !== $manifest['database']
            || ($identity['uuid'] ?? null) !== $manifest['server_uuid']
            || ($identity['version'] ?? null) !== $manifest['version']
            || ($identity['account'] ?? null) !== $manifest['username'].'@%') {
            throw new RuntimeException('UC45 selected database, instance or account does not match its manifest.');
        }
    }

    public static function assertGrants(array $grants, string $schema): void
    {
        // MySQL database grants treat unescaped underscores as wildcards.
        $literalSchema = str_replace('_', '\\_', $schema);
        $schemaGrant = false;
        foreach ($grants as $grant) {
            if (str_contains($grant, 'WITH GRANT OPTION')) {
                throw new RuntimeException('UC45 refuses delegable privileges.');
            }
            if (preg_match('/\AGRANT USAGE ON \*\.\* TO /', $grant) === 1) {
                continue;
            }
            if (!str_starts_with($grant, 'GRANT ALL PRIVILEGES ON `'.$literalSchema.'`.* TO ')) {
                throw new RuntimeException('UC45 refuses global, foreign-schema or delegable privileges.');
            }
            $schemaGrant = true;
        }
        if (!$schemaGrant) {
            throw new RuntimeException('UC45 requires privileges on its owned schema.');
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
