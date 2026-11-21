<?php

namespace App\Services\Catalog;

use App\DTOs\Catalog\Requests\HostRoomImageQuery;
use App\DTOs\Catalog\Requests\UploadHostRoomImageRequest;
use App\DTOs\Catalog\Responses\HostRoomImageResponse;
use App\Exceptions\AppException;
use App\Mappers\Catalog\CatalogMapper;
use App\Models\User;
use App\Repositories\Catalog\CatalogRepository;
use App\Validators\Catalog\CatalogValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;
use Throwable;

final class HostRoomImageService
{
    public function __construct(
        private readonly CatalogRepository $repository,
        private readonly CatalogValidator $validator,
        private readonly CatalogMapper $mapper,
    ) {}

    /** @return list<HostRoomImageResponse>|null */
    public function listing(User $actor, HostRoomImageQuery $input): ?array
    {
        $this->validator->authorizeHost($actor);
        if ($this->repository->findHostRoomPropertyId($actor->getKey(), $input->roomId) === null) return null;

        return $this->repository->getRoomImages($input->roomId)
            ->map($this->mapper->toHostRoomImage(...))->filter()->values()->all();
    }

    public function upload(User $actor, HostRoomImageQuery $target, UploadHostRoomImageRequest $input): ?HostRoomImageResponse
    {
        $this->validator->authorizeHost($actor);
        if (DB::transactionLevel() !== 0) throw new LogicException('Room image uploads require a root transaction.');
        $this->validator->validateHostRoomImageUpload($actor, ['image' => $input->image]);
        $path = null;
        try {
            $result = DB::transaction(function () use ($actor, $target, $input, &$path): ?HostRoomImageResponse {
                // Match HostRoomService lock order: Property, then Room.
                $propertyId = $this->repository->findHostRoomPropertyId($actor->getKey(), $target->roomId);
                $property = $propertyId === null ? null : $this->repository->lockHostProperty($actor->getKey(), $propertyId);
                $room = $property === null ? null : $this->repository->lockPropertyRoom($property, $target->roomId);
                if ($room === null) return null;
                $images = $this->repository->getRoomImages($room->id, true);
                if ($images->count() >= 20) {
                    throw new AppException('CATALOG_IMAGE_LIMIT_REACHED', 'Phòng đã đạt giới hạn 20 ảnh.', 409);
                }
                $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$input->image->getMimeType()];
                $filename = Str::uuid().'.'.$extension;
                $path = 'rooms/'.$room->id.'/'.$filename;
                if (Storage::disk('public')->putFileAs('rooms/'.$room->id, $input->image, $filename) === false) {
                    throw new RuntimeException('Room image storage failed.');
                }
                $image = $this->repository->createRoomImage([
                    'room_id' => $room->id, 'image_path' => $path, 'is_primary' => false,
                ]);
                $primary = $images->first(fn ($candidate) => $candidate->is_primary && PublicImageUrl::from($candidate->image_path) !== null);
                $this->repository->setRoomImagePrimary($room->id, $primary?->id ?? $image->id);

                return $this->mapper->toHostRoomImage($image->refresh())
                    ?? throw new LogicException('Uploaded room image must have a public URL.');
            });
        } catch (Throwable $failure) {
            if ($path !== null) $this->cleanup($path, $target->roomId);
            throw $failure;
        }
        if ($result !== null) Log::info('Tải ảnh phòng thành công.', ['room_id' => $target->roomId, 'image_id' => $result->id]);

        return $result;
    }

    private function cleanup(string $path, int $roomId): void
    {
        try {
            // Preserve files whose rows survived an ambiguous commit failure.
            if ($this->repository->roomImagePathReferenced($path)) return;
            $disk = Storage::disk('public');
            if (!$disk->exists($path) || $disk->delete($path)) return;
        } catch (Throwable) {
            // Preserve the original failure if compensation itself fails.
        }
        Log::warning('Không thể dọn ảnh phòng sau lỗi upload.', ['room_id' => $roomId, 'managed_path' => $path]);
    }
}
