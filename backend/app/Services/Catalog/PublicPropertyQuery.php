<?php

namespace App\Services\Catalog;

use App\Enums\PropertyStatus;
use App\Enums\RoomStatus;
use App\Models\Property;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PublicPropertyQuery
{
    private static function publicRooms(Builder $query): void
    {
        $query->where('rooms.status', RoomStatus::ACTIVE->value)
            ->whereHas('roomType', fn (Builder $type) => $type->whereColumn('room_types.property_id', 'rooms.property_id'));
    }

    private function base(): Builder
    {
        return Property::query()->where('status', PropertyStatus::ACTIVE->value)
            ->with(['images' => fn ($images) => $images->select(['id', 'property_id', 'image_path', 'is_primary'])
                ->orderByDesc('is_primary')->orderBy('id')])
            ->withMin(['rooms as starting_price' => self::publicRooms(...)], 'price_per_night')
            // TV3 public review semantics currently have no moderation/status filter.
            ->withCount(['reviews as review_count'])->withAvg('reviews as rating', 'rating');
    }

    public function listing(int $perPage = 12, int $page = 1): LengthAwarePaginator
    {
        return $this->base()->orderBy('properties.id')->paginate($perPage, ['properties.*'], 'page', $page)->withQueryString();
    }

    public function detail(int $id): ?Property
    {
        return $this->base()->with(['rooms' => function ($rooms): void {
            self::publicRooms($rooms->getQuery());
            $rooms->orderBy('rooms.id')->with([
                'roomType:id,property_id,name,description',
                'images' => fn ($images) => $images->orderByDesc('is_primary')->orderBy('id'),
                'amenities' => fn ($amenities) => $amenities->where('amenities.status', true)->orderBy('amenities.id'),
            ]);
        }])->find($id);
    }
}
