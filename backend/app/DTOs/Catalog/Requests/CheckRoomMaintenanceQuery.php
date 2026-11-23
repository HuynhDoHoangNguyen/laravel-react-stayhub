<?php

declare(strict_types=1);

namespace App\DTOs\Catalog\Requests;

/** Validated room and half-open, date-only stay interval. */
final readonly class CheckRoomMaintenanceQuery
{
    public function __construct(
        public int $roomId,
        public string $checkInDate,
        public string $checkOutDate,
    ) {}
}
