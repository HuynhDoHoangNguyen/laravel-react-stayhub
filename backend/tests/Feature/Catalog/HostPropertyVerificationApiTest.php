<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Enums\PropertyStatus;
use App\Models\Property;
use App\Models\PropertyVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class HostPropertyVerificationApiTest extends TestCase
{
    use DatabaseMigrations;

    protected function beforeRefreshingDatabase(): void
    {
        parent::beforeRefreshingDatabase();
    }

    public function test_host_submits_and_reads_private_record_without_changing_public_catalog(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $host = User::factory()->host()->create();
        $property = Property::factory()->for($host, 'host')->create([
            'address' => '12 Bạch Đằng', 'status' => PropertyStatus::ACTIVE,
        ]);
        $other = Property::factory()->for($host, 'host')->create();
        $publicUrl = '/api/public/properties/'.$property->id;
        $publicBefore = $this->getJson($publicUrl)->assertOk()->json();
        $this->actingAs($host)->getJson($this->url($property))->assertOk()->assertExactJson(['data' => null]);

        $response = $this->post($this->url($property), ['document' => $this->document()], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('data.status', 'SUBMITTED')->assertJsonPath('data.address_snapshot', '12 Bạch Đằng');
        $record = PropertyVerification::query()->sole();
        $body = ['data' => [
            'id' => $record->id, 'status' => 'SUBMITTED', 'submitted_at' => $record->submitted_at->utc()->toIso8601ZuluString(),
            'address_snapshot' => '12 Bạch Đằng', 'reviewed_at' => null, 'rejection_reason' => null,
        ]];
        $response->assertExactJson($body);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame($host->id, (int) $record->submitted_by);
        $this->assertSame('application/pdf', $record->document_mime);
        $this->assertGreaterThan(0, $record->document_size);
        $this->assertMatchesRegularExpression('~^property-verifications/\d+/[a-f0-9-]{36}\.pdf$~', $record->document_path);
        Storage::disk('local')->assertExists($record->document_path);
        Storage::disk('public')->assertMissing($record->document_path);
        $this->assertContains($this->get('/storage/'.$record->document_path)->status(), [403, 404]);
        $this->getJson($this->url($property))->assertOk()->assertExactJson($body);
        $this->getJson($this->url($other))->assertOk()->assertExactJson(['data' => null]);
        $this->getJson($publicUrl)->assertOk()->assertExactJson($publicBefore);
        $this->assertSame(PropertyStatus::ACTIVE, $property->fresh()->status);

        $this->post($this->url($property), ['document' => $this->document()], ['Accept' => 'application/json'])->assertConflict();
        $this->assertDatabaseCount('property_verifications', 1);
        $this->assertSame([$record->document_path], Storage::disk('local')->allFiles());
    }

    public function test_permissions_and_ownership_precede_input_validation(): void
    {
        $property = Property::factory()->create();
        $url = $this->url($property);
        $this->getJson($url)->assertUnauthorized();
        $this->postJson($url)->assertUnauthorized();

        foreach ([User::factory()->create(), User::factory()->admin()->create()] as $actor) {
            $this->actingAs($actor)->getJson($url)->assertForbidden();
            $this->postJson($url)->assertForbidden();
        }
        $this->actingAs(User::factory()->host()->create())->getJson($url)->assertNotFound();
        $this->postJson($url)->assertNotFound();

        $agreementId = DB::table('partner_agreements')->insertGetId([
            'version' => 'PROP02-test', 'title' => 'Demo', 'terms_body' => 'Demo',
            'content_sha256' => hash('sha256', 'Demo'), 'effective_at' => now(),
        ]);
        DB::table('host_agreement_acceptances')->insert([
            'host_id' => $property->host_id, 'partner_agreement_id' => $agreementId,
            'accepted_content_sha256' => hash('sha256', 'Demo'), 'accepted_at' => now(), 'method' => 'REGISTRATION_PENDING',
        ]);
        $this->actingAs($property->host)->getJson($url)->assertForbidden()->assertJsonPath('code', 'HOST_APPROVAL_PENDING');
        $this->postJson($url)->assertForbidden()->assertJsonPath('code', 'HOST_APPROVAL_PENDING');
    }

    public function test_rejects_missing_mismatched_empty_and_oversized_files(): void
    {
        Storage::fake('local');
        $property = Property::factory()->create();
        $this->actingAs($property->host)->postJson($this->url($property))->assertUnprocessable()->assertJsonValidationErrors('document');

        $invalid = [
            UploadedFile::fake()->createWithContent('fake.pdf', 'plain text'),
            UploadedFile::fake()->createWithContent('mismatched.jpg', "%PDF-1.4\n%%EOF"),
            UploadedFile::fake()->createWithContent('empty.pdf', ''),
            UploadedFile::fake()->createWithContent('too-large.pdf', '%PDF-1.4'.str_repeat(' ', 2 * 1024 * 1024)),
        ];
        foreach ($invalid as $file) {
            $real = new UploadedFile($file->getPathname(), $file->getClientOriginalName(), 'application/pdf', UPLOAD_ERR_OK, true);
            $this->post($this->url($property), ['document' => $real], ['Accept' => 'application/json'])
                ->assertUnprocessable()->assertJsonValidationErrors('document');
        }
        $this->assertDatabaseCount('property_verifications', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_partial_storage_failure_leaves_no_row_or_file(): void
    {
        $property = Property::factory()->create();
        $disk = Storage::fake('local');
        $failedDisk = \Mockery::mock(\Illuminate\Filesystem\FilesystemAdapter::class);
        $failedDisk->shouldReceive('putFileAs')->once()->andReturnUsing(function ($directory, $document, $filename) use ($disk): bool {
            $disk->put($directory.'/'.$filename, 'partial test file');

            return false;
        });
        $failedDisk->shouldReceive('delete')->once()->andReturnUsing(fn (string $path) => $disk->delete($path));
        Storage::set('local', $failedDisk);

        $this->actingAs($property->host)->post($this->url($property), ['document' => $this->document()], ['Accept' => 'application/json'])
            ->assertInternalServerError();
        $this->assertDatabaseCount('property_verifications', 0);
        $this->assertSame([], $disk->allFiles());
    }

    public function test_db_failure_rolls_back_row_removes_file_and_allows_retry(): void
    {
        $disk = Storage::fake('local');
        $property = Property::factory()->create(['status' => PropertyStatus::PENDING]);
        $fail = true;
        PropertyVerification::created(static function () use (&$fail): void {
            if ($fail) {
                throw new RuntimeException('Injected verification write failure.');
            }
        });
        $this->actingAs($property->host)
            ->post($this->url($property), ['document' => $this->document()], ['Accept' => 'application/json'])
            ->assertInternalServerError();
        $this->assertDatabaseCount('property_verifications', 0);
        $this->assertSame([], $disk->allFiles());

        $fail = false;
        $this->post($this->url($property), ['document' => $this->document()], ['Accept' => 'application/json'])->assertCreated();
        $this->assertSame(PropertyStatus::PENDING, $property->fresh()->status);
        $this->assertCount(1, $disk->allFiles());
    }

    public function test_two_mysql_submissions_create_one_row_and_one_private_file(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Requires the isolated MySQL database.');
        }

        $property = Property::factory()->create();
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'stayhub-prop02-'.bin2hex(random_bytes(16));
        File::makeDirectory($directory);
        $fixture = $this->document();
        $worker = <<<'PHP'
            require 'vendor/autoload.php';
            $app = require 'bootstrap/app.php';
            $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
            Tests\TestCase::validateDatabaseConfiguration(config('database.default'), DB::connection()->getConfig());
            if (DB::connection()->getDatabaseName() !== $argv[1]) exit(2);
            config(['filesystems.disks.local.root' => $argv[2].'/private']);
            $host = App\Models\User::query()->findOrFail((int) $argv[3]);
            $file = new Illuminate\Http\UploadedFile($argv[5], 'demo.pdf', 'application/pdf', UPLOAD_ERR_OK, true);
            $input = $app->make(App\Validators\Catalog\HostPropertyVerificationValidator::class)->validate(['document' => $file]);
            file_put_contents($argv[2].'/ready-'.$argv[6], 'ready');
            try {
                $app->make(App\Services\Catalog\HostPropertyVerificationService::class)->submit($host, (int) $argv[4], $input);
                echo "201\n";
            } catch (Symfony\Component\HttpKernel\Exception\ConflictHttpException) {
                echo "409\n";
            }
            PHP;

        $workerPath = $directory.DIRECTORY_SEPARATOR.'worker.php';
        File::put($workerPath, "<?php\n".$worker);
        $attempts = [];
        DB::beginTransaction();
        try {
            Property::query()->lockForUpdate()->findOrFail($property->id);
            for ($index = 0; $index < 2; $index++) {
                $process = new Process([
                    PHP_BINARY, $workerPath, DB::connection()->getDatabaseName(), $directory,
                    (string) $property->host_id, (string) $property->id, $fixture->getPathname(), (string) $index,
                ], base_path(), timeout: 20);
                $attempts[] = $process;
                $process->start();
            }
            $deadline = microtime(true) + 10;
            foreach ($attempts as $index => $process) {
                $marker = $directory.DIRECTORY_SEPARATOR.'ready-'.$index;
                while (!is_file($marker) && $process->isRunning() && microtime(true) < $deadline) {
                    usleep(20_000);
                }
                $this->assertFileExists($marker, $process->getErrorOutput());
            }
            $deadline = microtime(true) + 5;
            do {
                $waiting = collect(DB::select('SHOW FULL PROCESSLIST'))->filter(static function (object $row): bool {
                    $sql = strtolower((string) $row->Info);

                    return str_contains($sql, 'from `properties`') && str_ends_with($sql, 'for update');
                })->count();
                if ($waiting < 2) {
                    usleep(20_000);
                }
            } while ($waiting < 2 && microtime(true) < $deadline);
            $this->assertSame(2, $waiting, 'Both submissions must reach the property lock on separate connections.');
            DB::commit();

            $statuses = [];
            foreach ($attempts as $process) {
                $this->assertSame(0, $process->wait(), $process->getErrorOutput());
                $statuses[] = (int) trim($process->getOutput());
            }
            sort($statuses);
            $this->assertSame([201, 409], $statuses);
            $record = PropertyVerification::query()->sole();
            $disk = Storage::build(['driver' => 'local', 'root' => $directory.'/private']);
            $this->assertSame([$record->document_path], $disk->allFiles());
        } finally {
            if (DB::transactionLevel() !== 0) {
                DB::rollBack();
            }
            foreach ($attempts as $process) {
                $process->stop(1);
            }
            File::deleteDirectory($directory);
        }
    }

    private function url(Property $property): string
    {
        return '/api/host/properties/'.$property->id.'/verification';
    }

    private function document(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('demo.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF");
    }
}
