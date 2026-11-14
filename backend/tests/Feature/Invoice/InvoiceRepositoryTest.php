<?php

namespace Tests\Feature\Invoice;

use App\DTOs\Invoice\Requests\InvoiceFilterRequest;
use App\Mappers\Invoice\InvoiceMapper;
use App\Repositories\Invoice\InvoiceRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InvoiceRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function createApplication()
    {
        foreach (['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => ''] as $key => $expected) {
            if (($_ENV[$key] ?? getenv($key)) !== $expected) {
                throw new RuntimeException('Repository tests require isolated SQLite in-memory.');
            }
        }
        if (is_file(dirname(__DIR__, 3).'/bootstrap/cache/config.php')) {
            throw new RuntimeException('Repository tests must not use cached configuration.');
        }
        $app = parent::createApplication();
        if ($app['config']->get('database.default') !== 'sqlite'
            || $app['config']->get('database.connections.sqlite.database') !== ':memory:'
            || !empty($app['config']->get('database.connections.sqlite.url'))) {
            throw new RuntimeException('Unsafe repository test database configuration.');
        }
        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Synthetic read-query fixtures, not evidence of the invoice creation workflow.
        foreach ([1 => 'HOST', 2 => 'HOST', 3 => 'CUSTOMER', 4 => 'CUSTOMER'] as $id => $role) {
            DB::table('users')->insert(['id' => $id, 'name' => 'Fixture '.$id, 'email' => $id.'@example.test', 'password' => 'unused', 'role' => $role, 'status' => 'ACTIVE']);
        }
        foreach ([1, 2] as $id) {
            DB::table('properties')->insert(['id' => $id, 'host_id' => $id, 'name' => 'Property '.$id, 'type' => 'HOTEL', 'address' => 'Fixture address', 'phone' => '0900000000', 'status' => 'ACTIVE']);
            DB::table('room_types')->insert(['id' => $id, 'property_id' => $id, 'name' => 'Type']);
            DB::table('rooms')->insert(['id' => $id, 'property_id' => $id, 'room_type_id' => $id, 'room_number' => 'R'.$id, 'name' => 'Room', 'capacity' => 2, 'price_per_night' => '100.00', 'status' => 'ACTIVE']);
        }
        foreach ([[1, 1, 3, 'UNPAID'], [2, 1, 4, 'PAID'], [3, 2, 3, 'REFUNDED']] as [$id, $room, $customer, $status]) {
            DB::table('bookings')->insert([
                'id' => $id, 'booking_code' => 'BK-'.$id, 'room_id' => $room, 'customer_id' => $customer,
                'check_in_date' => '2026-11-01', 'check_out_date' => '2026-11-02', 'guest_count' => 1,
                'number_of_nights' => 1, 'price_per_night' => '100.00', 'room_total' => '100.00', 'status' => 'COMPLETED',
            ]);
            DB::table('invoices')->insert([
                'id' => $id, 'invoice_code' => 'INV-'.$id, 'booking_id' => $id, 'customer_name' => 'Customer '.$customer,
                'customer_email' => $customer.'@example.test', 'customer_phone' => '0900000000',
                'property_name' => 'Snapshot Property '.$room, 'room_name' => 'Snapshot Room',
                'subtotal' => '100.00', 'discount_amount' => '0.00', 'extra_fee' => '0.00', 'total_amount' => '100.00',
                'payment_status' => $status, 'paid_at' => $status === 'PAID' ? '2026-11-02 01:00:00' : null,
                'created_at' => '2026-11-02 00:00:00', 'updated_at' => '2026-11-02 00:00:00',
            ]);
            DB::table('invoice_items')->insert(['invoice_id' => $id, 'item_type' => 'ROOM', 'item_name' => 'Snapshot room charge', 'quantity' => '1.00', 'unit_price' => '100.00', 'amount' => '100.00']);
        }
    }

    private function repository(): InvoiceRepository
    {
        return $this->app->make(InvoiceRepository::class);
    }

    public function test_container_autowires_repository_and_lists_are_scoped_with_stable_tie_order(): void
    {
        $this->assertFalse($this->app->bound(InvoiceRepository::class));
        $repository = $this->repository();
        $this->assertInstanceOf(InvoiceRepository::class, $repository);
        $host = $repository->paginateForHost(1, new InvoiceFilterRequest);
        $customer = $repository->paginateForCustomer(3, new InvoiceFilterRequest);
        $this->assertSame([2, 1], $host->getCollection()->modelKeys());
        $this->assertSame([3, 1], $customer->getCollection()->modelKeys());
        $this->assertFalse($host->items()[0]->relationLoaded('items'));
        $this->assertSame(0, $repository->paginateForHost(999, new InvoiceFilterRequest)->total());
        $this->assertSame(0, $repository->paginateForCustomer(999, new InvoiceFilterRequest)->total());
    }

    public function test_filters_and_search_cannot_escape_ownership_scope(): void
    {
        $repo = $this->repository();
        $this->assertSame([2], $repo->paginateForHost(1, new InvoiceFilterRequest('PAID'))->getCollection()->modelKeys());
        $this->assertSame([2], $repo->paginateForHost(1, new InvoiceFilterRequest(search: ' Customer 4 '))->getCollection()->modelKeys());
        $this->assertSame([1], $repo->paginateForHost(1, new InvoiceFilterRequest(search: 'INV-1'))->getCollection()->modelKeys());
        $this->assertSame(0, $repo->paginateForHost(1, new InvoiceFilterRequest(search: 'INV-3'))->total());
        $this->assertSame([3], $repo->paginateForCustomer(3, new InvoiceFilterRequest('REFUNDED', 'Property 2'))->getCollection()->modelKeys());
        $this->assertSame(0, $repo->paginateForCustomer(3, new InvoiceFilterRequest(search: 'INV-2'))->total());
        $this->assertSame(2, $repo->paginateForHost(1, new InvoiceFilterRequest('unsupported'))->total());
        $this->assertSame(0, $repo->paginateForHost(1, new InvoiceFilterRequest(search: 'absent'))->total());
    }

    public function test_pagination_uses_explicit_page_preserves_metadata_and_clamps_size(): void
    {
        $page = $this->repository()->paginateForHost(1, new InvoiceFilterRequest(page: 2, perPage: 1));
        $this->assertSame([1], $page->getCollection()->modelKeys());
        $this->assertSame(2, $page->total());
        $this->assertSame(2, $page->currentPage());
        $this->assertSame(2, $page->lastPage());
        $this->assertSame(50, $this->repository()->paginateForHost(1, new InvoiceFilterRequest(perPage: 500))->perPage());
        $this->assertSame(1, $this->repository()->paginateForCustomer(3, new InvoiceFilterRequest(perPage: 0))->perPage());
    }

    public function test_detail_uses_same_not_found_result_for_foreign_owner_and_missing_id(): void
    {
        $repo = $this->repository();
        $this->assertNull($repo->findForHost(3, 1));
        $this->assertNull($repo->findForHost(999, 1));
        $this->assertNull($repo->findForCustomer(2, 3));
        $this->assertNull($repo->findForCustomer(999, 3));
        $this->assertTrue($repo->findForHost(1, 1)->relationLoaded('items'));
        $this->assertTrue($repo->findForCustomer(3, 3)->relationLoaded('items'));
    }

    public function test_mapping_loaded_results_has_no_queries_and_never_changes_snapshots(): void
    {
        $invoice = $this->repository()->findForHost(2, 1);
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $data = (new InvoiceMapper)->toResponse($invoice, true);
            $this->assertSame([], DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
        $this->assertSame('100.00', $data->total_amount);
        $this->assertSame('2026-11-02T01:00:00.000000Z', $data->paid_at);
        $this->assertSame('2026-11-02T00:00:00.000000Z', $data->created_at);
        $this->assertSame('Snapshot room charge', $data->items[0]->item_name);
        $this->assertSame('1.00', $data->items[0]->quantity);
        $this->assertDatabaseCount('invoices', 3);
        $this->assertDatabaseCount('invoice_items', 3);
    }

    private function newInvoiceAttributes(): array
    {
        // Synthetic repository-only fixture, not proof of the creation workflow.
        $booking = (array) DB::table('bookings')->where('id', 1)->first();
        $booking['id'] = 4;
        $booking['booking_code'] = 'BK-REPOSITORY-4';
        DB::table('bookings')->insert($booking);
        $attributes = (array) DB::table('invoices')->where('id', 1)->first();
        unset($attributes['id']);
        $attributes['booking_id'] = 4;
        $attributes['invoice_code'] = 'INV-REPOSITORY-4';
        return $attributes;
    }

    public function test_creation_lookup_loads_snapshots_and_keeps_caller_transaction_level(): void
    {
        $repository = $this->repository();
        $level = DB::transactionLevel();
        DB::transaction(function () use ($repository, $level) {
            $booking = $repository->findBookingForInvoiceWithLock(1);
            $this->assertSame($level + 1, DB::transactionLevel());
            $this->assertSame(1, $booking->id);
            $this->assertTrue($booking->relationLoaded('customer'));
            $this->assertTrue($booking->relationLoaded('room'));
            $this->assertTrue($booking->room->relationLoaded('property'));
            $this->assertTrue($booking->relationLoaded('bookingServices'));
            $this->assertNull($repository->findBookingForInvoiceWithLock(999));
            $this->assertTrue($repository->invoiceExistsForBooking(1));
            $this->assertFalse($repository->invoiceExistsForBooking(999));
            $this->assertTrue($repository->invoiceCodeExists('INV-1'));
            $this->assertFalse($repository->invoiceCodeExists('INV-ABSENT'));
        });
        $this->assertSame($level, DB::transactionLevel());
    }

    public function test_repository_persists_exact_header_items_without_owning_transaction(): void
    {
        $attributes = $this->newInvoiceAttributes();
        $repository = $this->repository();
        $level = DB::transactionLevel();
        DB::transaction(function () use ($attributes, $repository, $level) {
            $invoice = $repository->createInvoice($attributes);
            $item = $repository->createInvoiceItem($invoice, [
                'item_type' => 'SERVICE', 'item_name' => 'Snapshot item', 'description' => null,
                'quantity' => '1.25', 'unit_price' => '12.34', 'amount' => '15.43',
            ]);
            $loaded = $repository->loadInvoiceItems($invoice);
            $this->assertSame($level + 1, DB::transactionLevel());
            $this->assertSame(4, $invoice->booking_id);
            $this->assertSame('100.00', $invoice->total_amount);
            $this->assertSame('UNPAID', $invoice->payment_status);
            $this->assertNull($invoice->paid_at);
            $this->assertSame($invoice->id, $item->invoice_id);
            $this->assertSame('1.25', $item->quantity);
            $this->assertSame('12.34', $item->unit_price);
            $this->assertSame('15.43', $item->amount);
            $this->assertNull($item->description);
            $this->assertTrue($loaded->relationLoaded('items'));
            $this->assertSame([$item->id], $loaded->items->modelKeys());
        });
        $this->assertSame($level, DB::transactionLevel());
        $this->assertDatabaseCount('invoices', 4);
        $this->assertDatabaseCount('invoice_items', 4);
    }

    public function test_repository_leaves_rollback_to_caller_when_real_item_insert_fails(): void
    {
        $attributes = $this->newInvoiceAttributes();
        $repository = $this->repository();
        $level = DB::transactionLevel();
        try {
            DB::transaction(function () use ($attributes, $repository) {
                $invoice = $repository->createInvoice($attributes);
                $item = ['item_type' => 'ROOM', 'item_name' => 'Room', 'quantity' => '1.00', 'unit_price' => '100.00', 'amount' => '100.00'];
                $repository->createInvoiceItem($invoice, $item);
                $repository->createInvoiceItem($invoice, array_replace($item, ['item_name' => null]));
            });
            $this->fail('Expected a real NOT NULL database failure.');
        } catch (\Illuminate\Database\QueryException $error) {
            $this->assertStringContainsString('NOT NULL', $error->errorInfo[2]);
        }
        $this->assertSame($level, DB::transactionLevel());
        $this->assertDatabaseCount('invoices', 3);
        $this->assertDatabaseCount('invoice_items', 3);
        $this->assertFalse($repository->invoiceExistsForBooking(4));
    }

}
