<?php

namespace App\Repositories\Catalog;

use App\DTOs\Catalog\Requests\CreateHostRoomRequest;
use App\DTOs\Catalog\Requests\UpdateHostRoomRequest;
use App\DTOs\Catalog\Requests\PublicRoomFilterQuery;
use App\Enums\PropertyStatus;
use App\Enums\MaintenanceStatus;
use App\Enums\RoomStatus;
use App\Models\Maintenance;
use App\Models\Amenity;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\RoomImage;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Review;
use App\Services\Catalog\PublicImageUrl;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\JoinClause;

class CatalogRepository
{
    /** @return Collection<int, RoomImage> */
    public function getRoomImages(int $roomId, bool $lock = false): Collection
    {
        $query = RoomImage::query()->where('room_id', $roomId)->orderByDesc('is_primary')->orderBy('id');
        if ($lock) $query->lockForUpdate();

        return $query->get();
    }

    public function createRoomImage(array $attributes): RoomImage
    {
        return RoomImage::create($attributes)->refresh();
    }

    public function setRoomImagePrimary(int $roomId, ?int $imageId): void
    {
        $others = RoomImage::query()->where('room_id', $roomId)->where('is_primary', true);
        if ($imageId !== null) $others->whereKeyNot($imageId);
        $others->update(['is_primary' => false]);
        if ($imageId !== null) RoomImage::query()->where('room_id', $roomId)->whereKey($imageId)
            ->where('is_primary', false)->update(['is_primary' => true]);
    }

    public function deleteRoomImage(RoomImage $image): void
    {
        if (!$image->delete()) throw new \RuntimeException('Room image deletion failed.');
    }

    public function roomImagePathReferenced(string $path): bool
    {
        $key = PublicImageUrl::publicDiskKey($path);
        if ($key === null) return false;
        foreach ([RoomImage::class, PropertyImage::class] as $model) {
            if ($model::query()->whereIn('image_path', [$key, '/storage/'.$key])->exists()) return true;
        }
        foreach ([RoomImage::class, PropertyImage::class] as $model) {
            // Legacy URLs may contain encoded paths, query strings or a configured public base path.
            // Read in bounded batches; normalization stays identical for both image tables.
            foreach ($model::query()->select(['id', 'image_path'])->lazyById(200) as $image) {
                if (PublicImageUrl::publicDiskKey($image->image_path) === $key) return true;
            }
        }

        return false;
    }

    public function findPublicRoom(int $id): ?Room
    {
        return $this->publicRoomCandidates()->with([
            'property:id,name,type,address',
            'roomType:id,property_id,name',
            'images' => fn ($images) => $images->select(['id', 'room_id', 'image_path', 'is_primary'])
                ->orderByDesc('is_primary')->orderBy('id'),
            'amenities' => fn ($amenities) => $amenities->select(['amenities.id', 'amenities.name'])
                ->where('amenities.status', true)->orderBy('amenities.id'),
        ])->find($id, ['id', 'property_id', 'room_type_id', 'room_number', 'name', 'description', 'capacity', 'price_per_night']);
    }

    public function findPublicRoomForAvailability(int $id): ?Room
    {
        return $this->publicRoomCandidates()
            ->with(['property' => fn ($property) => $property->select(['id', 'name', 'status'])
                ->where('status', PropertyStatus::ACTIVE->value)])
            ->find($id, ['id', 'property_id', 'name', 'status', 'capacity']);
    }

    /** Public catalog filtering; deliberately not a date-based availability query. */
    public function paginatePublicRooms(PublicRoomFilterQuery $filters): LengthAwarePaginator
    {
        $query = $this->publicRoomCandidates()->with([
            'property' => fn ($property) => $property->select(['id', 'name', 'type', 'address'])
                ->withAvg('reviews', 'rating')->withCount('reviews'),
            'roomType:id,property_id,name',
            'images' => fn ($images) => $images->orderByDesc('is_primary')->orderBy('id'),
            'amenities' => fn ($amenities) => $amenities->where('amenities.status', true)->orderBy('amenities.id'),
        ]);
        if ($filters->minPrice !== null) $query->where('rooms.price_per_night', '>=', $filters->minPrice);
        if ($filters->maxPrice !== null) $query->where('rooms.price_per_night', '<=', $filters->maxPrice);
        if ($filters->maxPriceExclusive !== null) {
            $query->whereRaw('rooms.price_per_night < CAST(? AS DECIMAL(16,2))', [$filters->maxPriceExclusive]);
        }
        if ($filters->propertyId !== null) $query->where('rooms.property_id', $filters->propertyId);
        if ($filters->guestCount !== null) $query->where('rooms.capacity', '>=', $filters->guestCount);
        if ($filters->minRating !== null) {
            // Review belongs to Property. Compare the raw average, before display rounding.
            $query->whereIn('rooms.property_id', Review::query()->select('property_id')->groupBy('property_id')
                // Explicit numeric affinity also keeps SQLite and MySQL comparisons consistent.
                ->havingRaw('AVG(reviews.rating) >= CAST(? AS DECIMAL(3,1))', [$filters->minRating]));
        }
        if ($filters->roomTypeIds !== []) $query->whereIn('rooms.room_type_id', $filters->roomTypeIds);
        if ($filters->amenityIds !== []) {
            $query->whereHas('amenities', fn (Builder $amenities) => $amenities
                ->where('amenities.status', true)->whereIn('amenities.id', $filters->amenityIds), '=', count($filters->amenityIds));
        }

        return $query->orderBy('rooms.price_per_night', $filters->sort === 'price_desc' ? 'desc' : 'asc')
            ->orderBy('rooms.id')->paginate($filters->perPage, ['rooms.*'], 'page', $filters->page);
    }

    public function getPublicRoomTypes(): Collection
    {
        return RoomType::query()->whereIn('id', $this->publicRoomCandidates()->select('rooms.room_type_id'))
            ->with('property:id,name')->orderBy('property_id')->orderBy('name')->orderBy('id')
            ->get(['id', 'property_id', 'name']);
    }

    public function getPublicRoomAmenities(): Collection
    {
        return Amenity::query()->where('status', true)->whereHas('rooms', function (Builder $rooms): void {
            $this->scopePublicRoomCandidates($rooms);
        })->orderBy('name')->orderBy('id')->get(['id', 'name']);
    }

    /** @param list<string> $amenityNames @return Collection<int, Room> */
    public function findPublicRoomAmenityCandidates(int $propertyId, array $amenityNames, ?string $roomName = null): Collection
    {
        return $this->publicRoomAmenityCandidates($propertyId, $roomName, 'amenities.name', $amenityNames);
    }

    /** @param list<int> $amenityIds @return Collection<int, Room> */
    public function findPublicRoomAmenityCandidatesByIds(int $propertyId, array $amenityIds, ?string $roomName = null): Collection
    {
        return $this->publicRoomAmenityCandidates($propertyId, $roomName, 'amenities.id', $amenityIds);
    }

    private function publicRoomAmenityCandidates(int $propertyId, ?string $roomName, string $column, array $values): Collection
    {
        $rooms = $this->publicRoomCandidates()
            ->select(['rooms.id', 'rooms.name'])
            ->where('rooms.property_id', $propertyId)
            ->orderBy('rooms.id');
        $enabledAmenity = static fn (Builder $amenities): Builder => $amenities
            ->where('amenities.status', true)->whereIn($column, $values);

        if ($roomName === null) {
            return $rooms->whereHas('amenities', $enabledAmenity)->limit(3)->get();
        }

        // Resolve ambiguity across all public rooms with that name, including rooms without the amenity.
        return $rooms->where('rooms.name', $roomName)
            ->withExists(['amenities as has_requested_amenity' => $enabledAmenity])->limit(2)->get();
    }

    private function publicRoomCandidates(): Builder
    {
        $query = Room::query();
        $this->scopePublicRoomCandidates($query);

        return $query;
    }

    public function scopePublicRoomCandidates(Builder $query): void
    {
        self::publicRooms($query);
        $query->whereHas('property', fn (Builder $property) => $property->where('status', PropertyStatus::ACTIVE->value))
            ->whereBetween('rooms.capacity', [1, 2147483647])->where('rooms.price_per_night', '>=', '0');
    }

    public function roomExistsForMaintenanceCheck(int $roomId): bool
    {
        return Room::query()->whereKey($roomId)->exists();
    }

    /** Uncast facts preserve invalid legacy dates for the UC51 validator. */
    public function getRoomMaintenanceFacts(int $roomId): \Illuminate\Support\Collection
    {
        return Maintenance::query()->where('room_id', $roomId)
            ->whereIn('status', [MaintenanceStatus::SCHEDULED->value, MaintenanceStatus::IN_PROGRESS->value])
            ->orderBy('id')->toBase()->get(['id', 'status', 'start_date', 'end_date']);
    }

    public function lockRoomBookingFacts(Room $room): \Illuminate\Support\Collection
    {
        return $room->bookings()->whereIn('status', ['PENDING', 'CONFIRMED', 'CHECKED_IN'])
            ->select(['id', 'status', 'check_in_date', 'check_out_date'])->orderBy('id')->toBase()->lockForUpdate()->get();
    }

    public function lockRoomMaintenanceFacts(Room $room): \Illuminate\Support\Collection
    {
        return $room->maintenances()->whereIn('status', ['SCHEDULED', 'IN_PROGRESS'])
            ->select(['id', 'status', 'start_date', 'end_date'])->orderBy('id')->toBase()->lockForUpdate()->get();
    }

    public function changeHostRoomStatus(Room $room, RoomStatus $status): void
    {
        $room->status = $status;
        if (!$room->save()) {
            throw new \RuntimeException('Room status update failed.');
        }
    }

    public function findHostRoomPropertyId(int $hostId, int $roomId): ?int
    {
        return Room::query()->whereKey($roomId)
            ->whereHas('property', fn (Builder $query) => $query->where('host_id', $hostId))
            ->value('property_id');
    }

    public function lockPropertyRoom(Property $property, int $roomId): ?Room
    {
        return $property->rooms()->whereKey($roomId)->lockForUpdate()->first();
    }

    /** @return Collection<int, Amenity> */
    public function getHostRoomAmenityOptions(int $roomId): Collection
    {
        return Amenity::query()
            ->leftJoin('room_amenities', function (JoinClause $join) use ($roomId): void {
                $join->on('room_amenities.amenity_id', '=', 'amenities.id')
                    ->where('room_amenities.room_id', $roomId);
            })
            ->where(fn (Builder $query) => $query->where('amenities.status', true)
                ->orWhereNotNull('room_amenities.room_id'))
            ->select(['amenities.id', 'amenities.name', 'amenities.status'])
            ->selectRaw('CASE WHEN room_amenities.room_id IS NULL THEN 0 ELSE 1 END AS assigned_to_room')
            ->withCasts(['assigned_to_room' => 'boolean'])
            ->orderBy('amenities.name')->orderBy('amenities.id')->get();
    }

    public function lockAmenity(int $amenityId): ?Amenity
    {
        return Amenity::query()->whereKey($amenityId)->lockForUpdate()->first();
    }

    /** The caller holds the room lock. */
    public function assignRoomAmenity(Room $room, Amenity $amenity): bool
    {
        return $room->amenities()->syncWithoutDetaching([$amenity->id])['attached'] !== [];
    }

    /** The caller holds the room lock. */
    public function removeRoomAmenity(Room $room, int $amenityId): bool
    {
        return $room->amenities()->detach($amenityId) !== 0;
    }

    public function updateHostRoom(Room $room, RoomType $type, UpdateHostRoomRequest $input): bool
    {
        $room->fill([
            'room_type_id' => $type->getKey(), 'room_number' => $input->roomNumber,
            'name' => $input->name, 'description' => $input->description,
            'capacity' => $input->capacity, 'price_per_night' => $input->pricePerNight,
        ]);
        $changed = $room->isDirty();
        if ($changed && !$room->save()) {
            throw new \RuntimeException('Room update failed.');
        }
        $room->setRelation('roomType', $type);

        return $changed;
    }

    public function hostRoomNumberExists(Property $property, string $number, ?int $exceptId = null): bool
    {
        $query = $property->rooms()->where('room_number', $number);
        if ($exceptId !== null) {
            $query->whereKeyNot($exceptId);
        }

        return $query->lockForUpdate()->first(['id']) !== null;
    }

    public function createHostRoom(Property $property, RoomType $type, CreateHostRoomRequest $input): Room
    {
        $room = $property->rooms()->create([
            'room_type_id' => $type->getKey(),
            'room_number' => $input->roomNumber,
            'name' => $input->name,
            'description' => $input->description,
            'capacity' => $input->capacity,
            'price_per_night' => $input->pricePerNight,
            'status' => RoomStatus::ACTIVE->value,
        ]);

        return $room->setRelation('roomType', $type);
    }

    public function findHostRoom(int $hostId, int $roomId): ?Room
    {
        return Room::query()->whereKey($roomId)
            ->whereHas('property', fn (Builder $query) => $query->where('host_id', $hostId))
            ->with([
                'property:id,name,status',
                'roomType:id,property_id,name',
                'images' => fn ($images) => $images->orderByDesc('is_primary')->orderBy('id'),
                'amenities' => fn ($amenities) => $amenities->orderBy('name')->orderBy('amenities.id'),
            ])->first(['id', 'property_id', 'room_type_id', 'room_number', 'name', 'description',
                'capacity', 'price_per_night', 'status']);
    }

    /** @return Collection<int, Room>|null */
    public function getHostRooms(int $hostId, int $propertyId): ?Collection
    {
        $property = Property::query()->where('host_id', $hostId)->whereKey($propertyId)->first(['id']);
        if ($property === null) {
            return null;
        }

        return $property->rooms()->whereHas('property', fn (Builder $query) => $query->where('host_id', $hostId))
            ->with('roomType:id,property_id,name')
            ->orderBy('room_number')->orderBy('id')
            ->get(['id', 'property_id', 'room_type_id', 'room_number', 'name', 'description',
                'capacity', 'price_per_night', 'status']);
    }

    public function hostRoomTypeNameExists(Property $property, string $name, ?int $exceptId = null): bool
    {
        $query = $property->roomTypes()->where('name', $name);
        if ($exceptId !== null) {
            $query->whereKeyNot($exceptId);
        }

        return $query->lockForUpdate()->first(['id']) !== null;
    }

    public function findHostRoomTypePropertyId(int $hostId, int $roomTypeId): ?int
    {
        return RoomType::query()->whereKey($roomTypeId)
            ->whereHas('property', fn (Builder $query) => $query->where('host_id', $hostId))
            ->value('property_id');
    }

    public function lockPropertyRoomType(Property $property, int $roomTypeId): ?RoomType
    {
        return $property->roomTypes()->whereKey($roomTypeId)->lockForUpdate()->first();
    }

    public function roomTypeHasReferences(RoomType $roomType): bool
    {
        // Read the current references after the parent lock, even if the transaction has an older snapshot.
        return $roomType->rooms()->lockForUpdate()->first(['id']) !== null;
    }

    public function deleteHostRoomType(RoomType $roomType): void
    {
        if (!$roomType->delete()) {
            throw new \RuntimeException('Room type deletion failed.');
        }
    }

    public function updateHostRoomType(RoomType $roomType, string $name, ?string $description): bool
    {
        $roomType->fill(['name' => $name, 'description' => $description]);
        if (!$roomType->isDirty()) {
            return false;
        }
        $roomType->save();

        return true;
    }

    public function createHostRoomType(Property $property, string $name, ?string $description): RoomType
    {
        return $property->roomTypes()->create(['name' => $name, 'description' => $description]);
    }

    /** @return Collection<int, RoomType>|null */
    public function getHostRoomTypes(int $hostId, int $propertyId): ?Collection
    {
        $property = Property::query()->where('host_id', $hostId)->whereKey($propertyId)->first(['id']);
        if ($property === null) {
            return null;
        }

        return $property->roomTypes()->whereHas('property', fn (Builder $query) => $query->where('host_id', $hostId))
            ->orderBy('name')->orderBy('id')->get(['id', 'name', 'description']);
    }

    public function setPropertyPrimary(int $propertyId, ?int $imageId): bool
    {
        $others = PropertyImage::query()->where('property_id', $propertyId)->where('is_primary', true);
        if ($imageId !== null) $others->whereKeyNot($imageId);
        $changed = $others->update(['is_primary' => false]);
        if ($imageId !== null) $changed += PropertyImage::query()->where('property_id', $propertyId)->whereKey($imageId)
            ->where('is_primary', false)->update(['is_primary' => true]);

        return $changed > 0;
    }

    public function deletePropertyImage(PropertyImage $image): void
    {
        $image->delete();
    }

    public function imagePathReferenced(string $path): bool
    {
        return $this->roomImagePathReferenced($path);
    }

    /** @return Collection<int, PropertyImage> */
    public function getPropertyImages(int $propertyId): Collection
    {
        return PropertyImage::query()->where('property_id', $propertyId)->orderByDesc('is_primary')->orderBy('id')->get();
    }

    public function createPropertyImage(array $attributes): PropertyImage
    {
        return PropertyImage::create($attributes)->refresh();
    }

    public function loadHostPropertyImages(Property $property): Property
    {
        return $property->load(['images' => fn ($images) => $images->orderByDesc('is_primary')->orderBy('id')]);
    }

    public function changeHostPropertyStatus(Property $property, PropertyStatus $status): Property
    {
        $property->status = $status;
        $property->save();

        return $property->refresh()->load(['images' => fn ($images) => $images->orderByDesc('is_primary')->orderBy('id')]);
    }

    public function lockHostProperty(int $hostId, int $propertyId): ?Property
    {
        return Property::query()->where('host_id', $hostId)->whereKey($propertyId)->lockForUpdate()->first();
    }

    public function updateHostProperty(Property $property, array $attributes): Property
    {
        $property->fill($attributes)->save();

        return $property->refresh()->load(['images' => fn ($images) => $images->orderByDesc('is_primary')->orderBy('id')]);
    }

    public function createHostProperty(array $attributes): Property
    {
        return Property::create($attributes)->load('images');
    }

    public function findHostProperty(int $hostId, int $propertyId): ?Property
    {
        return Property::query()->where('host_id', $hostId)->whereKey($propertyId)
            ->with(['images' => fn ($images) => $images->orderByDesc('is_primary')->orderBy('id')])
            ->first();
    }

    /** @return Collection<int, Property> */
    public function getHostProperties(int $hostId): Collection
    {
        return Property::query()->where('host_id', $hostId)->orderBy('name')->get(['id', 'name']);
    }

    public function paginatePublicProperties(int $perPage, int $page): LengthAwarePaginator
    {
        return $this->publicProperties()->orderBy('properties.id')
            ->paginate($perPage, ['properties.*'], 'page', $page);
    }

    /** @return Collection<int, Property> */
    public function findPublicPropertyCandidates(string|int $reference): Collection
    {
        return $this->activeProperties()
            ->where(is_int($reference) ? 'id' : 'name', $reference)
            ->orderBy('id')->limit(4)->get(['id', 'name', 'address']);
    }

    public function findPublicProperty(int $id): ?Property
    {
        return $this->publicProperties()->with(['rooms' => function ($rooms): void {
            self::publicRooms($rooms->getQuery());
            $rooms->orderBy('rooms.id')->with([
                'roomType:id,property_id,name,description',
                'images' => fn ($images) => $images->orderByDesc('is_primary')->orderBy('id'),
                'amenities' => fn ($amenities) => $amenities->where('amenities.status', true)->orderBy('amenities.id'),
            ]);
        }])->find($id);
    }

    private function publicProperties(): Builder
    {
        return $this->activeProperties()
            ->with('approvedVerification:property_id,address_snapshot')
            ->with(['images' => fn ($images) => $images->select(['id', 'property_id', 'image_path', 'is_primary'])
                ->orderByDesc('is_primary')->orderBy('id')])
            ->withMin(['rooms as starting_price' => self::publicRooms(...)], 'price_per_night')
            // TV3 public review semantics currently have no moderation/status filter.
            ->withCount(['reviews as review_count'])->withAvg('reviews as rating', 'rating');
    }

    private function activeProperties(): Builder
    {
        return Property::query()->where('status', PropertyStatus::ACTIVE->value);
    }

    private static function publicRooms(Builder $query): void
    {
        $query->where('rooms.status', RoomStatus::ACTIVE->value)
            ->whereHas('roomType', fn (Builder $type) => $type->whereColumn('room_types.property_id', 'rooms.property_id'));
    }
}
