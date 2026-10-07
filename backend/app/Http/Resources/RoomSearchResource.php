<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomSearchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'property_id' => $this->property_id,
            'room_type_id' => $this->room_type_id,
            'room_number' => $this->room_number,
            'name' => $this->name,
            'description' => $this->description,
            'capacity' => $this->capacity,
            'price_per_night' => number_format((float) $this->price_per_night, 2, '.', ''),
            'status' => $this->status,
            'property' => $this->whenLoaded('property', fn () => [
                'id' => $this->property->id,
                'name' => $this->property->name,
                'type' => $this->property->type,
                'address' => $this->property->address,
                'phone' => $this->property->phone,
                'status' => $this->property->status,
            ]),
            'room_type' => $this->whenLoaded('roomType', fn () => [
                'id' => $this->roomType->id,
                'name' => $this->roomType->name,
                'description' => $this->roomType->description,
            ]),
            'amenities' => $this->whenLoaded('amenities', fn () => $this->amenities->map(fn ($amenity) => [
                'id' => $amenity->id,
                'name' => $amenity->name,
                'icon' => $amenity->icon,
            ])),
            'images' => $this->whenLoaded('images', fn () => $this->images->map(fn ($image) => [
                'id' => $image->id,
                'image_path' => $image->image_path,
                'is_primary' => $image->is_primary,
            ])),
        ];
    }
}
