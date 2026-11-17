<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Repositories\Booking\RoomAvailabilityRepository;

class SearchService
{
    public function __construct(
        protected RoomAvailabilityRepository $repository
    ) {}

    /**
     * Search available rooms matching filters.
     */
    public function searchRooms(array $filters): LengthAwarePaginator
    {
        $sort = $filters['sort'] ?? 'price_asc';
        $perPage = min((int) ($filters['per_page'] ?? 15), 50);
        if ($perPage < 1) {
            $perPage = 15;
        }

        return $this->repository->searchAvailableRooms($filters, $sort, $perPage);
    }
}
