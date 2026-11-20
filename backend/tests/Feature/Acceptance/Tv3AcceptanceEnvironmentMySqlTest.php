<?php

namespace Tests\Feature\Acceptance;

use Illuminate\Database\QueryException;
use PHPUnit\Framework\Attributes\Depends;
use Tests\Support\Tv3AcceptanceMySqlEnvironment;
use Tests\Support\Tv3AcceptanceMySqlTestCase;

final class Tv3AcceptanceEnvironmentMySqlTest extends Tv3AcceptanceMySqlTestCase
{
    public function test_migrations_and_owned_transaction(): void
    {
        $identity = Tv3AcceptanceMySqlEnvironment::assertApplication($this->app, $this->tv3acManifest);
        $this->assertSame($this->tv3acManifest['database'], $identity['db']);
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
            'name' => 'TV3_AC transaction probe', 'email' => 'tv3ac-probe@example.test',
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

    public function test_schema_storage_constraints_and_scales(): void
    {
        $db = $this->app['db']->connection();
        foreach (['users', 'properties', 'room_types', 'rooms', 'services', 'bookings', 'booking_services',
            'invoices', 'invoice_items', 'reviews'] as $table) {
            $this->assertSame(0, $db->table($table)->count(), $table.' must have no seed or probe residue');
            $this->assertSame('InnoDB', $db->selectOne(
                'SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                [$table],
            )->engine);
        }
        $columns = [
            ['bookings', 'price_per_night', 12], ['bookings', 'room_total', 12],
            ['booking_services', 'quantity', 10], ['booking_services', 'unit_price', 12],
            ['booking_services', 'amount', 12], ['invoices', 'subtotal', 12],
            ['invoices', 'discount_amount', 12], ['invoices', 'extra_fee', 12],
            ['invoices', 'total_amount', 12], ['invoice_items', 'quantity', 10],
            ['invoice_items', 'unit_price', 12], ['invoice_items', 'amount', 12],
        ];
        foreach ($columns as [$table, $name, $precision]) {
            $column = $db->selectOne('SELECT DATA_TYPE AS kind, NUMERIC_PRECISION AS p, NUMERIC_SCALE AS s
                FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [$table, $name]);
            $this->assertNotNull($column);
            $this->assertSame('decimal', $column->kind);
            $this->assertSame($precision, (int) $column->p);
            $this->assertSame(2, (int) $column->s);
        }
        foreach (['invoices', 'reviews'] as $table) {
            $unique = $db->select('SELECT INDEX_NAME FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND NON_UNIQUE = 0
                GROUP BY INDEX_NAME HAVING COUNT(*) = 1 AND MAX(COLUMN_NAME) = ?', [$table, 'booking_id']);
            $this->assertCount(1, $unique, $table.' needs one single-column booking unique index');
        }
        foreach ([['invoices', 'booking_id', 'bookings', 'RESTRICT'],
            ['invoice_items', 'invoice_id', 'invoices', 'CASCADE'],
            ['reviews', 'booking_id', 'bookings', 'CASCADE'],
            ['reviews', 'customer_id', 'users', 'CASCADE'],
            ['reviews', 'property_id', 'properties', 'CASCADE']] as [$table, $column, $parent, $rule]) {
            $keys = $db->select('SELECT k.REFERENCED_TABLE_SCHEMA AS parent_schema,
                k.REFERENCED_TABLE_NAME AS parent_table, k.REFERENCED_COLUMN_NAME AS parent_column,
                r.DELETE_RULE AS delete_rule FROM information_schema.KEY_COLUMN_USAGE k
                JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
                    AND r.TABLE_NAME = k.TABLE_NAME AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
                WHERE k.TABLE_SCHEMA = DATABASE() AND k.TABLE_NAME = ? AND k.COLUMN_NAME = ?
                    AND k.REFERENCED_TABLE_NAME IS NOT NULL', [$table, $column]);
            $this->assertCount(1, $keys);
            $this->assertSame($this->tv3acManifest['database'], $keys[0]->parent_schema);
            $this->assertSame($parent, $keys[0]->parent_table);
            $this->assertSame('id', $keys[0]->parent_column);
            $this->assertSame($rule, $keys[0]->delete_rule);
        }
    }

    public function test_development_access_is_denied(): void
    {
        try {
            $development = $this->tv3acManifest['development_database'];
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
            'name' => 'TV3_AC failure probe', 'email' => 'tv3ac-failure@example.test',
            'password' => 'synthetic-not-a-login',
        ]);
        $this->assertSame(1, $db->table('users')->count());
        $this->assertNotSame('1', getenv('STAYHUB_TV3_AC_INJECT_TEST_FAILURE'), 'TV3_AC_EXPECTED_TEST_FAILURE_AFTER_WRITE');
    }

    // Deliberately has no Depends attribute: it must also run after the negative control fails.
    public function test_failure_probe_transaction_does_not_leak(): void
    {
        $this->assertSame(0, $this->app['db']->connection()->table('users')->count());
    }
}
