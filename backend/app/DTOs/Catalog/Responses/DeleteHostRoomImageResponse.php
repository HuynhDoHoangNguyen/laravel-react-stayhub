<?php

namespace App\DTOs\Catalog\Responses;

use JsonSerializable;

final readonly class DeleteHostRoomImageResponse implements JsonSerializable
{
    /** @param list<HostRoomImageResponse> $images */
    public function __construct(public int $deletedImageId, public array $images, public string $cleanupStatus) {}

    public function jsonSerialize(): array
    {
        return ['deleted_image_id' => $this->deletedImageId, 'images' => $this->images, 'cleanup_status' => $this->cleanupStatus];
    }
}
