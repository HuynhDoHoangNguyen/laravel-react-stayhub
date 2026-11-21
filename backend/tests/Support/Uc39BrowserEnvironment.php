<?php

namespace Tests\Support;

use Illuminate\Foundation\Application;
use RuntimeException;

/** Browser-only guard. Never relax the API runner's testing/array-session guard. */
final class Uc39BrowserEnvironment
{
    public static function environment(): array
    {
        $keys = ['APP_ENV', 'DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD',
            'DB_URL', 'DATABASE_URL', 'DB_SOCKET', 'CACHE_STORE', 'SESSION_DRIVER', 'QUEUE_CONNECTION', 'STAYHUB_UC39_OPT_IN',
            'STAYHUB_UC39_BROWSER_OPT_IN', 'STAYHUB_UC39_MANIFEST', 'APP_KEY'];
        $env = [];
        foreach ($keys as $key) $env[$key] = getenv($key);
        return $env;
    }

    public static function assertEnvironment(array $env, array $manifest): void
    {
        if (($env['STAYHUB_UC39_BROWSER_OPT_IN'] ?? null) !== '1' || ($env['APP_ENV'] ?? null) !== 'local'
            || ($env['SESSION_DRIVER'] ?? null) !== 'database' || !is_string($env['APP_KEY'] ?? null)
            || preg_match('/\Abase64:[A-Za-z0-9+\/]{43}=\z/', $env['APP_KEY']) !== 1) {
            throw new RuntimeException('Browser QA requires explicit opt-in, local runtime and database sessions.');
        }
        // Reuse strict target/instance naming checks, without changing their API-test semantics.
        $target = $env; $target['APP_ENV'] = 'testing'; $target['SESSION_DRIVER'] = 'array';
        Uc39MySqlEnvironment::assertEnvironment($target, $manifest);
        if (!in_array($manifest['scenario'] ?? null, ['Populated', 'Zero', 'BookingOnly', 'RevenueOnly', 'MissingDate', 'Large'], true)) {
            throw new RuntimeException('Unknown browser fixture scenario.');
        }
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
        Uc39MySqlEnvironment::assertNoConfigCache($backend, getenv('APP_CONFIG_CACHE'));
        if (getenv('APP_BASE_PATH') || is_file(dirname($backend).'/.env.testing')) {
            throw new RuntimeException('Refuse alternate environment or base path.');
        }
        if (is_file(dirname($backend).'/.env')) {
            $root = \Dotenv\Dotenv::parse(file_get_contents(dirname($backend).'/.env'));
            if (!empty($root['APP_BASE_PATH']) || !empty($root['DATABASE_URL'])) throw new RuntimeException('Refuse root base path or URL override.');
            Uc39MySqlEnvironment::assertNoConfigCache($backend, $root['APP_CONFIG_CACHE'] ?? null);
        }
        $env = self::environment(); $path = $env['STAYHUB_UC39_MANIFEST'];
        if (!is_string($path) || !is_file($path)) throw new RuntimeException('Browser QA manifest is required.');
        $manifest = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($manifest)) throw new RuntimeException('Invalid browser manifest.');
        self::assertEnvironment($env, $manifest);
        return $manifest;
    }

    public static function assertConfiguration(array $config, array $env, array $manifest): void
    {
        if (($config['url'] ?? null) !== $manifest['backend_url'] || ($config['cors_patterns'] ?? null) !== []
            || ($config['debug'] ?? null) !== false || ($config['app_env'] ?? null) !== 'local' || ($config['session'] ?? null) !== 'database'
            || ($config['session_connection'] ?? null) !== 'mysql' || ($config['session_table'] ?? null) !== 'sessions'
            || ($config['session_cookie'] ?? null) !== 'uc39_'.$manifest['run_id'].'_session'
            || ($config['session_domain'] ?? null) !== null || ($config['session_secure'] ?? null) !== false
            || ($config['session_same_site'] ?? null) !== 'lax'
            || ($config['cors'] ?? null) !== [$manifest['frontend_url']]
            || ($config['credentials'] ?? null) !== true
            || ($config['stateful'] ?? null) !== [substr($manifest['frontend_url'], 7)]) {
            throw new RuntimeException('Browser effective session/origin configuration mismatch.');
        }
        $copy = $config; $copy['app_env'] = 'testing'; $copy['session'] = 'array';
        Uc39MySqlEnvironment::assertConfiguration($copy, $env);
    }

    public static function assertApplication(Application $app, array $manifest): array
    {
        self::assertEnvironment(self::environment(), $manifest);
        $c = $app['config'];
        self::assertConfiguration([
            'url' => $c->get('app.url'), 'cors_patterns' => $c->get('cors.allowed_origins_patterns'), 'debug' => $c->get('app.debug'), 'default' => $c->get('database.default'), 'app_env' => $c->get('app.env'),
            'cache' => $c->get('cache.default'), 'session' => $c->get('session.driver'), 'queue' => $c->get('queue.default'),
            'mysql' => $c->get('database.connections.mysql'), 'session_connection' => $c->get('session.connection'),
            'session_table' => $c->get('session.table'), 'session_cookie' => $c->get('session.cookie'),
            'session_domain' => $c->get('session.domain'), 'session_secure' => $c->get('session.secure'),
            'session_same_site' => $c->get('session.same_site'), 'cors' => $c->get('cors.allowed_origins'),
            'credentials' => $c->get('cors.supports_credentials'), 'stateful' => $c->get('sanctum.stateful'),
        ], self::environment(), $manifest);
        if ($app->runningUnitTests()) throw new RuntimeException('Browser QA must enforce CSRF outside unit-test mode.');
        $db = $app['db']->connection();
        $resolved = ['default' => 'mysql', 'app_env' => 'testing', 'cache' => 'array', 'session' => 'array', 'queue' => 'sync', 'mysql' => $db->getConfig()];
        Uc39MySqlEnvironment::assertConfiguration($resolved, self::environment());
        $identity = (array) $db->selectOne('SELECT DATABASE() AS db, VERSION() AS version, @@server_uuid AS uuid, CURRENT_USER() AS account');
        Uc39MySqlEnvironment::assertIdentity($identity, $manifest);
        Uc39MySqlEnvironment::assertGrants(array_map(static fn ($row) => array_values((array) $row)[0], $db->select('SHOW GRANTS')), $manifest['database']);
        return $identity;
    }
}
