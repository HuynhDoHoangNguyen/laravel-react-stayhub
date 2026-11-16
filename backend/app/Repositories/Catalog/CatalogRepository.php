<?php

namespace App\Repositories\Catalog;

use App\DTOs\Catalog\Requests\CreateHostRoomRequest;
use App\DTOs\Catalog\Requests\UpdateHostRoomRequest;
use App\Enums\PropertyStatus;
use App\Enums\RoomStatus;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class CatalogRepository
{
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
        return PropertyImage::query()->where('image_path', $path)->exists();
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
        return Property::query()->where('status', PropertyStatus::ACTIVE->value)
            ->with(['images' => fn ($images) => $images->select(['id', 'property_id', 'image_path', 'is_primary'])
                ->orderByDesc('is_primary')->orderBy('id')])
            ->withMin(['rooms as starting_price' => self::publicRooms(...)], 'price_per_night')
            // TV3 public review semantics currently have no moderation/status filter.
            ->withCount(['reviews as review_count'])->withAvg('reviews as rating', 'rating');
    }

    private static function publicRooms(Builder $query): void
    {
        $query->where('rooms.status', RoomStatus::ACTIVE->value)
            ->whereHas('roomType', fn (Builder $type) => $type->whereColumn('room_types.property_id', 'rooms.property_id'));
    }
}
