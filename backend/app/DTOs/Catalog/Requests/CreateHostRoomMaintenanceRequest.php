<?php

namespace App\DTOs\Catalog\Requests;

final readonly class CreateHostRoomMaintenanceRequest
{
    public function __construct(
        public int $roomId,
        public string $startDate,
        public string $endDate,
        public ?string $reason = null,
    ) {}
}
