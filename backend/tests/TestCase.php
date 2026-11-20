<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSafeTestDatabase();
    }

    /**
     * Hook called by RefreshDatabase before any migration or database reset runs.
     *
     * @return void
     */
    protected function beforeRefreshingDatabase()
    {
        $this->assertSafeTestDatabase();
    }

    /**
     * Ensure testing never targets production, development, or unapproved databases.
     * Fails closed immediately before any database interaction or migration execution.
     *
     * Permitted targets:
     * 1. SQLite in-memory (:memory:) or dedicated test files ending in _test.sqlite / .test.sqlite.
     * 2. Non-sqlite (MySQL/MariaDB/PostgreSQL) ONLY with explicit opt-in env
     *    and database name strictly ending in '_test' or '_testing' (or TV3 fixture pattern).
     */
    protected function assertSafeTestDatabase(): void
    {
        $defaultConnection = (string) config('database.default', 'sqlite');
        $rawConfig = config("database.connections.{$defaultConnection}", []);
        if (!is_array($rawConfig)) {
            $rawConfig = [];
        }

        static::validateDatabaseConfiguration($defaultConnection, $rawConfig);
    }

    /**
     * Validate effective database connection configuration against security rules.
     *
     * @param string $connectionName
     * @param array<string, mixed> $connectionConfig
     * @return void
     * @throws RuntimeException
     */
    public static function validateDatabaseConfiguration(string $connectionName, array $connectionConfig): void
    {
        $urlOverride = env('DB_URL') ?: env('DATABASE_URL');
        if (!empty($urlOverride) && empty($connectionConfig['url'])) {
            $connectionConfig['url'] = $urlOverride;
        }

        $parser = new \Illuminate\Database\ConfigurationUrlParser();
        $resolvedConfig = $parser->parseConfiguration($connectionConfig);

        $driver = (string) ($resolvedConfig['driver'] ?? $connectionName);
        $database = (string) ($resolvedConfig['database'] ?? '');

        // 1. SQLite: strictly permit in-memory or recognized isolated test file
        if ($driver === 'sqlite') {
            if ($database === ':memory:' || $database === 'sqlite::memory:') {
                return;
            }

            $baseName = basename($database);
            if (preg_match('/(^test|^testing|_test|_testing|\.test)\.sqlite$/i', $baseName)) {
                return;
            }

            throw new RuntimeException(
                "Blocked unsafe database in testing: SQLite target '{$database}' is not an authorized test database file. Testing only permits :memory: or files strictly ending in '_test.sqlite' / '.test.sqlite'."
            );
        }

        // 2. MySQL / other RDBMS: strictly require explicit opt-in AND verified test target
        if (in_array($driver, ['mysql', 'mariadb', 'pgsql', 'sqlsrv'], true)) {
            $allowExternal = env('ALLOW_EXTERNAL_TEST_DB') === true
                || env('ALLOW_MYSQL_TESTS') === true
                || env('ALLOW_EXTERNAL_TEST_DB') === 'true'
                || env('ALLOW_MYSQL_TESTS') === 'true'
                || !empty(getenv('STAYHUB_UC36_TEST_DATABASE'));

            $isDevDatabase = in_array(strtolower($database), ['stayhub', 'stayhub_dev', 'stayhub_development', 'stayhub_production', 'production', 'forge'], true);

            $isTestDatabase = !$isDevDatabase && (
                (bool) preg_match('/(_test|_testing)$/i', $database)
                || (bool) preg_match('/\Astayhub_uc36_test_[a-f0-9]{32}\z/', $database)
            );

            if ($allowExternal && $isTestDatabase) {
                return;
            }

            throw new RuntimeException(
                "Blocked unsafe database in testing: Connection '{$connectionName}' ({$driver}) targeting '{$database}' is not an authorized test database. Testing is only permitted on SQLite in-memory or dedicated test schemas ending with '_test'/'_testing' with explicit opt-in."
            );
        }

        throw new RuntimeException(
            "Blocked unsafe database in testing: Driver '{$driver}' targeting '{$database}' is not permitted for test execution."
        );
    }
}
