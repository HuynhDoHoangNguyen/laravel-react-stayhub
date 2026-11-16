<?php

namespace App\DTOs\Catalog\Requests;

use App\Enums\RoomStatus;

final readonly class ChangeHostRoomStatusRequest
{
    public function __construct(public int $roomId, public RoomStatus $status) {}
}
