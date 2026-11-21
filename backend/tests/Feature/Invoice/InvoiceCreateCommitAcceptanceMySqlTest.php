<?php

namespace Tests\Feature\Invoice;

use App\Models\InvoiceItem;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InvoiceCreateLifecycleMySqlTestCase;

final class InvoiceCreateCommitAcceptanceMySqlTest extends InvoiceCreateLifecycleMySqlTestCase
{
    public function test_invoice_create_lifecycle_actual_commit_is_visible_and_logged_once(): void
    {
        $reader = $this->observer();
        $this->captureSuccessLogs();
        $response = $this->actingAs($this->fixture->actors['H_A'])->postJson($this->fixture->url())->assertCreated();
        $id = $this->assertOutput($reader);
        $this->assertSame($id, $response->json('data.id'));
        $this->assertSame(0, DB::transactionLevel());
        $this->assertFalse(DB::connection()->getPdo()->inTransaction());
        $this->assertOneCommittedLog($this->successLogs, $id);
        $this->actingAs($this->fixture->actors['C_A'])->getJson('/api/customer/invoices/'.$id)->assertOk()->assertExactJson($response->json());
        $this->actingAs($this->fixture->actors['H_A'])->getJson('/api/host/invoices/'.$id)->assertOk()->assertExactJson($response->json());
        $this->evidence('committed', ['invoice_id' => $id, 'status' => 201, 'observer_items' => 3]);
    }

    public function test_success_and_observer_visibility_wait_for_real_outer_commit(): void
    {
        $reader = $this->observer();
        $this->captureSuccessLogs();
        DB::beginTransaction();
        $response = $this->actingAs($this->fixture->actors['H_A'])->postJson($this->fixture->url())->assertCreated();
        $this->assertSame(1, DB::transactionLevel());
        $this->assertSame(1, DB::table('invoices')->count());
        $this->assertSame(3, DB::table('invoice_items')->count());
        $this->assertNoOutput($reader);
        $this->assertSame([], $this->successLogs);
        $this->evidence('before-outer-commit', ['writer_invoices' => 1, 'writer_items' => 3, 'observer_invoices' => 0, 'logs' => []]);
        DB::commit();
        $id = $this->assertOutput($reader);
        $this->assertSame($id, $response->json('data.id'));
        $this->assertOneCommittedLog($this->successLogs, $id);
    }

    public function test_outer_rollback_discards_all_rows_and_callback_even_after_later_commit(): void
    {
        $reader = $this->observer();
        $this->captureSuccessLogs();
        DB::beginTransaction();
        $this->actingAs($this->fixture->actors['H_A'])->postJson($this->fixture->url())->assertCreated();
        $this->assertSame(1, DB::table('invoices')->count());
        $this->assertSame(3, DB::table('invoice_items')->count());
        $this->assertNoOutput($reader);
        $this->assertSame([], $this->successLogs);
        DB::rollBack();
        DB::transaction(static fn () => DB::selectOne('SELECT 1'));
        $this->assertNoOutput($reader);
        $this->assertSame([], $this->successLogs);
        $this->assertSame(0, DB::transactionLevel());
        $this->assertFalse(DB::connection()->getPdo()->inTransaction());
        $this->evidence('outer-rollback', ['observer_invoices' => 0, 'observer_items' => 0, 'logs_after_later_commit' => []]);
    }

    public static function failurePoints(): array
    {
        return ['after-header' => ['ROOM', 0], 'after-room' => ['SERVICE', 1]];
    }

    #[DataProvider('failurePoints')]
    public function test_actual_sql_failure_rolls_back_and_listener_removal_allows_next_commit(string $itemType, int $writtenItems): void
    {
        $reader = $this->observer();
        $this->captureSuccessLogs();
        InvoiceItem::query();
        $reported = [];
        $this->app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class)->reportable(
            static function (\Illuminate\Database\QueryException $error) use (&$reported): void {
                $reported[] = ['sqlstate' => $error->errorInfo[0], 'driver_code' => $error->errorInfo[1]];
            }
        )->stop();
        $dispatcher = InvoiceItem::getEventDispatcher();
        InvoiceItem::setEventDispatcher(clone $dispatcher);
        $reached = false;
        InvoiceItem::creating(function (InvoiceItem $item) use ($itemType, $writtenItems, &$reached, $reader): void {
            if ($item->item_type !== $itemType) return;
            $reached = true;
            $this->assertSame(1, DB::table('invoices')->count());
            $this->assertSame($writtenItems, DB::table('invoice_items')->count());
            $this->assertNoOutput($reader);
            $this->evidence('fault-boundary', ['writer_invoices' => 1, 'writer_items' => $writtenItems, 'observer_invoices' => 0]);
            $item->item_name = null; // Actual NOT NULL violation after production candidate validation.
        });
        try {
            $this->actingAs($this->fixture->actors['H_A'])->postJson($this->fixture->url())->assertStatus(500)
                ->assertExactJson(['message' => 'Đã xảy ra lỗi hệ thống. Vui lòng thử lại sau.', 'code' => 'INTERNAL_SERVER_ERROR']);
        } finally {
            InvoiceItem::setEventDispatcher($dispatcher);
        }
        $this->assertTrue($reached);
        $this->assertSame([['sqlstate' => '23000', 'driver_code' => 1048]], $reported);
        $this->evidence('sql-failure', ['reported' => $reported, 'item_type' => $itemType]);
        $this->assertNoOutput($reader);
        $this->assertSame([], $this->successLogs);
        $this->assertSame(0, DB::transactionLevel());
        $this->assertFalse(DB::connection()->getPdo()->inTransaction());
        $this->actingAs($this->fixture->actors['H_A'])->postJson($this->fixture->url())->assertCreated();
        $id = $this->assertOutput($reader);
        $this->assertOneCommittedLog($this->successLogs, $id);
    }
}
