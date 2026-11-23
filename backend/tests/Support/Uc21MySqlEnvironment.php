<?php

namespace Tests\Support;

use Illuminate\Foundation\Application;
use RuntimeException;

final class Uc21MySqlEnvironment
{
    public static function assertTarget(array $env, array $manifest): void
    {
        $run = $manifest['run_id'] ?? '';
        if (!is_string($run) || !preg_match('/\A[a-f0-9]{32}\z/', $run)
            || ($manifest['database'] ?? '') !== 'stayhub_uc21_'.$run.'_test'
            || ($manifest['username'] ?? '') !== 'uc21_'.substr($run, 0, 16)
            || ($env['STAYHUB_UC21_OPT_IN'] ?? '') !== '1'
            || ($env['APP_ENV'] ?? '') !== 'testing' || ($env['DB_CONNECTION'] ?? '') !== 'mysql'
            || ($env['ALLOW_MYSQL_TESTS'] ?? '') !== 'true'
            || ($env['DB_HOST'] ?? '') !== '127.0.0.1' || (string) ($env['DB_PORT'] ?? '') !== (string) ($manifest['port'] ?? '')
            || ($env['DB_DATABASE'] ?? '') !== $manifest['database'] || ($env['DB_USERNAME'] ?? '') !== $manifest['username']
            || !is_string($env['DB_PASSWORD'] ?? null) || strlen($env['DB_PASSWORD']) < 32
            || ($env['DB_URL'] ?? null) !== '' || ($env['DATABASE_URL'] ?? null) !== '' || ($env['DB_SOCKET'] ?? null) !== ''
            || ($env['CACHE_STORE'] ?? '') !== 'array' || ($env['SESSION_DRIVER'] ?? '') !== 'array'
            || ($env['QUEUE_CONNECTION'] ?? '') !== 'sync'
            || !is_string($manifest['version'] ?? null) || !str_contains($manifest['version'], 'MariaDB')
            || (int) ($manifest['port'] ?? 0) < 1 || (int) $manifest['port'] > 65535) {
            throw new RuntimeException('UC21 refuses missing opt-in or an unowned database target.');
        }
    }

    public static function environment(): array
    {
        $values = [];
        foreach (['STAYHUB_UC21_OPT_IN', 'APP_ENV', 'DB_CONNECTION', 'ALLOW_MYSQL_TESTS', 'DB_HOST', 'DB_PORT',
            'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'DB_URL', 'DATABASE_URL', 'DB_SOCKET', 'CACHE_STORE', 'SESSION_DRIVER', 'QUEUE_CONNECTION'] as $key) {
            $values[$key] = $_ENV[$key] ?? getenv($key);
        }
        return $values;
    }

    public static function manifest(string $backend): array
    {
        if (getenv('APP_CONFIG_CACHE') || is_file($backend.'/bootstrap/cache/config.php')) throw new RuntimeException('UC21 refuses config cache.');
        $path = realpath(getenv('STAYHUB_UC21_MANIFEST') ?: '');
        $root = realpath($backend.'/../.agent-workspace/uc21');
        if (!$path || !$root || basename($path) !== 'manifest.json' || dirname(dirname($path)) !== $root) throw new RuntimeException('Unowned UC21 manifest path.');
        $manifest = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        self::assertTarget(self::environment(), $manifest);
        if (basename(dirname($path)) !== $manifest['run_id']) throw new RuntimeException('Manifest directory mismatch.');
        return $manifest;
    }

    public static function assertApplication(Application $app, array $manifest): void
    {
        self::assertTarget(self::environment(), $manifest);
        $config = $app['config'];
        $dbConfig = $config->get('database.connections.mysql');
        if ($config->get('database.default') !== 'mysql' || ($dbConfig['driver'] ?? '') !== 'mysql' || $config->get('app.env') !== 'testing'
            || $config->get('cache.default') !== 'array' || $config->get('session.driver') !== 'array'
            || $config->get('queue.default') !== 'sync' || !empty($dbConfig['read']) || !empty($dbConfig['write'])
            || !empty($dbConfig['options']) || !empty($dbConfig['prefix']) || !empty($dbConfig['url']) || !empty($dbConfig['unix_socket'])) {
            throw new RuntimeException('Unsafe UC21 effective connection.');
        }
        foreach (['host' => 'DB_HOST', 'port' => 'DB_PORT', 'database' => 'DB_DATABASE', 'username' => 'DB_USERNAME', 'password' => 'DB_PASSWORD'] as $key => $name) {
            if ((string) ($dbConfig[$key] ?? '') !== (string) self::environment()[$name]) throw new RuntimeException('Effective config differs from UC21 target.');
        }
        $db = $app['db']->connection();
        $identity = (array) $db->selectOne('SELECT DATABASE() AS db, CURRENT_USER() AS account, VERSION() AS version, @@hostname AS hostname, @@server_id AS server_id');
        foreach (['version', 'hostname', 'server_id'] as $key) {
            if ((string) $identity[$key] !== (string) $manifest[$key]) throw new RuntimeException('UC21 server identity mismatch.');
        }
        if ($identity['db'] !== $manifest['database'] || $identity['account'] !== $manifest['username'].'@127.0.0.1') throw new RuntimeException('UC21 database/account mismatch.');
        $scope = '`'.str_replace('_', '\\_', $manifest['database']).'`.*';
        $found = false;
        foreach ($db->select('SHOW GRANTS') as $row) {
            $grant = array_values((array) $row)[0];
            if (str_contains($grant, 'WITH GRANT OPTION')) throw new RuntimeException('Delegable UC21 grant.');
            if (str_starts_with($grant, 'GRANT USAGE ON *.* TO ')) continue;
            if (!str_starts_with($grant, 'GRANT ALL PRIVILEGES ON '.$scope.' TO ')) throw new RuntimeException('Global or foreign UC21 grant.');
            $found = true;
        }
        if (!$found) throw new RuntimeException('UC21 scoped grant missing.');
    }
}
