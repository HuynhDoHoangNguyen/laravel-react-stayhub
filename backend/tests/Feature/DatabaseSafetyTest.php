<?php

namespace Tests\Feature;

use RuntimeException;
use Tests\TestCase;

class DatabaseSafetyTest extends TestCase
{
    public function test_fails_closed_when_database_connection_targets_unsafe_database(): void
    {
        // Simulate an unapproved MySQL database target without opt-in env
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Blocked unsafe database in testing');

        TestCase::validateDatabaseConfiguration('mysql', [
            'driver' => 'mysql',
            'database' => 'stayhub',
        ]);
    }

    public function test_fails_closed_when_url_override_targets_mysql_stayhub(): void
    {
        // PR13-02: Raw config appears to be sqlite :memory:, but url override targets MySQL stayhub
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Blocked unsafe database in testing');

        TestCase::validateDatabaseConfiguration('sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'url' => 'mysql://root:secret@127.0.0.1:3306/stayhub',
        ]);
    }

    public function test_fails_closed_when_sqlite_targets_ordinary_database_file(): void
    {
        // PR13-02: Arbitrary sqlite files without explicit test suffix must be blocked
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not an authorized test database file');

        TestCase::validateDatabaseConfiguration('sqlite', [
            'driver' => 'sqlite',
            'database' => 'database/stayhub.sqlite',
        ]);
    }

    public function test_fails_closed_when_sqlite_contains_test_substring_not_as_suffix(): void
    {
        // PR13-02: 'contest.sqlite' or 'latest.sqlite' must be blocked
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not an authorized test database file');

        TestCase::validateDatabaseConfiguration('sqlite', [
            'driver' => 'sqlite',
            'database' => 'database/contest.sqlite',
        ]);
    }

    public function test_permits_sqlite_in_memory_target(): void
    {
        TestCase::validateDatabaseConfiguration('sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        $this->assertTrue(true);
    }

    public function test_permits_dedicated_sqlite_test_file(): void
    {
        TestCase::validateDatabaseConfiguration('sqlite', [
            'driver' => 'sqlite',
            'database' => 'database/testing.sqlite',
        ]);
        TestCase::validateDatabaseConfiguration('sqlite', [
            'driver' => 'sqlite',
            'database' => 'database/unit_test.sqlite',
        ]);
        $this->assertTrue(true);
    }

    public function test_fails_closed_when_mysql_targets_dev_even_if_opt_in_present(): void
    {
        // PR13-02: Opt-in must NEVER allow dev database 'stayhub'
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Blocked unsafe database in testing');

        putenv('ALLOW_EXTERNAL_TEST_DB=true');
        try {
            TestCase::validateDatabaseConfiguration('mysql', [
                'driver' => 'mysql',
                'database' => 'stayhub',
            ]);
        } finally {
            putenv('ALLOW_EXTERNAL_TEST_DB');
        }
    }
}
