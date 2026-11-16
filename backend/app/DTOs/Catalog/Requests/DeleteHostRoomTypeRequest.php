<?php

namespace App\DTOs\Catalog\Requests;

final readonly class DeleteHostRoomTypeRequest
{
    public function __construct(public int $roomTypeId) {}
}
