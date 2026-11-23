<?php

namespace Tests\Feature\Invoice;

use App\DTOs\Invoice\Requests\UpdateInvoicePaymentStatusRequest;
use App\Mappers\EloquentObjectMapper;
use App\Mappers\Invoice\InvoiceMapper;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;
use Tests\Support\InvoicePaymentMySqlTestCase;

final class InvoicePaymentCommitMySqlTest extends InvoicePaymentMySqlTestCase
{
    public function test_success_log_waits_for_outer_commit_and_independent_reader_sees_only_committed_state(): void
    {
        extract($this->fixture());
        $reader = $this->observer();
        $calls = [];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')->willReturnCallback(function ($message, $context) use (&$calls, $reader, $invoice) {
            $this->assertSame(0, DB::transactionLevel());
            $this->assertFalse(DB::connection()->getPdo()->inTransaction());
            $this->assertSame('PAID', $reader->table('invoices')->where('id', $invoice->id)->value('payment_status'));
            $calls[] = [$message, $context];
        });
        DB::beginTransaction();
        try {
            $response = $this->paymentService($logger)->update(new UpdateInvoicePaymentStatusRequest($invoice->id, 'PAID'), $host);
            $this->assertSame('PAID', $response->payment_status);
            $this->assertSame(1, DB::transactionLevel());
            $this->assertSame([], $calls);
            $uncommitted = $reader->table('invoices')->find($invoice->id);
            $this->assertSame('UNPAID', $uncommitted->payment_status);
            $this->assertNull($uncommitted->paid_at);
            DB::commit();
            $this->assertSame([['Xác nhận thanh toán hóa đơn thành công', [
                'invoice_id' => $invoice->id, 'payment_status' => 'PAID',
            ]]], $calls);
            $this->assertSame('2026-11-17 01:00:00', $reader->table('invoices')->where('id', $invoice->id)->value('paid_at'));
        } finally {
            while (DB::transactionLevel() > 0) DB::rollBack();
        }
    }

    public function test_outer_rollback_discards_update_and_callback_even_after_a_later_transaction_commits(): void
    {
        extract($this->fixture());
        $before = $invoice->getRawOriginal();
        $reader = $this->observer();
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('info');
        DB::beginTransaction();
        try {
            $this->paymentService($logger)->update(new UpdateInvoicePaymentStatusRequest($invoice->id, 'PAID'), $host);
            $this->assertSame('UNPAID', $reader->table('invoices')->where('id', $invoice->id)->value('payment_status'));
        } finally {
            DB::rollBack();
        }
        DB::transaction(fn () => DB::selectOne('SELECT 1'));
        $this->assertSame($before, $invoice->fresh()->getRawOriginal());
        $this->assertNull($reader->table('invoices')->where('id', $invoice->id)->value('paid_at'));
    }

    public function test_exception_in_updated_event_after_sql_rolls_back_without_success_log(): void
    {
        extract($this->fixture());
        $before = $invoice->getRawOriginal();
        $items = $invoice->items()->get()->toArray();
        $bookingBefore = $booking->getRawOriginal();
        $reader = $this->observer();
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('info');
        // Clone dispatcher to restore every existing listener, not flush shared model events.
        $dispatcher = Invoice::getEventDispatcher();
        Invoice::setEventDispatcher(clone $dispatcher);
        $reached = false;
        Invoice::updated(function (Invoice $updated) use ($invoice, $reader, &$reached): void {
            if ($updated->id !== $invoice->id) return;
            $reached = true;
            $this->assertSame('PAID', DB::table('invoices')->where('id', $invoice->id)->value('payment_status'));
            $this->assertSame('UNPAID', $reader->table('invoices')->where('id', $invoice->id)->value('payment_status'));
            throw new RuntimeException('Injected failure after SQL update.');
        });
        try {
            $this->paymentService($logger)->update(new UpdateInvoicePaymentStatusRequest($invoice->id, 'PAID'), $host);
            $this->fail('Injected failure must propagate.');
        } catch (RuntimeException $e) {
            $this->assertSame('Injected failure after SQL update.', $e->getMessage());
        } finally {
            Invoice::setEventDispatcher($dispatcher);
        }
        $this->assertTrue($reached);
        $this->assertSame(0, DB::transactionLevel());
        $this->assertSame($before, $invoice->fresh()->getRawOriginal());
        $this->assertSame($items, $invoice->items()->get()->toArray());
        $this->assertSame($bookingBefore, $booking->fresh()->getRawOriginal());
        // Negative control: the listener was removed and a normal payment can now commit.
        $this->paymentService($this->createStub(LoggerInterface::class))->update(new UpdateInvoicePaymentStatusRequest($invoice->id, 'PAID'), $host);
        $this->assertSame('PAID', $reader->table('invoices')->where('id', $invoice->id)->value('payment_status'));
    }

    public function test_mapper_exception_after_write_rolls_back_header_items_booking_and_emits_no_success(): void
    {
        extract($this->fixture());
        $before = $invoice->getRawOriginal();
        $items = $invoice->items()->get()->toArray();
        $bookingBefore = $booking->getRawOriginal();
        $reader = $this->observer();
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('info');
        $mapper = $this->createMock(ObjectMapperInterface::class);
        $mapper->expects($this->once())->method('map')->willReturnCallback(function () use ($invoice, $reader): never {
            $this->assertSame('PAID', DB::table('invoices')->where('id', $invoice->id)->value('payment_status'));
            $this->assertSame('UNPAID', $reader->table('invoices')->where('id', $invoice->id)->value('payment_status'));
            throw new RuntimeException('Injected mapping failure.');
        });
        try {
            $this->paymentService($logger, new InvoiceMapper(new EloquentObjectMapper($mapper)))
                ->update(new UpdateInvoicePaymentStatusRequest($invoice->id, 'PAID'), $host);
            $this->fail('Mapping failure must propagate.');
        } catch (RuntimeException $e) {
            $this->assertSame('Injected mapping failure.', $e->getMessage());
        }
        $this->assertSame(0, DB::transactionLevel());
        $this->assertSame($before, $invoice->fresh()->getRawOriginal());
        $this->assertSame($items, $invoice->items()->get()->toArray());
        $this->assertSame($bookingBefore, $booking->fresh()->getRawOriginal());
        $this->assertSame('UNPAID', $reader->table('invoices')->where('id', $invoice->id)->value('payment_status'));
    }
}
