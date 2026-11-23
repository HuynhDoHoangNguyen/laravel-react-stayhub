<?php

namespace Tests\Feature\HostReport;

use App\Models\Booking;
use App\Models\HostReport;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Mockery;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class HostReportEvidenceApiTest extends TestCase
{
    use DatabaseMigrations;

    private const PDF = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF\n";
    private const INPUT = ['reason' => 'WRONG_ADDRESS', 'description' => 'Địa chỉ trên đơn khác với cơ sở lưu trú.'];

    public function test_upload_and_download_preserve_bytes_without_exposing_path_or_original_name(): void
    {
        $private = Storage::fake('local');
        $public = Storage::fake('public');
        $booking = $this->booking();
        $response = $this->actingAs($booking->customer)->upload($booking, $this->pdf())->assertCreated()
            ->assertJsonMissingPath('data.evidence')->assertJsonMissingPath('data.evidence_path');
        $report = HostReport::query()->sole();
        $this->assertSame($report->id, $response->json('data.id'));
        $private->assertExists($report->evidence_path, self::PDF);
        $public->assertMissing($report->evidence_path);
        $this->assertCount(1, $private->allFiles());
        $this->assertContains($this->get('/storage/'.$report->evidence_path)->status(), [403, 404]);
        $download = "/api/admin/host-reports/{$report->id}/evidence";

        $this->getJson($download)->assertForbidden();
        $this->actingAs($booking->room->property->host)->getJson($download)->assertForbidden();
        auth()->forgetGuards();
        $this->getJson($download)->assertUnauthorized();
        $this->actingAs(User::factory()->admin()->blocked()->create())->getJson($download)->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())
            ->getJson("/api/admin/host-reports/{$report->id}")->assertOk()
            ->assertJsonPath('data.evidence.filename', 'chung-cu-bao-cao-'.$report->id.'.pdf')
            ->assertJsonPath('data.evidence.mime', 'application/pdf')
            ->assertJsonPath('data.evidence.size', strlen(self::PDF))
            ->assertJsonMissingPath('data.evidence_path')->assertDontSee('original-private.pdf');
        $file = $this->get($download)->assertOk()->assertDownload('chung-cu-bao-cao-'.$report->id.'.pdf')
            ->assertHeader('Content-Type', 'application/pdf')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', $file->headers->get('Cache-Control'));
        $this->assertSame(hash('sha256', self::PDF), hash('sha256', $file->streamedContent()));

        $wrongPath = str_replace('/'.$booking->id.'/', '/'.($booking->id + 1).'/', $report->evidence_path);
        $private->put($wrongPath, self::PDF);
        $report->update(['evidence_path' => $wrongPath]);
        $this->getJson($download)->assertNotFound();
    }

    public function test_no_evidence_or_missing_file_keeps_report_readable_and_download_returns_404(): void
    {
        $private = Storage::fake('local');
        $booking = $this->booking();
        $old = $this->booking();
        $this->actingAs($booking->customer)->upload($booking, $this->pdf())->assertCreated();
        $this->actingAs($old->customer)->postJson($this->customerUrl($old), self::INPUT)->assertCreated();
        $attached = HostReport::query()->where('booking_id', $booking->id)->sole();
        $without = HostReport::query()->where('booking_id', $old->id)->sole();
        $private->delete($attached->evidence_path);
        $this->actingAs(User::factory()->admin()->create())
            ->getJson("/api/admin/host-reports/{$without->id}")->assertOk()->assertJsonPath('data.evidence', null);
        $this->getJson("/api/admin/host-reports/{$attached->id}")->assertOk();
        foreach ([$attached->id, $without->id, 999999] as $id) {
            $this->getJson("/api/admin/host-reports/{$id}/evidence")->assertNotFound();
        }
    }

    public function test_invalid_files_and_foreign_bookings_cannot_write(): void
    {
        $private = Storage::fake('local');
        $booking = $this->booking();
        $this->actingAs(User::factory()->create())->upload($booking, $this->pdf())->assertNotFound();
        $this->actingAs($booking->customer);
        foreach ([
            UploadedFile::fake()->createWithContent('wrong.pdf', 'text only'),
            UploadedFile::fake()->createWithContent('wrong.jpg', self::PDF),
            UploadedFile::fake()->createWithContent('empty.pdf', ''),
            UploadedFile::fake()->createWithContent('large.pdf', self::PDF.str_repeat(' ', 2 * 1024 * 1024)),
        ] as $file) {
            $this->upload($booking, $file)->assertUnprocessable()->assertJsonValidationErrors('evidence');
        }
        $this->postJson($this->customerUrl($booking), self::INPUT + ['evidence' => '/private/foo.pdf'])
            ->assertUnprocessable()->assertJsonValidationErrors('evidence');
        $this->assertSame([], $private->allFiles());
        $this->assertDatabaseCount('host_reports', 0);
    }

    public function test_failed_database_write_leaves_no_file_and_allows_retry(): void
    {
        $private = Storage::fake('local');
        $booking = $this->booking();
        $fail = true;
        HostReport::created(static function () use (&$fail): void {
            if ($fail) throw new \RuntimeException('Injected report insert failure.');
        });
        $this->actingAs($booking->customer)->upload($booking, $this->pdf())->assertInternalServerError();
        $this->assertDatabaseCount('host_reports', 0);
        $this->assertSame([], $private->allFiles());
        $fail = false;
        $this->upload($booking, $this->pdf())->assertCreated();
        $this->upload($booking, $this->pdf())->assertConflict();
        $this->assertCount(1, $private->allFiles());
        $this->assertDatabaseCount('host_reports', 1);
    }

    public function test_partial_storage_failure_does_not_create_a_report_or_leave_a_file(): void
    {
        $private = Storage::fake('local');
        $booking = $this->booking();
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('putFileAs')->once()->andReturnUsing(function (string $directory, UploadedFile $file, string $name) use ($private): bool {
            $private->put($directory.'/'.$name, 'partial bytes');

            return false;
        });
        $disk->shouldReceive('delete')->once()->andReturnUsing(fn (string $path) => $private->delete($path));
        Storage::set('local', $disk);
        $this->actingAs($booking->customer)->upload($booking, $this->pdf())->assertInternalServerError();
        $this->assertDatabaseCount('host_reports', 0);
        $this->assertSame([], $private->allFiles());
    }

    public function test_mysql_concurrent_uploads_keep_one_committed_file(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Requires isolated MySQL and separate connections.');
        }
        $private = Storage::fake('local');
        $booking = $this->booking();
        $pdf = $this->pdf();
        $worker = <<<'PHP'
            require 'vendor/autoload.php';
            $app = require 'bootstrap/app.php';
            $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
            Tests\TestCase::validateDatabaseConfiguration(config('database.default'), DB::connection()->getConfig());
            if (DB::connection()->getDatabaseName() !== $argv[1]) exit(2);
            config(['filesystems.disks.local.root' => $argv[2]]);
            $owner = App\Models\User::query()->findOrFail($argv[3]);
            $file = new Illuminate\Http\UploadedFile($argv[5], 'evidence.pdf', null, null, true);
            try {
                $app->make(App\Services\HostReport\CustomerHostReportService::class)->submit($owner, (int) $argv[4], [
                    'reason' => 'WRONG_ADDRESS', 'description' => 'Địa chỉ trên đơn khác với cơ sở lưu trú.', 'evidence' => $file,
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
                $processes[$index] = new Process([
                    PHP_BINARY, '-r', $worker, '--', DB::connection()->getDatabaseName(), $private->path(''),
                    (string) $booking->customer_id, (string) $booking->id, $pdf->getPathname(),
                ], base_path(), timeout: 20);
                $processes[$index]->start();
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
            $results = [];
            foreach ($processes as $process) {
                $this->assertSame(0, $process->wait(), $process->getErrorOutput());
                $results[] = (int) trim($process->getOutput());
            }
            sort($results);
            $this->assertSame([201, 409], $results);
            $report = HostReport::query()->sole();
            $this->assertSame([$report->evidence_path], $private->allFiles());
            $private->assertExists($report->evidence_path, self::PDF);
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($processes as $process) {
                $process->stop();
            }
            $private->deleteDirectory('host-report-evidence');
        }
    }

    private function booking(): Booking
    {
        return Booking::factory()->confirmed()->create([
            'check_in_date' => today()->addDay()->toDateString(), 'check_out_date' => today()->addDays(3)->toDateString(),
        ]);
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('original-private.pdf', self::PDF);
    }

    private function upload(Booking $booking, UploadedFile $file): TestResponse
    {
        $upload = new UploadedFile($file->getPathname(), $file->getClientOriginalName(), $file->getClientMimeType(), null, true);

        return $this->post($this->customerUrl($booking), self::INPUT + ['evidence' => $upload], ['Accept' => 'application/json']);
    }

    private function customerUrl(Booking $booking): string
    {
        return "/api/customer/bookings/{$booking->id}/host-report";
    }
}
