<?php

namespace App\Http\Resources\Catalog;

use App\Services\Catalog\PublicImageUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicRoomResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'room_number' => $this->room_number, 'name' => $this->name,
            'description' => $this->description, 'capacity' => $this->capacity,
            'price_per_night' => $this->price_per_night,
            'room_type' => ['id' => $this->roomType->id, 'name' => $this->roomType->name],
            'images' => $this->images->map(fn ($image) => PublicImageUrl::from($image->image_path))->filter()->values()->all(),
            'amenities' => $this->amenities->map(fn ($amenity) => ['id' => $amenity->id, 'name' => $amenity->name])->all(),
        ];
    }
}
