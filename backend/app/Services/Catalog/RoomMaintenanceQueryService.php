<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Room;
use App\Repositories\Catalog\CatalogRepository;
use App\Validators\Catalog\RoomMaintenanceValidator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/** Read-only UC51 check. Availability and booking coordination remain with the caller. */
final class RoomMaintenanceQueryService
{
    public function __construct(
        private readonly CatalogRepository $repository,
        private readonly RoomMaintenanceValidator $validator,
    ) {}

    public function hasBlockingMaintenance(mixed $roomId, mixed $checkInDate, mixed $checkOutDate): bool
    {
        $query = $this->validator->validateQuery($roomId, $checkInDate, $checkOutDate);
        if (!$this->repository->roomExistsForMaintenanceCheck($query->roomId)) {
            throw (new ModelNotFoundException())->setModel(Room::class, [$query->roomId]);
        }

        return $this->validator->hasBlockingFacts(
            $this->repository->getRoomMaintenanceFacts($query->roomId),
            $query,
        );
    }
}
