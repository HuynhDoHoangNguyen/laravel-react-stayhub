<?php

namespace App\Http\Resources\Review;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $booking = $this->relationLoaded('booking') ? $this->booking : null;
        $property = $this->relationLoaded('property') ? $this->property : null;
        $room = $booking && $booking->relationLoaded('room') ? $booking->room : null;

        return [
            'id' => (int) $this->id,
            'booking_id' => (int) $this->booking_id,
            'booking_code' => $booking?->booking_code ?? null,
            'property_id' => (int) $this->property_id,
            'property_name' => $property?->name ?? null,
            'room_name' => $room?->name ?? null,
            'rating' => (int) $this->rating,
            'comment' => (string) $this->comment,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
