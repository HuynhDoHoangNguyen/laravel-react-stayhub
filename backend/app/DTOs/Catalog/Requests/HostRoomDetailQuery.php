<?php

namespace App\DTOs\Catalog\Requests;

final readonly class HostRoomDetailQuery
{
    public function __construct(public int $roomId) {}
}
