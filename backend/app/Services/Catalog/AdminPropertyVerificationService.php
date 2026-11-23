<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\DTOs\Catalog\Requests\ReviewPropertyVerificationRequest;
use App\DTOs\Catalog\Responses\AdminPropertyVerificationResponse;
use App\Models\Property;
use App\Models\PropertyVerification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class AdminPropertyVerificationService
{
    private const EXTENSIONS = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    public function current(int $propertyId): ?AdminPropertyVerificationResponse
    {
        $property = Property::query()->findOrFail($propertyId);
        $entry = PropertyVerification::query()->where('property_id', $propertyId)->first();

        return $entry === null ? null : $this->response($property, $entry);
    }

    /** @return array{path: string, name: string, mime: string} */
    public function document(int $propertyId): array
    {
        $entry = PropertyVerification::query()->where('property_id', $propertyId)->firstOrFail();
        $extension = $this->availableExtension($entry);
        if ($extension === null) {
            throw new NotFoundHttpException('Không tìm thấy tệp hồ sơ xác minh.');
        }

        return ['path' => $entry->document_path, 'name' => 'ho-so-xac-minh-'.$entry->id.'.'.$extension, 'mime' => $entry->document_mime];
    }

    public function review(User $admin, int $propertyId, ReviewPropertyVerificationRequest $input): AdminPropertyVerificationResponse
    {
        return DB::transaction(function () use ($admin, $propertyId, $input): AdminPropertyVerificationResponse {
            $property = Property::query()->lockForUpdate()->findOrFail($propertyId);
            $entry = PropertyVerification::query()->where('property_id', $propertyId)->lockForUpdate()->firstOrFail();
            if ($entry->status !== PropertyVerification::SUBMITTED) {
                throw new ConflictHttpException('Hồ sơ này đã được xử lý.');
            }
            if ($input->approve && $entry->address_snapshot !== $property->address) {
                throw new ConflictHttpException('Địa chỉ cơ sở đã thay đổi kể từ khi gửi hồ sơ.');
            }
            if ($input->approve && $this->availableExtension($entry) === null) {
                throw new ConflictHttpException('Tệp hồ sơ bị thiếu. Không thể duyệt hồ sơ này.');
            }
            $entry->update([
                'status' => $input->approve ? PropertyVerification::APPROVED : PropertyVerification::REJECTED,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now()->startOfSecond(),
                'rejection_reason' => $input->reason,
            ]);

            return $this->response($property, $entry);
        });
    }

    private function availableExtension(PropertyVerification $entry): ?string
    {
        $extension = self::EXTENSIONS[$entry->document_mime] ?? null;
        if ($extension === null || !preg_match(
            '~\Aproperty-verifications/'.$entry->property_id.'/[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}\.'.$extension.'\z~',
            $entry->document_path,
        ) || !Storage::disk('local')->exists($entry->document_path)) {
            return null;
        }

        return $extension;
    }

    private function response(Property $property, PropertyVerification $entry): AdminPropertyVerificationResponse
    {
        return new AdminPropertyVerificationResponse(
            $entry->id,
            $entry->status,
            $entry->address_snapshot,
            $property->address,
            $entry->submitted_at->utc()->toIso8601ZuluString(),
            $entry->reviewed_at?->utc()->toIso8601ZuluString(),
            $entry->rejection_reason,
        );
    }
}
