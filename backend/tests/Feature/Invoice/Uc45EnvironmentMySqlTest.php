<?php

namespace Tests\Feature\Invoice;

use App\Models\Booking;
use App\Models\Invoice;
use Illuminate\Database\QueryException;
use Tests\Support\Uc45InvoiceFixtures;
use Tests\Support\Uc45MySqlEnvironment;
use Tests\Support\Uc45MySqlTestCase;

final class Uc45EnvironmentMySqlTest extends Uc45MySqlTestCase
{
    public function test_owned_schema_has_real_migrations_constraints_and_expected_fixture(): void
    {
        $identity = Uc45MySqlEnvironment::assertApplication($this->app, $this->uc45Manifest);
        $this->assertSame($this->uc45Manifest['database'], $identity['db']);
        $db = $this->app['db']->connection();
        $this->assertSame(0, $db->table('invoices')->count());
        $fixture = Uc45InvoiceFixtures::create($this->app, $this->uc45Manifest);
        $this->assertCount(6, $fixture['actors']);
        $this->assertCount(4, $fixture['properties']);
        $this->assertSame(12, $db->table('invoices')->count());
        $this->assertSame(24, $db->table('invoice_items')->count());
        $this->assertSame(12, $db->table('booking_services')->count());
        foreach (['UNPAID', 'PAID', 'REFUNDED'] as $status) {
            $this->assertSame(4, $db->table('invoices')->where('payment_status', $status)->count());
        }
        $first = Invoice::findOrFail($fixture['invoices'][0]);
        $this->assertSame('208.50', $first->total_amount);
        $this->assertSame('213.25', $first->subtotal);
        $this->assertSame('10.00', $first->discount_amount);
        $this->assertSame('5.25', $first->extra_fee);
        $this->assertNull($first->paid_at);
        $this->assertSame('2026-11-18T02:00:00.000000Z', $first->created_at->toISOString());
        $this->assertSame('2026-11-18T03:00:00.000000Z', Invoice::findOrFail($fixture['invoices'][4])->paid_at->toISOString());
        $this->assertSame('200.50', Booking::findOrFail($fixture['bookings'][0])->room_total);
        $this->assertNotSame($first->property_name, $first->booking->room->property->name);
        $this->assertSame('999.99', $first->booking->room->price_per_night);
        $this->assertSame('200.50', $first->items()->where('item_type', 'ROOM')->firstOrFail()->amount);
        $this->assertSame(8, Invoice::whereHas('booking.room.property', fn ($q) => $q->where('host_id', $fixture['actors']['host_a']))->count());
        $this->assertSame(0, Invoice::whereHas('booking.room', fn ($q) => $q->where('property_id', $fixture['properties'][3]))->count());
        $this->assertSame(1, $db->table('properties')->where('status', 'INACTIVE')->count());
        $this->assertSame(1, $db->table('users')->where('status', 'BLOCKED')->count());
        $this->assertSame(1, $db->table('users')->where('status', 'INACTIVE')->count());
        $this->assertSame(12, $db->table('invoices')->where('created_at', '2026-11-18 02:00:00')->count());
        $this->assertSame(12, $db->table('invoices')->where('customer_name', 'like', '%snapshot%')->count());
        $fk = $db->selectOne("SELECT DELETE_RULE AS rule FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'invoices_booking_id_foreign'");
        $this->assertSame('RESTRICT', $fk->rule);
        $unique = $db->selectOne("SELECT NON_UNIQUE AS non_unique FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'invoices' AND INDEX_NAME = 'invoices_booking_id_unique'");
        $this->assertSame(0, (int) $unique->non_unique);
        try {
            $db->select('SELECT 1 FROM stayhub.invoices LIMIT 0');
            $this->fail('Fixture user can read development invoices.');
        } catch (QueryException $error) {
            $this->assertSame(1142, $error->errorInfo[1]);
        }
    }

    public function test_previous_fixture_transaction_does_not_leak_into_next_test(): void
    {
        $db = $this->app['db']->connection();
        foreach (['users', 'properties', 'rooms', 'bookings', 'invoices', 'invoice_items'] as $table) {
            $this->assertSame(0, $db->table($table)->count());
        }
        $this->assertSame(1, $db->transactionLevel());
    }
}
