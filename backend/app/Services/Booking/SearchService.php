<?php

namespace App\Services\Booking;

use App\DTOs\Booking\Requests\SearchRoomsQuery;
use App\Repositories\Booking\RoomAvailabilityRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SearchService
{
    public function __construct(
        protected RoomAvailabilityRepository $repository
    ) {}

    /**
     * Search available rooms matching filters.
     */
    public function searchRooms(SearchRoomsQuery $input): LengthAwarePaginator
    {
        $filters = $input->toArray();
        $sort = $input->sort ?? 'price_asc';
        $perPage = min((int) ($input->perPage ?? 15), 50);
        if ($perPage < 1) {
            $perPage = 15;
        }

        return $this->repository->searchAvailableRooms($filters, $sort, $perPage);
    }
}
