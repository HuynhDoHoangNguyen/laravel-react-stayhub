<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class SearchService
{
    public function __construct(
        protected AvailabilityService $availabilityService
    ) {}

    /**
     * Search available rooms matching filters.
     */
    public function searchRooms(array $filters): LengthAwarePaginator
    {
        $query = $this->availabilityService->availableRooms($filters);

        // Filter property_id
        if (! empty($filters['property_id'])) {
            $query->where('property_id', $filters['property_id']);
        }

        // Filter keyword (property name or address)
        if (! empty($filters['keyword'])) {
            $kw = $filters['keyword'];
            $query->whereHas('property', function (Builder $q) use ($kw) {
                $q->where(function (Builder $sub) use ($kw) {
                    $sub->where('name', 'like', "%{$kw}%")
                        ->orWhere('address', 'like', "%{$kw}%");
                });
            });
        }

        // Filter room_type_id
        if (! empty($filters['room_type_id'])) {
            $query->where('room_type_id', $filters['room_type_id']);
        }

        // Filter min_price
        if (isset($filters['min_price']) && $filters['min_price'] !== '') {
            $query->where('price_per_night', '>=', (float) $filters['min_price']);
        }

        // Filter max_price
        if (isset($filters['max_price']) && $filters['max_price'] !== '') {
            $query->where('price_per_night', '<=', (float) $filters['max_price']);
        }

        // Filter amenity_ids (must have ALL specified amenities)
        if (! empty($filters['amenity_ids']) && is_array($filters['amenity_ids'])) {
            $amenityIds = array_filter(array_map('intval', $filters['amenity_ids']));
            if (! empty($amenityIds)) {
                $query->whereHas('amenities', function (Builder $q) use ($amenityIds) {
                    $q->whereIn('amenities.id', $amenityIds);
                }, '=', count($amenityIds));
            }
        }

        // Sort
        $sort = $filters['sort'] ?? 'price_asc';
        match ($sort) {
            'price_desc' => $query->orderBy('price_per_night', 'desc'),
            'newest' => $query->orderBy('created_at', 'desc'),
            default => $query->orderBy('price_per_night', 'asc'),
        };

        // Eager load relationships to prevent N+1
        $query->with(['property', 'roomType', 'amenities', 'images']);

        $perPage = min((int) ($filters['per_page'] ?? 15), 50);
        if ($perPage < 1) {
            $perPage = 15;
        }

        return $query->paginate($perPage);
    }
}
