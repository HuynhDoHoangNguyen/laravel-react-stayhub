<?php

namespace App\Services\Catalog;

use App\DTOs\Catalog\Requests\UploadHostPropertyImageRequest;
use App\DTOs\Catalog\Requests\HostPropertyImageRequest;
use App\DTOs\Catalog\Responses\DeleteHostPropertyImageResponse;
use App\DTOs\Catalog\Responses\HostPropertyImageResponse;
use App\Mappers\Catalog\CatalogMapper;
use App\Models\User;
use App\Repositories\Catalog\CatalogRepository;
use App\Validators\Catalog\CatalogValidator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;
use Throwable;

class HostPropertyImageService
{
    public function __construct(
        private readonly CatalogRepository $repository,
        private readonly CatalogValidator $validator,
        private readonly CatalogMapper $mapper,
    ) {}

    private function requireRootTransaction(User $actor): void
    {
        $this->validator->authorizeHost($actor);
        if (DB::transactionLevel() !== 0) throw new LogicException('Property image mutations require a root transaction.');
    }

    /** @return list<HostPropertyImageResponse>|null */
    public function makePrimary(User $actor, HostPropertyImageRequest $input): ?array
    {
        $this->requireRootTransaction($actor);
        $changed = false;
        $result = DB::transaction(function () use ($actor, $input, &$changed): ?array {
            if ($this->repository->lockHostProperty($actor->getKey(), $input->propertyId) === null) return null;
            $images = $this->repository->getPropertyImages($input->propertyId);
            $image = $images->firstWhere('id', $input->imageId);
            if ($image === null) return null;
            if (PublicImageUrl::from($image->image_path) === null) {
                throw new HttpResponseException(response()->json(['message' => 'Ảnh không có URL công khai hợp lệ để làm ảnh đại diện.'], 409));
            }
            $changed = $this->repository->setPropertyPrimary($input->propertyId, $input->imageId);

            return $this->mapImages($input->propertyId);
        });
        if ($changed) Log::info('Đã cập nhật ảnh đại diện cơ sở.', ['property_id' => $input->propertyId, 'image_id' => $input->imageId]);

        return $result;
    }

    public function delete(User $actor, HostPropertyImageRequest $input): ?DeleteHostPropertyImageResponse
    {
        $this->requireRootTransaction($actor);
        $path = null;
        $result = DB::transaction(function () use ($actor, $input, &$path): ?array {
            if ($this->repository->lockHostProperty($actor->getKey(), $input->propertyId) === null) return null;
            $images = $this->repository->getPropertyImages($input->propertyId);
            $image = $images->firstWhere('id', $input->imageId);
            if ($image === null) return null;
            $path = $image->image_path;
            $eligible = $images->where('id', '!=', $image->id)
                ->filter(fn ($candidate) => PublicImageUrl::from($candidate->image_path) !== null)->sortBy('id');
            $replacement = $image->is_primary ? $eligible->first() : ($eligible->firstWhere('is_primary', true) ?? $eligible->first());
            $this->repository->deletePropertyImage($image);
            $this->repository->setPropertyPrimary($input->propertyId, $replacement?->id);

            return $this->mapImages($input->propertyId);
        });
        if ($result === null) return null;
        $cleanup = $this->cleanupDeletedImage($path, $input->propertyId, $input->imageId);
        Log::info('Đã xóa ảnh khỏi cơ sở lưu trú.', ['property_id' => $input->propertyId, 'image_id' => $input->imageId]);

        return new DeleteHostPropertyImageResponse($input->imageId, $result, $cleanup);
    }

    /** @return list<HostPropertyImageResponse> */
    private function mapImages(int $propertyId): array
    {
        return $this->repository->getPropertyImages($propertyId)->map($this->mapper->toHostPropertyImage(...))->filter()->values()->all();
    }

    private function cleanupDeletedImage(string $path, int $propertyId, int $imageId): string
    {
        if (preg_match('~\Aproperties/'.$propertyId.'/[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}\.(?:jpg|png|webp)\z~D', $path) !== 1) return 'not_managed';
        try {
            if ($this->repository->imagePathReferenced($path)) return 'shared';
            $disk = Storage::disk('public');
            if (!$disk->exists($path)) return 'missing';
            if ($disk->delete($path)) return 'deleted';
        } catch (Throwable) {
            // The row is committed; report cleanup failure without replaying the deletion.
        }
        Log::warning('Không thể dọn tệp ảnh cơ sở đã xóa.', ['property_id' => $propertyId, 'image_id' => $imageId, 'managed_path' => $path]);

        return 'failed';
    }

    /** @return list<HostPropertyImageResponse>|null */
    public function listing(User $actor, int $propertyId): ?array
    {
        $this->validator->authorizeHost($actor);
        $property = $this->repository->findHostProperty($actor->getKey(), $propertyId);
        if ($property === null) return null;

        return $property->images->map($this->mapper->toHostPropertyImage(...))->filter()->values()->all();
    }

    public function upload(User $actor, int $propertyId, UploadHostPropertyImageRequest $input): ?HostPropertyImageResponse
    {
        $this->validator->authorizeHost($actor);
        if (DB::transactionLevel() !== 0) {
            throw new LogicException('Property image uploads require a root transaction.');
        }
        $this->validator->validateHostPropertyImage($actor, ['image' => $input->image]);
        $path = null;
        try {
            $result = DB::transaction(function () use ($actor, $propertyId, $input, &$path): ?HostPropertyImageResponse {
                $property = $this->repository->lockHostProperty($actor->getKey(), $propertyId);
                if ($property === null) return null;
                $images = $this->repository->getPropertyImages($propertyId);
                if ($images->count() >= 20) {
                    throw new HttpResponseException(response()->json(['message' => 'Cơ sở đã đạt giới hạn 20 ảnh.'], 409));
                }
                $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$input->image->getMimeType()];
                $filename = Str::uuid().'.'.$extension;
                $path = 'properties/'.$propertyId.'/'.$filename;
                if (Storage::disk('public')->putFileAs('properties/'.$propertyId, $input->image, $filename) === false) {
                    throw new RuntimeException('Property image storage failed.');
                }
                $image = $this->repository->createPropertyImage([
                    'property_id' => $propertyId, 'image_path' => $path,
                    'is_primary' => !$images->contains(fn ($image) => $image->is_primary),
                ]);
                return $this->mapper->toHostPropertyImage($image)
                    ?? throw new LogicException('Uploaded image must have a public URL.');
            });
        } catch (Throwable $failure) {
            if ($path !== null) $this->cleanup($path, $propertyId);
            throw $failure;
        }
        // Upload owns the root transaction: commit has completed before logging.
        if ($result !== null) Log::info('Tải ảnh cơ sở lưu trú thành công.', ['property_id' => $propertyId, 'image_id' => $result->id]);

        return $result;
    }

    private function cleanup(string $path, int $propertyId): void
    {
        try {
            if (Storage::disk('public')->delete($path)) return;
        } catch (Throwable) {
            // Compensation must preserve the original storage/database failure.
        }
        Log::warning('Không thể dọn ảnh cơ sở sau lỗi upload.', ['property_id' => $propertyId, 'managed_path' => $path]);
    }
}
