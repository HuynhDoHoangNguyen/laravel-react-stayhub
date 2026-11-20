<?php

namespace Tests\Feature;

use RuntimeException;
use Tests\TestCase;

class DatabaseSafetyTest extends TestCase
{
    public function test_fails_closed_when_database_connection_targets_unsafe_database(): void
    {
        // Simulate an unapproved MySQL database target without opt-in env
        config([
            'database.default' => 'mysql',
            'database.connections.mysql' => [
                'driver' => 'mysql',
                'database' => 'stayhub',
            ],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Blocked unsafe database in testing');

        $this->assertSafeTestDatabase();
    }

    public function test_permits_sqlite_in_memory_target(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
            ],
        ]);

        $this->assertSafeTestDatabase();
        $this->assertTrue(true);
    }
}
