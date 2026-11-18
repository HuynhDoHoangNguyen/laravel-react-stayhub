<?php

namespace App\Mappers\Catalog;

use App\DTOs\Catalog\Responses\HostPropertyResponse;
use App\DTOs\Catalog\Responses\HostRoomTypeResponse;
use App\DTOs\Catalog\Responses\HostRoomResponse;
use App\DTOs\Catalog\Responses\HostRoomDetailResponse;
use App\DTOs\Catalog\Responses\HostPropertyDetailResponse;
use App\DTOs\Catalog\Responses\PublicPropertyDetailResponse;
use App\DTOs\Catalog\Responses\PublicPropertyListItemResponse;
use App\DTOs\Catalog\Responses\PublicRoomResponse;
use App\DTOs\Catalog\Responses\PublicRoomListItemResponse;
use App\DTOs\Catalog\Responses\PublicRoomFilterOptionsResponse;
use App\Mappers\EloquentObjectMapper;
use App\Models\Amenity;
use App\Models\Property;
use App\Models\PropertyImage;
use App\DTOs\Catalog\Responses\HostPropertyImageResponse;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\Catalog\PublicImageUrl;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;
use LogicException;

final class CatalogMapper
{
    public function __construct(private readonly EloquentObjectMapper $objectMapper) {}

    public function toPublicRoomListItem(Room $room): PublicRoomListItemResponse
    {
        if (!$room->relationLoaded('property')) throw new LogicException('Public room list requires a loaded property.');
        $property = $room->getRelation('property');

        return $this->objectMapper->map($room, PublicRoomListItemResponse::class, [], [
            'room' => $this->toPublicRoom($room),
            'property' => ['id' => $property->id, 'name' => $property->name, 'type' => $property->type->value, 'address' => $property->address,
                'rating' => $property->reviews_avg_rating === null ? null : round((float) $property->reviews_avg_rating, 1),
                'review_count' => (int) $property->reviews_count],
        ]);
    }

    public function toPublicRoomFilterOptions(Collection $types, Collection $amenities): PublicRoomFilterOptionsResponse
    {
        return new PublicRoomFilterOptionsResponse(
            $types->map(static function (RoomType $type): array {
                if (!$type->relationLoaded('property')) throw new LogicException('Room filter options require a loaded property.');
                $property = $type->getRelation('property');

                return ['id' => $type->id, 'name' => $type->name, 'property' => ['id' => $property->id, 'name' => $property->name]];
            })->values()->all(),
            $amenities->map(static fn (Amenity $amenity) => ['id' => $amenity->id, 'name' => $amenity->name])->values()->all(),
        );
    }

    public function toHostRoomDetail(Room $room): HostRoomDetailResponse
    {
        foreach (['property', 'images', 'amenities'] as $relation) {
            if (!$room->relationLoaded($relation)) {
                throw new LogicException('Host room detail mapping requires loaded property, images and amenities.');
            }
        }

        return $this->objectMapper->map($room, HostRoomDetailResponse::class, [], [
            'room' => $this->toHostRoom($room),
            'property' => ['id' => $room->property->id, 'name' => $room->property->name,
                'status' => $room->property->status->value],
            'images' => $this->imageUrls($room->images),
            'amenities' => $room->amenities->map(fn (Amenity $amenity) => [
                'id' => $amenity->id, 'name' => $amenity->name, 'status' => $amenity->status,
            ])->values()->all(),
        ]);
    }

    public function toHostRoom(Room $room): HostRoomResponse
    {
        if (!$room->relationLoaded('roomType') || !array_key_exists('property_id', $room->getAttributes())) {
            throw new LogicException('Host room mapping requires loaded room type and property ID.');
        }
        $type = $room->roomType;
        $roomType = $type !== null && (int) $type->property_id === (int) $room->property_id
            ? ['id' => $type->id, 'name' => $type->name]
            : null;

        return $this->objectMapper->map($room, HostRoomResponse::class,
            ['id', 'room_number', 'name', 'description', 'capacity', 'price_per_night', 'status'],
            ['room_type' => $roomType]);
    }

    public function toHostRoomType(RoomType $roomType): HostRoomTypeResponse
    {
        return $this->objectMapper->map($roomType, HostRoomTypeResponse::class, ['id', 'name', 'description']);
    }

    public function toHostPropertyImage(PropertyImage $image): ?HostPropertyImageResponse
    {
        $url = PublicImageUrl::from($image->image_path);

        return $url === null ? null : $this->objectMapper->map($image, HostPropertyImageResponse::class,
            ['id', 'is_primary'], ['url' => $url]);
    }

    public function toHostProperty(Property $property): HostPropertyResponse
    {
        return $this->objectMapper->map($property, HostPropertyResponse::class, ['id', 'name']);
    }

    public function toHostPropertyDetail(Property $property): HostPropertyDetailResponse
    {
        return $this->objectMapper->map($property, HostPropertyDetailResponse::class, [
            'id', 'name', 'type', 'address', 'description', 'phone', 'check_in_time', 'check_out_time', 'status',
        ], ['images' => $this->propertyImageUrls($property)]);
    }

    public function toPublicListItem(Property $property): PublicPropertyListItemResponse
    {
        return $this->mapPropertyHeader($property, $this->propertyImageUrls($property)[0] ?? null);
    }

    public function toPublicDetail(Property $property): PublicPropertyDetailResponse
    {
        if (!$property->relationLoaded('rooms')) {
            throw new LogicException('Public property detail mapping requires loaded rooms.');
        }

        $images = $this->propertyImageUrls($property);
        $rooms = $property->rooms->map($this->toPublicRoom(...))->values();
        $amenities = $rooms->flatMap(fn (PublicRoomResponse $room) => $room->amenities)
            ->unique('id')->sortBy('id')->values()->all();

        return $this->objectMapper->map($property, PublicPropertyDetailResponse::class, [], [
            'header' => $this->mapPropertyHeader($property, $images[0] ?? null),
            'images' => $images,
            'rooms' => $rooms->all(),
            'amenities' => $amenities,
        ]);
    }

    private function mapPropertyHeader(Property $property, ?string $primaryImage): PublicPropertyListItemResponse
    {
        return $this->objectMapper->map(
            $property,
            PublicPropertyListItemResponse::class,
            ['id', 'name', 'type', 'address', 'description', 'check_in_time', 'check_out_time'],
            [
                'primary_image' => $primaryImage,
                'starting_price' => $property->starting_price === null
                    ? null
                    : (string) BigDecimal::of((string) $property->starting_price)->toScale(2),
                'rating' => $property->review_count > 0 ? round((float) $property->rating, 1) : null,
                'review_count' => (int) $property->review_count,
            ],
        );
    }

    private function toPublicRoom(Room $room): PublicRoomResponse
    {
        if (!$room->relationLoaded('roomType') || !$room->relationLoaded('images') || !$room->relationLoaded('amenities')) {
            throw new LogicException('Public room mapping requires loaded room type, images and amenities.');
        }

        return $this->objectMapper->map(
            $room,
            PublicRoomResponse::class,
            ['id', 'room_number', 'name', 'description', 'capacity', 'price_per_night'],
            [
                'room_type' => ['id' => $room->roomType->id, 'name' => $room->roomType->name],
                'images' => $this->imageUrls($room->images),
                'amenities' => $room->amenities->map(fn (Amenity $amenity) => [
                    'id' => $amenity->id,
                    'name' => $amenity->name,
                ])->values()->all(),
            ],
        );
    }

    /** @return list<string> */
    private function propertyImageUrls(Property $property): array
    {
        if (!$property->relationLoaded('images')) {
            throw new LogicException('Public property mapping requires loaded images.');
        }

        return $this->imageUrls($property->images);
    }

    /** @return list<string> */
    private function imageUrls(Collection $images): array
    {
        return $images->map(fn ($image) => PublicImageUrl::from($image->image_path))->filter()->values()->all();
    }
}
