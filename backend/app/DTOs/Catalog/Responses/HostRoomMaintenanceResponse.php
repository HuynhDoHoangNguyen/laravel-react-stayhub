<?php

namespace App\DTOs\Catalog\Responses;

use JsonSerializable;

final readonly class HostRoomMaintenanceResponse implements JsonSerializable
{
    public function __construct(
        public int $id,
        public string $startDate,
        public string $endDate,
        public ?string $reason,
        public string $status,
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'reason' => $this->reason,
            'status' => $this->status,
        ];
    }
}
