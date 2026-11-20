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
     * 1. SQLite in-memory (:memory:) or dedicated test files.
     * 2. Non-sqlite (MySQL/MariaDB/PostgreSQL) ONLY with explicit opt-in env
     *    and database name strictly ending in '_test' or '_testing'.
     */
    protected function assertSafeTestDatabase(): void
    {
        $defaultConnection = (string) config('database.default', 'sqlite');
        $connectionConfig = config("database.connections.{$defaultConnection}", []);
        $driver = (string) ($connectionConfig['driver'] ?? $defaultConnection);
        $database = (string) ($connectionConfig['database'] ?? '');

        // SQLite: permit in-memory or recognized test database file
        if ($driver === 'sqlite') {
            if ($database === ':memory:' || str_contains($database, 'test') || str_ends_with($database, '.sqlite')) {
                return;
            }
        }

        // MySQL / other RDBMS: strictly require explicit opt-in AND test suffix
        if (in_array($driver, ['mysql', 'mariadb', 'pgsql', 'sqlsrv'], true)) {
            $allowExternal = env('ALLOW_EXTERNAL_TEST_DB') === true
                || env('ALLOW_MYSQL_TESTS') === true
                || env('ALLOW_EXTERNAL_TEST_DB') === 'true'
                || env('ALLOW_MYSQL_TESTS') === 'true';

            $isTestDatabase = (bool) preg_match('/(_test|_testing)$/i', $database);

            if ($allowExternal && $isTestDatabase) {
                return;
            }

            throw new RuntimeException(
                "Blocked unsafe database in testing: Connection '{$defaultConnection}' ({$driver}) targeting '{$database}' is not an authorized test database. Testing is only permitted on SQLite in-memory or dedicated test schemas ending with '_test'/'_testing' with explicit opt-in."
            );
        }

        throw new RuntimeException(
            "Blocked unsafe database in testing: Driver '{$driver}' targeting '{$database}' is not permitted for test execution."
        );
    }
}
