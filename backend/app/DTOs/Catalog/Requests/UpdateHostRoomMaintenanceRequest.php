<?php

namespace App\DTOs\Catalog\Requests;

final readonly class UpdateHostRoomMaintenanceRequest
{
    public function __construct(
        public int $maintenanceId,
        public string $startDate,
        public string $endDate,
        public ?string $reason,
    ) {}
}
