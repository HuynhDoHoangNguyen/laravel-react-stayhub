<?php

namespace App\Http\Resources\Catalog;

use App\Services\Catalog\PublicImageUrl;
use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicPropertyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $images = $this->images->map(fn ($image) => PublicImageUrl::from($image->image_path))->filter()->values();
        return [
            'id' => $this->id, 'name' => $this->name, 'type' => $this->type->value,
            'address' => $this->address, 'description' => $this->description,
            'check_in_time' => $this->check_in_time, 'check_out_time' => $this->check_out_time,
            'primary_image' => $images->first(),
            'starting_price' => $this->starting_price === null ? null : (string) BigDecimal::of((string) $this->starting_price)->toScale(2),
            'currency' => 'VND', 'price_unit' => 'night',
            'rating' => $this->review_count > 0 ? round((float) $this->rating, 1) : null,
            'review_count' => (int) $this->review_count,
            'images' => $this->when($this->resource->relationLoaded('rooms'), fn () => $images->all()),
            'rooms' => PublicRoomResource::collection($this->whenLoaded('rooms')),
            'amenities' => $this->when($this->resource->relationLoaded('rooms'), fn () => $this->rooms->flatMap->amenities
                ->unique('id')->sortBy('id')->values()->map(fn ($amenity) => ['id' => $amenity->id, 'name' => $amenity->name])->all()),
        ];
    }
}
