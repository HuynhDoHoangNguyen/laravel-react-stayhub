<?php

namespace Tests\Feature\HostReport;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\HostReport;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class AdminHostReportApiTest extends TestCase
{
    use DatabaseMigrations;

    private const BASE = '/api/admin/host-reports';
    private const INPUT = [
        'decision' => HostReport::SUBSTANTIATED,
        'note' => 'Đã đối chiếu thông tin của đơn.',
    ];

    public function test_list_defaults_to_queue_and_filters_by_status_and_booking_with_stable_pagination(): void
    {
        $this->freezeSecond();
        $queue = $this->report('BK-QUEUE-FIRST');
        for ($index = 1; $index <= 10; $index++) {
            $latest = $this->report('BK-QUEUE-'.$index);
        }
        $closed = $this->report('BK-CLOSED');
        $this->actingAs(User::factory()->admin()->create())
            ->patchJson($this->decisionUrl($closed), self::INPUT)->assertOk();

        $this->getJson(self::BASE)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonCount(10, 'data')->assertJsonPath('meta.total', 11)->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('data.0.id', $latest->id)->assertJsonMissingPath('data.0.description');
        $this->getJson(self::BASE.'?page=2')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $queue->id);
        $this->getJson(self::BASE.'?status=ALL&booking_code=bk-closed')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $closed->id);
        $this->getJson(self::BASE.'?status=SUBSTANTIATED')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonMissingPath('data.0.review_note');
        $this->getJson(self::BASE.'?booking_code=%25')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson(self::BASE.'?page=0&status=wrong')->assertUnprocessable()->assertJsonValidationErrors(['page', 'status']);
    }

    public function test_admin_reviews_both_outcomes_and_customer_receives_only_original_receipt(): void
    {
        $first = $this->report('BK-RPT-FIRST');
        $second = $this->report('BK-RPT-SECOND');
        $admin = User::factory()->admin()->create();
        $customerUrl = "/api/customer/bookings/{$first->booking_id}/host-report";
        $before = $this->actingAs($first->customer)->getJson($customerUrl)->assertOk()->json();

        $this->actingAs($admin)->getJson(self::BASE.'/'.$first->id)->assertOk()
            ->assertJsonPath('data.booking_code', 'BK-RPT-FIRST')->assertJsonPath('data.description', $first->description)
            ->assertJsonPath('data.host_name', $first->host->name)->assertJsonPath('data.review_note', null)
            ->assertJsonMissingPath('data.email');
        $this->patchJson($this->decisionUrl($first), [
            'note' => '  '.self::INPUT['note'].'  ',
            'reviewed_by' => 999,
        ] + self::INPUT)->assertOk()
            ->assertJsonPath('data.status', HostReport::SUBSTANTIATED)
            ->assertJsonPath('data.reviewed_by', $admin->id)->assertJsonPath('data.reviewer_name', $admin->name)
            ->assertJsonPath('data.review_note', self::INPUT['note']);
        $this->patchJson($this->decisionUrl($first), ['decision' => HostReport::UNSUBSTANTIATED] + self::INPUT)
            ->assertConflict()->assertJsonPath('code', 'HOST_REPORT_ALREADY_REVIEWED');
        $this->assertDatabaseHas('host_reports', ['id' => $first->id, 'status' => HostReport::SUBSTANTIATED, 'reviewed_by' => $admin->id, 'review_note' => self::INPUT['note']]);
        $this->patchJson($this->decisionUrl($second), ['decision' => HostReport::UNSUBSTANTIATED] + self::INPUT)
            ->assertOk()->assertJsonPath('data.status', HostReport::UNSUBSTANTIATED);
        $this->assertNotNull($first->fresh()->reviewed_at);
        $this->assertSame(BookingStatus::CONFIRMED, $first->booking->fresh()->status);
        $this->actingAs($first->customer)->getJson($customerUrl)->assertOk()->assertExactJson($before);
    }

    public function test_permissions_validation_and_unknown_report(): void
    {
        $report = $this->report();
        $detail = self::BASE.'/'.$report->id;
        $patch = $this->decisionUrl($report);
        $this->getJson(self::BASE)->assertUnauthorized();
        $this->getJson($detail)->assertUnauthorized();
        $this->patchJson($patch, self::INPUT)->assertUnauthorized();
        foreach ([User::factory()->host()->create(), $report->customer, User::factory()->admin()->blocked()->create()] as $actor) {
            $this->actingAs($actor)->getJson(self::BASE)->assertForbidden();
            $this->getJson($detail)->assertForbidden();
            $this->patchJson($patch, self::INPUT)->assertForbidden();
        }
        $this->actingAs(User::factory()->admin()->create());
        $this->getJson(self::BASE.'/999999')->assertNotFound();
        $this->patchJson(self::BASE.'/999999/decision', self::INPUT)->assertNotFound();
        $this->patchJson($patch, ['decision' => 'SUBMITTED', 'note' => '  '])->assertUnprocessable()
            ->assertJsonValidationErrors(['decision', 'note']);
        $this->patchJson($patch, ['note' => str_repeat('ả', 501)] + self::INPUT)->assertUnprocessable()
            ->assertJsonValidationErrors('note');
        $this->assertDatabaseHas('host_reports', ['id' => $report->id, 'status' => HostReport::SUBMITTED, 'reviewed_by' => null]);
    }

    public function test_failed_update_rolls_back_all_columns_and_allows_retry(): void
    {
        $report = $this->report();
        $fail = true;
        HostReport::updated(static function () use (&$fail): void {
            if ($fail) {
                throw new \RuntimeException('Injected reviewer update failure.');
            }
        });
        $this->actingAs(User::factory()->admin()->create())
            ->patchJson($this->decisionUrl($report), self::INPUT)->assertInternalServerError();
        $this->assertDatabaseHas('host_reports', [
            'id' => $report->id, 'status' => HostReport::SUBMITTED, 'reviewed_by' => null, 'reviewed_at' => null, 'review_note' => null,
        ]);
        $fail = false;
        $this->patchJson($this->decisionUrl($report), self::INPUT)->assertOk();
    }

    public function test_mysql_concurrent_review_keeps_the_winning_decision_and_audit_fields(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Requires isolated MySQL and separate connections.');
        }
        $report = $this->report();
        $admins = User::factory()->admin()->count(2)->create();
        $worker = <<<'PHP'
            require 'vendor/autoload.php';
            $app = require 'bootstrap/app.php';
            $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
            Tests\TestCase::validateDatabaseConfiguration(config('database.default'), DB::connection()->getConfig());
            if (DB::connection()->getDatabaseName() !== $argv[1]) exit(2);
            $admin = App\Models\User::query()->findOrFail($argv[2]);
            try {
                $app->make(App\Services\HostReport\AdminHostReportService::class)->decide(
                    $admin, (int) $argv[3], new App\DTOs\HostReport\Requests\ReviewHostReportRequest($argv[4], $argv[5]),
                );
                echo '200';
            } catch (Symfony\Component\HttpKernel\Exception\ConflictHttpException) {
                echo '409';
            }
            PHP;
        $processes = [];
        DB::beginTransaction();
        try {
            HostReport::query()->lockForUpdate()->findOrFail($report->id);
            foreach ($admins as $index => $admin) {
                $processes[$index] = new Process([
                    PHP_BINARY, '-r', $worker, '--', DB::connection()->getDatabaseName(),
                    (string) $admin->id, (string) $report->id, HostReport::DECISIONS[$index], 'Review note '.$index,
                ], base_path(), timeout: 20);
                $processes[$index]->start();
            }
            $deadline = microtime(true) + 10;
            do {
                $waiting = collect(DB::select('SHOW FULL PROCESSLIST'))->filter(static function (object $row): bool {
                    $sql = strtolower((string) $row->Info);

                    return str_contains($sql, 'from `host_reports`') && str_ends_with($sql, 'for update');
                })->count();
                if ($waiting < 2) {
                    usleep(20_000);
                }
            } while ($waiting < 2 && microtime(true) < $deadline);
            $this->assertSame(2, $waiting, $processes[0]->getErrorOutput().$processes[1]->getErrorOutput());
            DB::commit();

            $statuses = [];
            foreach ($processes as $index => $process) {
                $this->assertSame(0, $process->wait(), $process->getErrorOutput());
                $statuses[$index] = (int) trim($process->getOutput());
            }
            $winner = array_search(200, $statuses, true);
            sort($statuses);
            $this->assertSame([200, 409], $statuses);
            $this->assertDatabaseHas('host_reports', [
                'id' => $report->id, 'reviewed_by' => $admins[$winner]->id, 'status' => HostReport::DECISIONS[$winner],
                'review_note' => 'Review note '.$winner,
            ]);
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($processes as $process) {
                $process->stop();
            }
        }
    }

    private function report(?string $code = null): HostReport
    {
        $booking = Booking::factory()->confirmed()->create($code ? ['booking_code' => $code] : []);
        $property = $booking->room->property;

        return HostReport::query()->create([
            'booking_id' => $booking->id, 'customer_id' => $booking->customer_id,
            'host_id' => $property->host_id, 'property_id' => $property->id,
            'reason' => 'WRONG_ADDRESS', 'description' => 'Địa chỉ ghi trên đơn không đúng với nơi lưu trú.',
        ])->refresh();
    }

    private function decisionUrl(HostReport $report): string
    {
        return self::BASE.'/'.$report->id.'/decision';
    }
}
