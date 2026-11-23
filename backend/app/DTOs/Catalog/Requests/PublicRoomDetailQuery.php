<?php

namespace App\DTOs\Catalog\Requests;

final readonly class PublicRoomDetailQuery
{
    public function __construct(public int $roomId) {}
}
