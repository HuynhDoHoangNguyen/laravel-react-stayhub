<?php

namespace App\DTOs\Catalog\Requests;

final readonly class HostRoomImageRequest
{
    public function __construct(public int $roomId, public int $imageId) {}
}
