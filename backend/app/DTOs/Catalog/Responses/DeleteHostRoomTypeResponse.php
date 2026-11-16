<?php

namespace App\DTOs\Catalog\Responses;

use JsonSerializable;

final readonly class DeleteHostRoomTypeResponse implements JsonSerializable
{
    public function __construct(public int $deletedRoomTypeId) {}

    public function jsonSerialize(): array
    {
        return ['deleted_room_type_id' => $this->deletedRoomTypeId];
    }
}
