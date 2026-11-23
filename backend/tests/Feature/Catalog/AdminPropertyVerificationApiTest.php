<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Enums\PropertyStatus;
use App\Models\Property;
use App\Models\PropertyVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class AdminPropertyVerificationApiTest extends TestCase
{
    use DatabaseMigrations;

    protected function beforeRefreshingDatabase(): void
    {
        parent::beforeRefreshingDatabase();
    }

    public function test_only_admin_can_read_and_download_the_requested_private_file(): void
    {
        $disk = Storage::fake('local');
        $entry = $this->entry();
        $other = $this->entry();
        $empty = Property::factory()->create();
        $disk->put($entry->document_path, 'correct attachment');
        $disk->put($other->document_path, 'other attachment');
        $url = $this->url($entry->property_id);
        $this->getJson($url)->assertUnauthorized();
        $this->getJson($url.'/document')->assertUnauthorized();
        $this->patchJson($url)->assertUnauthorized();

        foreach ([User::findOrFail($entry->submitted_by), User::factory()->create(), User::factory()->admin()->create(['status' => 'BLOCKED'])] as $actor) {
            $this->actingAs($actor)->getJson($url)->assertForbidden();
            $this->getJson($url.'/document')->assertForbidden();
            $this->patchJson($url)->assertForbidden();
        }

        $this->actingAs(User::factory()->admin()->create());
        $this->getJson($this->url($empty->id))->assertExactJson(['data' => null]);
        $this->getJson($this->url($empty->id).'/document')->assertNotFound();
        $this->getJson($url)->assertOk()->assertExactJson(['data' => [
            'id' => $entry->id, 'status' => 'SUBMITTED', 'address_snapshot' => '12 Bạch Đằng',
            'address_current' => '12 Bạch Đằng', 'submitted_at' => $entry->submitted_at->utc()->toIso8601ZuluString(),
            'reviewed_at' => null, 'rejection_reason' => null,
        ]]);
        $download = $this->get($url.'/document')->assertOk()->assertDownload('ho-so-xac-minh-'.$entry->id.'.pdf')
            ->assertHeader('Content-Type', 'application/pdf')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', $download->headers->get('Cache-Control'));
        $this->assertSame('correct attachment', $download->streamedContent());

        $entry->update(['document_path' => $other->document_path.'.copy']);
        $disk->put($entry->document_path, 'unrelated private file');
        $this->getJson($url.'/document')->assertNotFound();
    }

    public function test_approve_persists_admin_and_host_results_without_catalog_changes(): void
    {
        $disk = Storage::fake('local');
        $entry = $this->entry();
        $disk->put($entry->document_path, 'fixture');
        $admin = User::factory()->admin()->create();
        $url = $this->url($entry->property_id);
        $publicUrl = '/api/public/properties/'.$entry->property_id;
        $before = $this->getJson($publicUrl)->assertOk()->json();

        $result = $this->actingAs($admin)->patchJson($url, ['decision' => 'APPROVE'])
            ->assertOk()->assertJsonPath('data.status', 'APPROVED')->assertJsonPath('data.rejection_reason', null);
        $this->assertNotNull($result->json('data.reviewed_at'));
        $this->assertEquals($admin->id, $entry->fresh()->reviewed_by);
        $this->getJson($url)->assertExactJson($result->json());
        $this->patchJson($url, ['decision' => 'REJECT', 'reason' => 'Decided already'])->assertConflict();
        $this->assertFalse($before['data']['is_verified']);
        $before['data']['is_verified'] = true;
        $this->getJson($publicUrl)->assertExactJson($before);
        $this->assertSame(PropertyStatus::ACTIVE, Property::findOrFail($entry->property_id)->status);
        $this->actingAs(User::findOrFail($entry->submitted_by))
            ->getJson('/api/host/properties/'.$entry->property_id.'/verification')->assertOk()
            ->assertJsonPath('data.status', 'APPROVED')->assertJsonMissingPath('data.reviewed_by')
            ->assertJsonPath('data.reviewed_at', $result->json('data.reviewed_at'));
    }

    public function test_reject_requires_a_reason_and_host_sees_the_trimmed_result(): void
    {
        $entry = $this->entry();
        $url = $this->url($entry->property_id);
        $this->actingAs(User::factory()->admin()->create());
        foreach (['', '  ', null, ['x'], str_repeat('ệ', 501)] as $reason) {
            $this->patchJson($url, ['decision' => 'REJECT', 'reason' => $reason])->assertUnprocessable()
                ->assertJsonValidationErrors('reason');
        }
        $this->patchJson($url, ['decision' => ['APPROVE']])->assertUnprocessable()->assertJsonValidationErrors('decision');
        $this->assertSame('SUBMITTED', $entry->fresh()->status);
        $this->patchJson($url, ['decision' => 'REJECT', 'reason' => '  Thiếu địa chỉ.  '])
            ->assertOk()->assertJsonPath('data.status', 'REJECTED')->assertJsonPath('data.rejection_reason', 'Thiếu địa chỉ.');
        $this->actingAs(User::findOrFail($entry->submitted_by))
            ->getJson('/api/host/properties/'.$entry->property_id.'/verification')->assertOk()
            ->assertJsonPath('data.status', 'REJECTED')->assertJsonPath('data.rejection_reason', 'Thiếu địa chỉ.');
    }

    public function test_changed_address_and_missing_document_block_approval(): void
    {
        $disk = Storage::fake('local');
        $entry = $this->entry();
        $url = $this->url($entry->property_id);
        $disk->put($entry->document_path, 'fixture');
        $this->actingAs(User::factory()->admin()->create());
        Property::findOrFail($entry->property_id)->update(['address' => '99 Địa chỉ mới']);
        $this->patchJson($url, ['decision' => 'APPROVE'])->assertConflict()
            ->assertJsonPath('message', 'Địa chỉ cơ sở đã thay đổi kể từ khi gửi hồ sơ.');

        Property::findOrFail($entry->property_id)->update(['address' => $entry->address_snapshot]);
        $disk->delete($entry->document_path);
        $this->getJson($url.'/document')->assertNotFound();
        $this->patchJson($url, ['decision' => 'APPROVE'])->assertConflict()
            ->assertJsonPath('message', 'Tệp hồ sơ bị thiếu. Không thể duyệt hồ sơ này.');
        $this->assertSame('SUBMITTED', $entry->fresh()->status);
        $this->assertNull($entry->fresh()->reviewed_by);
        $this->patchJson($url, ['decision' => 'REJECT', 'reason' => 'Thiếu tệp.'])->assertOk();
    }

    public function test_two_mysql_admins_cannot_overwrite_each_others_decision(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Requires the isolated MySQL database.');
        }

        $entry = $this->entry();
        $admins = User::factory()->admin()->count(2)->create();
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'stayhub-prop03-'.bin2hex(random_bytes(16));
        File::makeDirectory($directory);
        $worker = <<<'PHP'
            <?php
            require 'vendor/autoload.php';
            $app = require 'bootstrap/app.php';
            $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
            Tests\TestCase::validateDatabaseConfiguration(config('database.default'), DB::connection()->getConfig());
            if (DB::connection()->getDatabaseName() !== $argv[1]) exit(2);
            $admin = App\Models\User::findOrFail((int) $argv[2]);
            $input = new App\DTOs\Catalog\Requests\ReviewPropertyVerificationRequest(false, 'Reason '.$admin->id);
            try {
                $app->make(App\Services\Catalog\AdminPropertyVerificationService::class)->review($admin, (int) $argv[3], $input);
                echo '200';
            } catch (Symfony\Component\HttpKernel\Exception\ConflictHttpException) {
                echo '409';
            }
            PHP;
        $workerPath = $directory.DIRECTORY_SEPARATOR.'worker.php';
        File::put($workerPath, $worker);
        $processes = [];
        DB::beginTransaction();
        try {
            Property::query()->lockForUpdate()->findOrFail($entry->property_id);
            foreach ($admins as $admin) {
                $process = new Process([
                    PHP_BINARY, $workerPath, DB::connection()->getDatabaseName(), (string) $admin->id, (string) $entry->property_id,
                ], base_path(), timeout: 20);
                $processes[$admin->id] = $process;
                $process->start();
            }
            $deadline = microtime(true) + 8;
            do {
                $waiting = collect(DB::select('SHOW FULL PROCESSLIST'))->filter(static function (object $row): bool {
                    $sql = strtolower((string) $row->Info);

                    return str_contains($sql, 'properties') && str_ends_with($sql, 'for update');
                })->count();
                if ($waiting < 2) {
                    usleep(20_000);
                }
            } while ($waiting < 2 && microtime(true) < $deadline);
            $this->assertSame(2, $waiting, 'Both reviewers must reach the property lock on separate connections.');
            DB::commit();

            $statuses = [];
            foreach ($processes as $adminId => $process) {
                $this->assertSame(0, $process->wait(), $process->getErrorOutput());
                $statuses[$adminId] = (int) trim($process->getOutput());
            }
            $winner = array_search(200, $statuses, true);
            sort($statuses);
            $this->assertSame([200, 409], $statuses);
            $entry->refresh();
            $this->assertEquals($winner, $entry->reviewed_by);
            $this->assertSame('Reason '.$winner, $entry->rejection_reason);
        } finally {
            if (DB::transactionLevel() !== 0) {
                DB::rollBack();
            }
            foreach ($processes as $process) {
                $process->stop(1);
            }
            File::deleteDirectory($directory);
        }
    }

    private function entry(): PropertyVerification
    {
        $property = Property::factory()->create(['address' => '12 Bạch Đằng', 'status' => PropertyStatus::ACTIVE]);

        return PropertyVerification::query()->create([
            'property_id' => $property->id, 'submitted_by' => $property->host_id, 'status' => 'SUBMITTED',
            'address_snapshot' => $property->address, 'submitted_at' => now()->startOfSecond(),
            'document_path' => 'property-verifications/'.$property->id.'/'.Str::uuid().'.pdf',
            'document_mime' => 'application/pdf', 'document_size' => 10,
        ]);
    }

    private function url(int $propertyId): string
    {
        return '/api/admin/properties/'.$propertyId.'/verification';
    }
}
