<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\DTOs\Catalog\Requests\SubmitPropertyVerificationRequest;
use App\DTOs\Catalog\Responses\HostPropertyVerificationResponse;
use App\Models\Property;
use App\Models\PropertyVerification;
use App\Models\User;
use App\Repositories\Catalog\CatalogRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final class HostPropertyVerificationService
{
    public function __construct(private readonly CatalogRepository $catalog) {}

    public function requireOwnedProperty(User $host, int $propertyId): void
    {
        if (!Property::query()->whereKey($propertyId)->where('host_id', $host->id)->exists()) {
            throw new NotFoundHttpException('Không tìm thấy cơ sở lưu trú.');
        }
    }

    public function current(User $host, int $propertyId): ?HostPropertyVerificationResponse
    {
        $this->requireOwnedProperty($host, $propertyId);
        $verification = PropertyVerification::query()->where('property_id', $propertyId)->first();

        return $verification === null ? null : $this->response($verification);
    }

    public function submit(User $host, int $propertyId, SubmitPropertyVerificationRequest $input): HostPropertyVerificationResponse
    {
        if (DB::transactionLevel() !== 0) {
            throw new LogicException('Verification uploads require a root transaction.');
        }

        $path = null;
        try {
            $result = DB::transaction(function () use ($host, $propertyId, $input, &$path): HostPropertyVerificationResponse {
                $property = $this->catalog->lockHostProperty($host->id, $propertyId);
                if ($property === null) {
                    throw new NotFoundHttpException('Không tìm thấy cơ sở lưu trú.');
                }
                $previous = PropertyVerification::query()->where('property_id', $propertyId)->lockForUpdate()->first(['id']);
                if ($previous !== null) {
                    throw new ConflictHttpException('Cơ sở này đã có hồ sơ xác minh.');
                }

                $directory = 'property-verifications/'.$propertyId;
                $filename = Str::uuid().'.'.$input->extension;
                $path = $directory.'/'.$filename;
                if (Storage::disk('local')->putFileAs($directory, $input->document, $filename, 'private') === false) {
                    throw new RuntimeException('Unable to store the verification document.');
                }

                $verification = PropertyVerification::query()->create([
                    'property_id' => $propertyId,
                    'submitted_by' => $host->id,
                    'address_snapshot' => $property->address,
                    'status' => PropertyVerification::SUBMITTED,
                    'document_path' => $path,
                    'document_mime' => $input->mime,
                    'document_size' => $input->size,
                    'submitted_at' => now()->startOfSecond(),
                ]);

                return $this->response($verification);
            });
        } catch (Throwable $failure) {
            if ($path !== null) {
                $this->cleanupUncommittedFile($path, $propertyId);
            }
            throw $failure;
        }

        Log::info('Host đã gửi hồ sơ xác minh cơ sở.', ['property_id' => $propertyId, 'verification_id' => $result->id]);

        return $result;
    }

    private function cleanupUncommittedFile(string $path, int $propertyId): void
    {
        try {
            // A lost connection during commit can leave the write outcome unknown.
            $committed = PropertyVerification::query()->useWritePdo()->where('document_path', $path)->exists();
            if ($committed || Storage::disk('local')->delete($path)) {
                return;
            }
        } catch (Throwable) {
            // Preserve the original failure and retain the document if commit cannot be checked.
        }
        Log::warning('Chưa thể đối chiếu hoặc dọn hồ sơ xác minh sau lỗi ghi.', ['property_id' => $propertyId]);
    }

    private function response(PropertyVerification $verification): HostPropertyVerificationResponse
    {
        return new HostPropertyVerificationResponse(
            $verification->id,
            $verification->status,
            $verification->submitted_at->utc()->toIso8601ZuluString(),
            $verification->address_snapshot,
            $verification->reviewed_at?->utc()->toIso8601ZuluString(),
            $verification->rejection_reason,
        );
    }
}
