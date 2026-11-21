<?php

namespace Tests\Feature\Dashboard;

use Illuminate\Database\QueryException;
use PHPUnit\Framework\Attributes\Depends;
use Tests\Support\Uc47MySqlEnvironment;
use Tests\Support\Uc47MySqlTestCase;

final class Uc47EnvironmentMySqlTest extends Uc47MySqlTestCase
{
    public function test_migrations_and_owned_transaction(): void
    {
        $identity = Uc47MySqlEnvironment::assertApplication($this->app, $this->uc47Manifest);
        $this->assertSame($this->uc47Manifest['database'], $identity['db']);
        $db = $this->app['db']->connection();
        foreach (['users', 'properties', 'rooms', 'bookings', 'invoices', 'invoice_items'] as $table) {
            $this->assertTrue($db->getSchemaBuilder()->hasTable($table));
            $this->assertSame(0, $db->table($table)->count());
        }
        $this->assertSame('UTC', config('app.timezone'));
        $column = $db->selectOne("SELECT DATA_TYPE AS kind, NUMERIC_PRECISION AS precision_value, NUMERIC_SCALE AS scale_value FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'invoices' AND COLUMN_NAME = 'total_amount'");
        $this->assertSame('decimal', $column->kind);
        $this->assertSame(12, (int) $column->precision_value);
        $this->assertSame(2, (int) $column->scale_value);
        $this->assertSame('utf8mb4_unicode_ci', $db->selectOne('SELECT @@collation_connection AS value')->value);
        $db->table('users')->insert([
            'name' => 'UC47 transaction probe', 'email' => 'uc47-probe@example.test',
            'password' => 'synthetic-not-a-login', 'role' => 'ADMIN', 'status' => 'ACTIVE',
        ]);
        $this->assertSame(1, $db->table('users')->count());
        $this->assertSame(1, $db->transactionLevel());
    }

    #[Depends('test_migrations_and_owned_transaction')]
    public function test_previous_transaction_is_rolled_back(): void
    {
        $this->assertSame(0, $this->app['db']->connection()->table('users')->count());
        $this->assertSame(1, $this->app['db']->connection()->transactionLevel());
    }

    public function test_development_access_is_denied(): void
    {
        try {
            $this->app['db']->connection()->select('SELECT 1 FROM stayhub.invoices LIMIT 0');
            $this->fail('QA account unexpectedly accessed development data.');
        } catch (QueryException $error) {
            $this->assertSame(1142, $error->errorInfo[1]);
        }
    }

    public function test_controlled_failure_after_owned_write(): void
    {
        $db = $this->app['db']->connection();
        $db->table('users')->insert([
            'name' => 'UC47 failure probe', 'email' => 'uc47-failure@example.test',
            'password' => 'synthetic-not-a-login',
        ]);
        $this->assertSame(1, $db->table('users')->count());
        $this->assertNotSame('1', getenv('STAYHUB_UC47_INJECT_TEST_FAILURE'), 'UC47_EXPECTED_TEST_FAILURE_AFTER_WRITE');
    }

    // Deliberately has no Depends attribute: it must also run after the negative control fails.
    public function test_failure_probe_transaction_does_not_leak(): void
    {
        $this->assertSame(0, $this->app['db']->connection()->table('users')->count());
    }
}
