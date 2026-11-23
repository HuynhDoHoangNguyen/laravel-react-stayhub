<?php

namespace App\Repositories\Booking;

use App\DTOs\Booking\Requests\CheckAvailabilityQuery;
use App\Enums\MaintenanceStatus;
use App\Enums\PropertyStatus;
use App\Enums\RoomStatus;
use App\Models\Amenity;
use App\Models\Booking;
use App\Models\Maintenance;
use App\Models\Property;
use App\Models\Review;
use App\Models\Room;
use App\Models\RoomType;
use App\Repositories\Catalog\CatalogRepository;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class RoomAvailabilityRepository
{
    public function __construct(private readonly CatalogRepository $catalog) {}

    public function propertyForRoom(Room $room): ?Property
    {
        return $room->relationLoaded('property')
            ? $room->getRelation('property')
            : $room->property()->first();
    }

    public function propertyExists(int $propertyId): bool
    {
        return Property::query()->where('id', $propertyId)->exists();
    }

    public function roomTypeExists(int $roomTypeId): bool
    {
        return RoomType::query()->where('id', $roomTypeId)->exists();
    }

    /**
     * @param  array<int>  $roomTypeIds
     * @return array<int>
     */
    public function findExistingRoomTypeIds(array $roomTypeIds): array
    {
        if (empty($roomTypeIds)) {
            return [];
        }

        return RoomType::query()
            ->whereIn('id', $roomTypeIds)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @param  array<int>  $amenityIds
     * @return array<int>
     */
    public function findExistingAmenityIds(array $amenityIds): array
    {
        if (empty($amenityIds)) {
            return [];
        }

        return Amenity::query()
            ->whereIn('id', $amenityIds)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    public function hasBlockingMaintenanceOverlap(int $roomId, string $checkIn, string $checkOut): bool
    {
        $query = Maintenance::query()
            ->where('room_id', $roomId)
            ->whereNotIn('status', [MaintenanceStatus::CANCELLED->value, MaintenanceStatus::COMPLETED->value])
            ->where(function (Builder $query) use ($checkIn, $checkOut) {
                $query->where('status', MaintenanceStatus::IN_PROGRESS->value)
                    ->orWhere(function (Builder $q) use ($checkIn, $checkOut) {
                        $q->where('start_date', '<', $checkOut)
                            ->where('end_date', '>', $checkIn);
                    });
            });

        if (DB::transactionLevel() > 0) {
            $query->lockForUpdate();
        }

        return $query->first(['id']) !== null;
    }

    public function hasHoldingBookingOverlap(int $roomId, string $checkIn, string $checkOut, ?int $ignoreBookingId = null): bool
    {
        $query = $this->holdingBookings($roomId, $checkIn, $checkOut, $ignoreBookingId);

        if (DB::transactionLevel() > 0) {
            $query->lockForUpdate();
        }

        return $query->first(['id']) !== null;
    }

    /** @return Collection<int, Booking> */
    public function blockingBookings(Room $room, string $fromDate, string $toDate): Collection
    {
        return $this->holdingBookings($room->id, $fromDate, $toDate)
            ->get(['check_in_date', 'check_out_date']);
    }

    /** @return Collection<int, Maintenance> */
    public function blockingMaintenances(Room $room, string $fromDate, string $toDate): Collection
    {
        return Maintenance::query()
            ->where('room_id', $room->id)
            ->whereNotIn('status', [MaintenanceStatus::CANCELLED->value, MaintenanceStatus::COMPLETED->value])
            ->where('start_date', '<', $toDate)
            ->where('end_date', '>', $fromDate)
            ->get(['start_date', 'end_date']);
    }

    public function searchAvailableRooms(array $filters, string $sort, int $perPage): LengthAwarePaginator
    {
        $hasDates = ! empty($filters['check_in_date']) && ! empty($filters['check_out_date']);
        $checkIn = $hasDates ? Carbon::parse($filters['check_in_date'])->format('Y-m-d') : null;
        $checkOut = $hasDates ? Carbon::parse($filters['check_out_date'])->format('Y-m-d') : null;
        $guestCount = (int) $filters['guest_count'];
        $ignoreBookingId = $filters['ignore_booking_id'] ?? null;

        if ($hasDates && $checkIn !== null && $checkOut !== null) {
            $query = $this->availableRooms($checkIn, $checkOut, $guestCount, $ignoreBookingId);
        } else {
            $query = Room::query()
                ->where('status', RoomStatus::ACTIVE->value)
                ->where('capacity', '>=', $guestCount)
                ->whereHas('property', function (Builder $query): void {
                    $query->where('status', PropertyStatus::ACTIVE->value);
                });
        }

        if (! empty($filters['property_id'])) {
            $query->where('property_id', $filters['property_id']);
        }

        if (! empty($filters['keyword'])) {
            $keyword = $filters['keyword'];
            $query->whereHas('property', function (Builder $query) use ($keyword): void {
                $query->where(function (Builder $query) use ($keyword): void {
                    $query->where('name', 'like', "%{$keyword}%")
                        ->orWhere('address', 'like', "%{$keyword}%");
                });
            });
        }

        if (! empty($filters['room_type_ids']) && is_array($filters['room_type_ids'])) {
            $roomTypeIds = array_values(array_unique(array_filter(array_map('intval', $filters['room_type_ids']))));
            if (! empty($roomTypeIds)) {
                $query->whereIn('room_type_id', $roomTypeIds);
            }
        } elseif (! empty($filters['room_type_id'])) {
            $query->where('room_type_id', $filters['room_type_id']);
        }

        if (isset($filters['min_rating']) && $filters['min_rating'] !== '' && $filters['min_rating'] !== null) {
            $minRating = $filters['min_rating'];
            $query->whereIn('rooms.property_id', Review::query()->select('property_id')->groupBy('property_id')
                ->havingRaw('AVG(reviews.rating) >= CAST(? AS DECIMAL(3,1))', [$minRating]));
        }

        if (isset($filters['min_price']) && $filters['min_price'] !== '') {
            $query->where('price_per_night', '>=', $filters['min_price']);
        }

        if (isset($filters['max_price']) && $filters['max_price'] !== '') {
            $query->where('price_per_night', '<=', $filters['max_price']);
        }

        if (! empty($filters['amenity_ids']) && is_array($filters['amenity_ids'])) {
            $amenityIds = array_values(array_unique(array_filter(array_map('intval', $filters['amenity_ids']))));
            if (! empty($amenityIds)) {
                $query->whereHas('amenities', function (Builder $query) use ($amenityIds): void {
                    $query->whereIn('amenities.id', $amenityIds);
                }, '=', count($amenityIds));
            }
        }

        match ($sort) {
            'price_desc' => $query->orderBy('price_per_night', 'desc'),
            'newest' => $query->orderBy('created_at', 'desc'),
            default => $query->orderBy('price_per_night', 'asc'),
        };

        return $query->with(['property', 'roomType', 'amenities', 'images'])->paginate($perPage);
    }

    /**
     * Search active public properties filtered by keyword and/or room guest capacity BEFORE pagination.
     *
     * @param array{keyword: ?string, guest_count: ?int, page: int, per_page: int} $filters
     */
    public function searchPublicProperties(array $filters): LengthAwarePaginator
    {
        $keyword = $filters['keyword'] ?? null;
        $guestCount = $filters['guest_count'] ?? null;
        $page = (int) ($filters['page'] ?? 1);
        $perPage = (int) ($filters['per_page'] ?? 12);

        $publicRoomsScope = static function (Builder $roomQuery) use ($guestCount): void {
            $roomQuery->where('rooms.status', RoomStatus::ACTIVE->value)
                ->whereHas('roomType', fn (Builder $type) => $type->whereColumn('room_types.property_id', 'rooms.property_id'));
            if ($guestCount !== null && $guestCount >= 1) {
                $roomQuery->where('rooms.capacity', '>=', $guestCount);
            }
        };

        $query = Property::query()
            ->where('status', PropertyStatus::ACTIVE->value)
            ->with('approvedVerification:property_id,address_snapshot')
            ->with(['images' => fn ($images) => $images->select(['id', 'property_id', 'image_path', 'is_primary'])
                ->orderByDesc('is_primary')->orderBy('id')])
            ->withMin(['rooms as starting_price' => $publicRoomsScope], 'price_per_night')
            ->withCount(['reviews as review_count'])
            ->withAvg('reviews as rating', 'rating');

        if ($keyword !== null && $keyword !== '') {
            $query->where(function (Builder $q) use ($keyword): void {
                $q->where('name', 'like', "%{$keyword}%")
                    ->orWhere('address', 'like', "%{$keyword}%");
            });
        }

        if ($guestCount !== null && $guestCount >= 1) {
            $query->whereHas('rooms', $publicRoomsScope);
        }

        return $query->orderBy('properties.id')
            ->paginate($perPage, ['properties.*'], 'page', $page);
    }
    /** @return Collection<int, Room> */
    public function publicAvailableRooms(int $propertyId, CheckAvailabilityQuery $input): Collection
    {
        $query = $this->availableRooms($input->checkInDate, $input->checkOutDate, $input->guestCount)
            ->where('rooms.property_id', $propertyId);
        $this->catalog->scopePublicRoomCandidates($query);

        return $query->orderBy('rooms.id')->limit(3)->get(['rooms.id', 'rooms.name']);
    }

    /** @return Builder<Room> */
    private function availableRooms(string $checkIn, string $checkOut, int $guestCount, ?int $ignoreBookingId = null): Builder
    {
        return Room::query()
            ->where('status', RoomStatus::ACTIVE->value)
            ->where('capacity', '>=', $guestCount)
            ->whereHas('property', function (Builder $query): void {
                $query->where('status', PropertyStatus::ACTIVE->value);
            })
            ->whereDoesntHave('maintenances', function (Builder $query) use ($checkIn, $checkOut): void {
                $query->whereNotIn('status', [MaintenanceStatus::CANCELLED->value, MaintenanceStatus::COMPLETED->value])
                    ->where(function (Builder $q) use ($checkIn, $checkOut) {
                        $q->where('status', MaintenanceStatus::IN_PROGRESS->value)
                            ->orWhere(function (Builder $sub) use ($checkIn, $checkOut) {
                                $sub->where('start_date', '<', $checkOut)
                                    ->where('end_date', '>', $checkIn);
                            });
                    });
            })
            ->whereDoesntHave('bookings', function (Builder $query) use ($checkIn, $checkOut, $ignoreBookingId): void {
                $query->holdingRoom()->overlapping($checkIn, $checkOut);
                if ($ignoreBookingId !== null) {
                    $query->where('id', '!=', $ignoreBookingId);
                }
            });
    }

    private function holdingBookings(int $roomId, string $checkIn, string $checkOut, ?int $ignoreBookingId = null): Builder
    {
        $query = Booking::query()
            ->where('room_id', $roomId)
            ->holdingRoom()
            ->overlapping($checkIn, $checkOut);

        if ($ignoreBookingId !== null) {
            $query->where('id', '!=', $ignoreBookingId);
        }

        return $query;
    }
}
