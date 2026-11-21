<?php

namespace Tests\Feature\Review;

use App\Models\Review;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ReviewCreateLifecycleMySqlTestCase;

final class ReviewCreateCommitAcceptanceMySqlTest extends ReviewCreateLifecycleMySqlTestCase
{
    public function test_review_create_lifecycle_actual_commit_is_visible_and_logged_once(): void
    {
        $reader = $this->observer();
        $this->captureSuccessLogs();
        $response = $this->actingAs($this->fixture->actors['C_A'])->postJson($this->url(), $this->payload())->assertCreated();
        $id = $this->assertOutput($reader);
        $response->assertExactJson(['data' => $this->fixture->expectedCustomer('SUCCESS_A', $id, 5, $this->payload()['comment'])]);
        $this->assertSame(0, DB::transactionLevel());
        $this->assertFalse(DB::connection()->getPdo()->inTransaction());
        $this->assertOneCommittedLog($this->successLogs, $id);
        $this->assertCustomerReadBack($id);
        $this->evidence('committed', ['review_id' => $id, 'status' => 201, 'observer_reviews' => 1]);
    }

    public function test_success_and_observer_visibility_wait_for_actual_outer_commit(): void
    {
        $reader = $this->observer();
        $this->captureSuccessLogs();
        DB::beginTransaction();
        $response = $this->actingAs($this->fixture->actors['C_A'])->postJson($this->url(), $this->payload())->assertCreated();
        $this->assertSame(1, DB::transactionLevel());
        $this->assertSame(1, DB::table('reviews')->where('booking_id', $this->bookingId())->count());
        $this->assertNoOutput($reader);
        $this->assertSame([], $this->successLogs);
        $this->evidence('before-outer-commit', ['writer_reviews' => 1, 'observer_reviews' => 0, 'logs' => []]);
        DB::commit();
        $id = $this->assertOutput($reader);
        $this->assertSame($id, $response->json('data.id'));
        $this->assertOneCommittedLog($this->successLogs, $id);
    }

    public function test_outer_rollback_discards_review_and_callback_even_after_later_commit(): void
    {
        $reader = $this->observer();
        $this->captureSuccessLogs();
        DB::beginTransaction();
        $this->actingAs($this->fixture->actors['C_A'])->postJson($this->url(), $this->payload())->assertCreated();
        $this->assertSame(1, DB::table('reviews')->where('booking_id', $this->bookingId())->count());
        $this->assertNoOutput($reader);
        $this->assertSame([], $this->successLogs);
        DB::rollBack();
        DB::transaction(static fn () => DB::selectOne('SELECT 1'));
        $this->assertNoOutput($reader);
        $this->assertSame([], $this->successLogs);
        $this->assertSame(0, DB::transactionLevel());
        $this->assertFalse(DB::connection()->getPdo()->inTransaction());
        $this->evidence('outer-rollback', ['observer_reviews' => 0, 'logs_after_later_commit' => []]);
    }

    public static function failurePoints(): array
    {
        return ['sql-not-null' => ['sql'], 'after-insert' => ['after-insert']];
    }

    #[DataProvider('failurePoints')]
    public function test_fault_rolls_back_then_retry_commits_without_stale_callback(string $mode): void
    {
        $reader = $this->observer();
        $this->captureSuccessLogs();
        Review::query();
        $dispatcher = Review::getEventDispatcher();
        Review::setEventDispatcher(clone $dispatcher);
        $reported = [];
        $this->app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class)->reportable(
            static function (\Throwable $error) use (&$reported): void {
                $reported[] = ['class' => $error::class, 'driver_code' => $error instanceof QueryException ? $error->errorInfo[1] : null];
            }
        )->stop();
        $reached = false;
        $hook = function (Review $review) use ($mode, $reader, &$reached): void {
            if ((int) $review->booking_id !== $this->bookingId()) return;
            $reached = true;
            $this->assertGreaterThan(0, DB::transactionLevel());
            $this->assertNoOutput($reader);
            $this->assertSame([], $this->successLogs);
            if ($mode === 'sql') {
                $review->comment = null; // Real MySQL 1048 after production candidate validation.
            } else {
                $this->assertSame(1, DB::table('reviews')->where('booking_id', $this->bookingId())->count());
                $this->evidence('after-insert-boundary', ['review_id' => $review->id, 'writer_reviews' => 1, 'observer_reviews' => 0]);
                throw new \RuntimeException('PRIVATE_REVIEW_AFTER_INSERT_FAULT');
            }
        };
        if ($mode === 'sql') Review::creating($hook); else Review::created($hook);
        try {
            $this->actingAs($this->fixture->actors['C_A'])->postJson($this->url(), $this->payload())->assertStatus(500)
                ->assertExactJson(['message' => 'Đã xảy ra lỗi hệ thống. Vui lòng thử lại sau.', 'code' => 'INTERNAL_SERVER_ERROR']);
        } finally {
            Review::setEventDispatcher($dispatcher);
        }
        $this->assertTrue($reached);
        $this->assertCount(1, $reported);
        $this->assertSame($mode === 'sql' ? 1048 : null, $reported[0]['driver_code']);
        $this->assertNoOutput($reader);
        $this->assertSame([], $this->successLogs);
        $this->assertSame(0, DB::transactionLevel());
        $this->assertFalse(DB::connection()->getPdo()->inTransaction());
        $this->actingAs($this->fixture->actors['C_A'])->postJson($this->url(), $this->payload())->assertCreated();
        $id = $this->assertOutput($reader);
        $this->assertOneCommittedLog($this->successLogs, $id);
        $this->evidence('fault-and-recovery', ['mode' => $mode, 'reported' => $reported, 'retry_review_id' => $id]);
    }

    public function test_database_unique_constraint_rejects_second_committed_review_with_real_1062(): void
    {
        $reader = $this->observer();
        $this->captureSuccessLogs();
        $this->actingAs($this->fixture->actors['C_A'])->postJson($this->url(), $this->payload())->assertCreated();
        $id = $this->assertOutput($reader);
        $stored = $this->fixture->expectedStored('SUCCESS_A', $id, 2, 'Direct unique constraint control');
        unset($stored['id']);
        try {
            $reader->table('reviews')->insert($stored); // Constraint probe, not an alternative API workflow.
            $this->fail('Expected unique booking_id to reject the second row.');
        } catch (QueryException $error) {
            $this->assertSame(1062, $error->errorInfo[1]);
            $this->assertStringContainsString('reviews_booking_id_unique', $error->errorInfo[2]);
            $this->evidence('unique-constraint', ['driver_code' => 1062, 'booking_id' => $this->bookingId()]);
        }
        $this->assertSame($id, $this->assertOutput($reader));
        $this->assertOneCommittedLog($this->successLogs, $id);
    }
}
