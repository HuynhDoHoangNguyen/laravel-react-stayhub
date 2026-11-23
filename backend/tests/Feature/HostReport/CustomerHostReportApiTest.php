<?php

namespace Tests\Feature\HostReport;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\HostReport;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class CustomerHostReportApiTest extends TestCase
{
    use DatabaseMigrations;

    private const INPUT = [
        'reason' => 'OFF_PLATFORM_PAYMENT',
        'description' => 'Cơ sở yêu cầu chuyển khoản ngoài hệ thống.',
    ];

    public function test_customer_submits_once_and_can_read_confirmation_after_cancellation(): void
    {
        $this->travelTo(now('Asia/Ho_Chi_Minh')->setDate(2026, 11, 23));
        $booking = $this->booking();
        $url = $this->url($booking);
        $this->actingAs($booking->customer)->getJson($url)->assertOk()
            ->assertJsonPath('data.can_submit', true)
            ->assertJsonPath('data.deadline', '2026-12-26')
            ->assertJsonCount(3, 'data.reasons')->assertJsonPath('data.report', null);

        $response = $this->postJson($url, [
            'description' => '  '.self::INPUT['description'].'  ',
            'property_id' => 999, 'host_id' => 999, 'customer_id' => 999, 'status' => 'APPROVED',
        ] + self::INPUT)->assertCreated()->assertJsonPath('data.status', 'SUBMITTED');

        $report = HostReport::query()->sole();
        $this->assertDatabaseHas('host_reports', [
            'booking_id' => $booking->id,
            'customer_id' => $booking->customer_id,
            'property_id' => $booking->room->property_id,
            'host_id' => $booking->room->property->host_id,
            'status' => 'SUBMITTED',
        ] + self::INPUT);
        $receipt = ['id' => $report->id, 'status' => 'SUBMITTED', 'submitted_at' => $report->created_at->utc()->toIso8601ZuluString()];
        $response->assertExactJson(['data' => $receipt]);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->postJson($url, self::INPUT)->assertConflict();
        $this->assertSame(BookingStatus::CONFIRMED, $booking->fresh()->status);
        $booking->update(['status' => BookingStatus::CANCELLED]);
        $this->getJson($url)->assertOk()->assertExactJson(['data' => [
            'can_submit' => false, 'blocked_reason' => 'ALREADY_SUBMITTED', 'deadline' => '2026-12-26',
            'reasons' => [], 'report' => $receipt,
        ]]);
    }

    public function test_hides_other_customers_orders_and_denies_inactive_or_wrong_roles(): void
    {
        $booking = $this->booking();
        $url = $this->url($booking);
        $this->getJson($url)->assertUnauthorized();
        $this->postJson($url)->assertUnauthorized();
        foreach ([User::factory()->host()->create(), User::factory()->admin()->create(), User::factory()->blocked()->create()] as $actor) {
            $this->actingAs($actor)->getJson($url)->assertForbidden();
            $this->actingAs($actor)->postJson($url)->assertForbidden();
        }
        $this->actingAs(User::factory()->customer()->create());
        $foreign = $this->getJson($url)->assertNotFound()->json();
        $this->getJson('/api/customer/bookings/999999/host-report')->assertNotFound()->assertExactJson($foreign);
        $this->postJson($url)->assertNotFound()->assertExactJson($foreign);
        $this->assertDatabaseCount('host_reports', 0);
    }

    public function test_only_supported_booking_statuses_and_reasons_are_eligible(): void
    {
        $this->travelTo(now('Asia/Ho_Chi_Minh')->setDate(2026, 11, 23));
        $booking = $this->booking();
        $url = $this->url($booking);
        $this->actingAs($booking->customer);
        $input = ['reason' => 'CHECK_IN_REFUSED'] + self::INPUT;
        $this->postJson($url, $input)->assertUnprocessable()->assertJsonValidationErrors('reason');

        foreach ([BookingStatus::PENDING, BookingStatus::CANCELLED, BookingStatus::REJECTED] as $status) {
            $booking->update(['status' => $status]);
            $this->getJson($url)->assertOk()->assertJsonPath('data.blocked_reason', 'BOOKING_STATUS')->assertJsonCount(0, 'data.reasons');
            $this->postJson($url, self::INPUT)->assertConflict();
        }
        foreach ([BookingStatus::CHECKED_IN, BookingStatus::COMPLETED] as $status) {
            $booking->update(['status' => $status]);
            $this->getJson($url)->assertOk()->assertJsonPath('data.can_submit', true)->assertJsonCount(4, 'data.reasons');
        }

        $booking->update(['status' => BookingStatus::CONFIRMED]);
        $this->travelTo(now('Asia/Ho_Chi_Minh')->setDate(2026, 11, 24)->startOfDay());
        $this->getJson($url)->assertOk()->assertJsonCount(4, 'data.reasons');
        $this->postJson($url, $input)->assertCreated();
    }

    public function test_deadline_includes_last_second_in_vietnam_and_excludes_next_midnight(): void
    {
        $booking = $this->booking(BookingStatus::COMPLETED);
        $lateBooking = $this->booking(BookingStatus::COMPLETED);
        $this->travelTo(now('UTC')->setDate(2026, 12, 26)->setTime(16, 59, 59));
        $this->actingAs($booking->customer)->getJson($this->url($booking))->assertOk()
            ->assertJsonPath('data.deadline', '2026-12-26')->assertJsonPath('data.can_submit', true);
        $this->postJson($this->url($booking), self::INPUT)->assertCreated();

        $this->travelTo(now('UTC')->setDate(2026, 12, 26)->setTime(17, 0, 0));
        $this->actingAs($lateBooking->customer)->getJson($this->url($lateBooking))->assertOk()
            ->assertJsonPath('data.blocked_reason', 'DEADLINE_PASSED');
        $this->postJson($this->url($lateBooking), self::INPUT)->assertConflict();
        $this->assertDatabaseCount('host_reports', 1);
    }

    public function test_validation_and_throttling_do_not_create_reports(): void
    {
        $this->travelTo(now('Asia/Ho_Chi_Minh')->setDate(2026, 11, 23));
        $booking = $this->booking();
        $url = $this->url($booking);
        $this->actingAs($booking->customer);
        $this->postJson($url)->assertUnprocessable()->assertJsonValidationErrors(['reason', 'description']);
        $this->postJson($url, ['reason' => ['WRONG_ADDRESS']] + self::INPUT)->assertUnprocessable()->assertJsonValidationErrors('reason');
        foreach ([str_repeat('ả', 19), str_repeat('ả', 1001), "\u{00a0}".str_repeat(' ', 30)] as $description) {
            $this->postJson($url, ['description' => $description] + self::INPUT)->assertUnprocessable()->assertJsonValidationErrors('description');
        }
        $this->postJson($url, self::INPUT)->assertTooManyRequests()->assertHeader('Retry-After');
        $this->getJson($url)->assertOk()->assertJsonPath('data.can_submit', true);
        $this->assertDatabaseCount('host_reports', 0);
    }

    public function test_failure_after_insert_rolls_back_and_customer_can_retry(): void
    {
        $this->travelTo(now('Asia/Ho_Chi_Minh')->setDate(2026, 11, 23));
        $booking = $this->booking();
        $fail = true;
        HostReport::created(static function () use (&$fail): void {
            if ($fail) {
                throw new RuntimeException('Injected Host report failure.');
            }
        });
        $this->actingAs($booking->customer)->postJson($this->url($booking), self::INPUT)->assertInternalServerError();
        $this->assertDatabaseCount('host_reports', 0);
        $fail = false;
        $this->postJson($this->url($booking), ['description' => str_repeat('ả', 1000)] + self::INPUT)->assertCreated();
        $this->assertDatabaseCount('host_reports', 1);
    }

    public function test_two_mysql_connections_only_submit_one_report(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Requires isolated MySQL and separate connections.');
        }

        $booking = $this->booking();
        $worker = <<<'PHP'
            require 'vendor/autoload.php';
            $app = require 'bootstrap/app.php';
            $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
            Tests\TestCase::validateDatabaseConfiguration(config('database.default'), DB::connection()->getConfig());
            if (DB::connection()->getDatabaseName() !== $argv[1]) exit(2);
            Illuminate\Support\Carbon::setTestNow(Illuminate\Support\Carbon::parse('2026-11-23 10:00:00', 'Asia/Ho_Chi_Minh'));
            $owner = App\Models\User::query()->findOrFail($argv[2]);
            try {
                $app->make(App\Services\HostReport\CustomerHostReportService::class)->submit($owner, (int) $argv[3], [
                    'reason' => 'WRONG_ADDRESS', 'description' => 'Địa chỉ ghi trên đơn không đúng với nơi lưu trú.',
                ]);
                echo '201';
            } catch (Symfony\Component\HttpKernel\Exception\ConflictHttpException) {
                echo '409';
            }
            PHP;
        $processes = [];
        DB::beginTransaction();
        try {
            Booking::query()->lockForUpdate()->findOrFail($booking->id);
            for ($index = 0; $index < 2; $index++) {
                $process = new Process([
                    PHP_BINARY, '-r', $worker, '--', DB::connection()->getDatabaseName(),
                    (string) $booking->customer_id, (string) $booking->id,
                ], base_path(), timeout: 20);
                $processes[] = $process;
                $process->start();
            }
            $deadline = microtime(true) + 10;
            do {
                $waiting = collect(DB::select('SHOW FULL PROCESSLIST'))->filter(static function (object $row): bool {
                    $sql = strtolower((string) $row->Info);

                    return str_contains($sql, 'from `bookings`') && str_ends_with($sql, 'for update');
                })->count();
                if ($waiting < 2) {
                    usleep(20_000);
                }
            } while ($waiting < 2 && microtime(true) < $deadline);
            $this->assertSame(2, $waiting, $processes[0]->getErrorOutput().$processes[1]->getErrorOutput());
            DB::commit();

            $statuses = [];
            foreach ($processes as $process) {
                $this->assertSame(0, $process->wait(), $process->getErrorOutput());
                $statuses[] = (int) trim($process->getOutput());
            }
            sort($statuses);
            $this->assertSame([201, 409], $statuses);
            $this->assertDatabaseCount('host_reports', 1);
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($processes as $process) {
                $process->stop();
            }
        }
    }

    private function booking(BookingStatus $status = BookingStatus::CONFIRMED): Booking
    {
        return Booking::factory()->create([
            'check_in_date' => '2026-11-24', 'check_out_date' => '2026-11-26', 'status' => $status,
        ]);
    }

    private function url(Booking $booking): string
    {
        return "/api/customer/bookings/{$booking->id}/host-report";
    }
}
