<?php

namespace Tests\Feature\Booking;

use Illuminate\Database\QueryException;
use Tests\Support\Uc44MySqlEnvironment;
use Tests\Support\Uc44MySqlTestCase;

/** Environment probes only: no Booking workflow or reporting acceptance fixtures. */
final class Uc44EnvironmentMySqlTest extends Uc44MySqlTestCase
{
    public function test_migrations_and_owned_transaction(): void
    {
        $identity = Uc44MySqlEnvironment::assertApplication($this->app, $this->uc44Manifest);
        $this->assertSame($this->uc44Manifest['database'], $identity['db']);
        $db = $this->app['db']->connection();
        foreach (['users', 'properties', 'rooms', 'bookings', 'booking_services', 'invoices'] as $table) {
            $this->assertTrue($db->getSchemaBuilder()->hasTable($table));
            $this->assertSame(0, $db->table($table)->count());
        }
        $this->assertSame(count(glob(database_path('migrations/*.php'))), $db->table('migrations')->count());
        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame('utf8mb4_unicode_ci', $db->selectOne('SELECT @@collation_connection AS value')->value);
        $this->assertSame('InnoDB', $db->selectOne("SELECT ENGINE AS value FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bookings'")->value);
        foreach (['check_in_date', 'check_out_date'] as $name) {
            $this->assertSame('date', $db->selectOne("SELECT DATA_TYPE AS value FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bookings' AND COLUMN_NAME=?", [$name])->value);
        }
        foreach (['price_per_night', 'room_total'] as $name) {
            $column = $db->selectOne("SELECT DATA_TYPE AS kind, NUMERIC_PRECISION AS precision_value, NUMERIC_SCALE AS scale_value FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bookings' AND COLUMN_NAME=?", [$name]);
            $this->assertSame('decimal', $column->kind);
            $this->assertSame(12, (int) $column->precision_value);
            $this->assertSame(2, (int) $column->scale_value);
        }
        $db->table('uc44_environment_probe')->insert(['id' => 1, 'marker' => 'rollback-control']);
        $this->assertSame(1, $db->table('uc44_environment_probe')->count());
        $this->assertSame(1, $db->transactionLevel());
        $db->rollBack();
        $this->assertSame(0, $db->table('uc44_environment_probe')->count());
        $db->beginTransaction();
    }

    public function test_development_read_and_write_are_denied(): void
    {
        $development = $this->uc44Manifest['development_database'];
        $this->assertMatchesRegularExpression('/\A[a-zA-Z0-9_]+\z/', $development);
        // The INSERT has zero source rows even if the guard has regressed: never mutate dev.
        foreach (['SELECT 1 FROM `'.$development.'`.invoices LIMIT 0',
            'INSERT INTO `'.$development.'`.invoices (id) SELECT 1 WHERE 1=0'] as $sql) {
            try {
                $this->app['db']->connection()->statement($sql);
                $this->fail('QA account unexpectedly accessed development data.');
            } catch (QueryException $error) {
                $this->assertSame(1142, $error->errorInfo[1]);
            }
        }
    }

    public function test_controlled_failure_after_owned_write(): void
    {
        $db = $this->app['db']->connection();
        $db->table('uc44_environment_probe')->insert(['id' => 2, 'marker' => 'failure-control']);
        $this->assertSame(1, $db->table('uc44_environment_probe')->count());
        $this->assertNotSame('1', getenv('STAYHUB_UC44_INJECT_TEST_FAILURE'), 'UC44_EXPECTED_TEST_FAILURE_AFTER_WRITE');
    }

    // Must run even when the intentionally failing test above has failed.
    public function test_failure_probe_transaction_does_not_leak(): void
    {
        $this->assertSame(0, $this->app['db']->connection()->table('uc44_environment_probe')->count());
        $this->assertSame(1, $this->app['db']->connection()->transactionLevel());
    }
}
