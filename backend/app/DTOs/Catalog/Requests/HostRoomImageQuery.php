<?php

namespace App\DTOs\Catalog\Requests;

final readonly class HostRoomImageQuery
{
    public function __construct(public int $roomId) {}
}
