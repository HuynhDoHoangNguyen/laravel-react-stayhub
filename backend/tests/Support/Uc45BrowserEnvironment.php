<?php

namespace Tests\Support;

use Illuminate\Foundation\Application;
use RuntimeException;

/** Browser-only guard. Never relax the API runner's testing/array-session guard. */
final class Uc45BrowserEnvironment
{
    public static function environment(): array
    {
        $keys = ['APP_ENV', 'DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD',
            'DB_URL', 'DB_SOCKET', 'CACHE_STORE', 'SESSION_DRIVER', 'QUEUE_CONNECTION', 'STAYHUB_UC45_OPT_IN',
            'STAYHUB_UC45_BROWSER_OPT_IN', 'STAYHUB_UC45_MANIFEST', 'APP_KEY'];
        $env = [];
        foreach ($keys as $key) $env[$key] = getenv($key);
        return $env;
    }

    public static function assertEnvironment(array $env, array $manifest): void
    {
        if (($env['STAYHUB_UC45_BROWSER_OPT_IN'] ?? null) !== '1' || ($env['APP_ENV'] ?? null) !== 'local'
            || ($env['SESSION_DRIVER'] ?? null) !== 'database' || !is_string($env['APP_KEY'] ?? null)
            || preg_match('/\Abase64:[A-Za-z0-9+\/]{43}=\z/', $env['APP_KEY']) !== 1) {
            throw new RuntimeException('Browser QA requires explicit opt-in, local runtime and database sessions.');
        }
        // Reuse strict target/instance naming checks, without changing their API-test semantics.
        $target = $env; $target['APP_ENV'] = 'testing'; $target['SESSION_DRIVER'] = 'array';
        Uc45MySqlEnvironment::assertEnvironment($target, $manifest);
        $ports = [];
        foreach (['backend_url', 'frontend_url'] as $key) {
            $url = $manifest[$key] ?? '';
            if (!is_string($url) || preg_match('/\Ahttp:\/\/localhost:([1-9][0-9]{3,4})\z/', $url, $match) !== 1
                || (int) $match[1] > 65535) throw new RuntimeException('Browser QA requires explicit loopback origins.');
            $ports[] = $match[1];
        }
        if ($ports[0] === $ports[1]) throw new RuntimeException('Browser QA ports must differ.');
    }

    public static function beforeBoot(string $backend): array
    {
        if (is_file($backend.'/bootstrap/cache/config.php')) throw new RuntimeException('Refuse cached Laravel configuration.');
        $env = self::environment(); $path = $env['STAYHUB_UC45_MANIFEST'];
        if (!is_string($path) || !is_file($path)) throw new RuntimeException('Browser QA manifest is required.');
        $manifest = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($manifest)) throw new RuntimeException('Invalid browser manifest.');
        self::assertEnvironment($env, $manifest);
        return $manifest;
    }

    public static function assertConfiguration(array $config, array $env, array $manifest): void
    {
        if (($config['app_env'] ?? null) !== 'local' || ($config['session'] ?? null) !== 'database'
            || ($config['session_connection'] ?? null) !== 'mysql' || ($config['session_table'] ?? null) !== 'sessions'
            || ($config['session_cookie'] ?? null) !== 'uc45_'.$manifest['run_id'].'_session'
            || ($config['session_domain'] ?? null) !== null || ($config['session_secure'] ?? null) !== false
            || ($config['session_same_site'] ?? null) !== 'lax'
            || ($config['cors'] ?? null) !== [$manifest['frontend_url']]
            || ($config['credentials'] ?? null) !== true
            || ($config['stateful'] ?? null) !== [substr($manifest['frontend_url'], 7)]) {
            throw new RuntimeException('Browser effective session/origin configuration mismatch.');
        }
        $copy = $config; $copy['app_env'] = 'testing'; $copy['session'] = 'array';
        Uc45MySqlEnvironment::assertConfiguration($copy, $env);
    }

    public static function assertApplication(Application $app, array $manifest): array
    {
        self::assertEnvironment(self::environment(), $manifest);
        $c = $app['config'];
        self::assertConfiguration([
            'default' => $c->get('database.default'), 'app_env' => $c->get('app.env'),
            'cache' => $c->get('cache.default'), 'session' => $c->get('session.driver'), 'queue' => $c->get('queue.default'),
            'mysql' => $c->get('database.connections.mysql'), 'session_connection' => $c->get('session.connection'),
            'session_table' => $c->get('session.table'), 'session_cookie' => $c->get('session.cookie'),
            'session_domain' => $c->get('session.domain'), 'session_secure' => $c->get('session.secure'),
            'session_same_site' => $c->get('session.same_site'), 'cors' => $c->get('cors.allowed_origins'),
            'credentials' => $c->get('cors.supports_credentials'), 'stateful' => $c->get('sanctum.stateful'),
        ], self::environment(), $manifest);
        if ($app->runningUnitTests()) throw new RuntimeException('Browser QA must enforce CSRF outside unit-test mode.');
        $db = $app['db']->connection();
        $identity = (array) $db->selectOne('SELECT DATABASE() AS db, VERSION() AS version, @@server_uuid AS uuid, CURRENT_USER() AS account');
        Uc45MySqlEnvironment::assertIdentity($identity, $manifest);
        Uc45MySqlEnvironment::assertGrants(array_map(static fn ($row) => array_values((array) $row)[0], $db->select('SHOW GRANTS')), $manifest['database']);
        return $identity;
    }
}
