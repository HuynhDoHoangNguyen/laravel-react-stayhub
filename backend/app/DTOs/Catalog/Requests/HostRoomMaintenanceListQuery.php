<?php

namespace App\DTOs\Catalog\Requests;

final readonly class HostRoomMaintenanceListQuery
{
    public function __construct(public int $roomId, public int $page) {}
}
