<?php

namespace Tests\Feature\Statistics;

use Illuminate\Database\QueryException;
use PHPUnit\Framework\Attributes\Depends;
use Tests\Support\Uc48MySqlEnvironment;
use Tests\Support\Uc48MySqlTestCase;

final class Uc48EnvironmentMySqlTest extends Uc48MySqlTestCase
{
    public function test_migrations_and_owned_transaction(): void
    {
        $identity = Uc48MySqlEnvironment::assertApplication($this->app, $this->uc48Manifest);
        $this->assertSame($this->uc48Manifest['database'], $identity['db']);
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
        foreach (['bookings' => 'created_at', 'invoices' => 'paid_at'] as $table => $name) {
            $type = $db->selectOne('SELECT DATA_TYPE AS kind FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$table, $name]);
            $this->assertSame('timestamp', $type->kind);
            $this->assertSame('InnoDB', $db->selectOne('SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table])->engine);
        }
        $this->assertSame(count(glob(database_path('migrations/*.php'))), $db->table('migrations')->count());
        $db->table('users')->insert([
            'name' => 'UC48 transaction probe', 'email' => 'uc48-probe@example.test',
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
            $development = $this->uc48Manifest['development_database'];
            $this->assertMatchesRegularExpression('/\A[a-zA-Z0-9_]+\z/', $development);
            $this->app['db']->connection()->select('SELECT 1 FROM `'.$development.'`.invoices LIMIT 0');
            $this->fail('QA account unexpectedly accessed development data.');
        } catch (QueryException $error) {
            $this->assertSame(1142, $error->errorInfo[1]);
        }
    }

    public function test_controlled_failure_after_owned_write(): void
    {
        $db = $this->app['db']->connection();
        $db->table('users')->insert([
            'name' => 'UC48 failure probe', 'email' => 'uc48-failure@example.test',
            'password' => 'synthetic-not-a-login',
        ]);
        $this->assertSame(1, $db->table('users')->count());
        $this->assertNotSame('1', getenv('STAYHUB_UC48_INJECT_TEST_FAILURE'), 'UC48_EXPECTED_TEST_FAILURE_AFTER_WRITE');
    }

    // Deliberately has no Depends attribute: it must also run after the negative control fails.
    public function test_failure_probe_transaction_does_not_leak(): void
    {
        $this->assertSame(0, $this->app['db']->connection()->table('users')->count());
    }
}
