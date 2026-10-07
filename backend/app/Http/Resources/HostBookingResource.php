<?php

namespace App\Http\Resources;

use App\Enums\BookingStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HostBookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $statusEnum = $this->status instanceof BookingStatus ? $this->status : BookingStatus::tryFrom((string) $this->status);

        return [
            'id' => $this->id,
            'booking_code' => $this->booking_code,
            'customer_id' => $this->customer_id,
            'room_id' => $this->room_id,
            'check_in_date' => $this->check_in_date instanceof \DateTimeInterface ? $this->check_in_date->format('Y-m-d') : (string) $this->check_in_date,
            'check_out_date' => $this->check_out_date instanceof \DateTimeInterface ? $this->check_out_date->format('Y-m-d') : (string) $this->check_out_date,
            'guest_count' => (int) $this->guest_count,
            'number_of_nights' => (int) $this->number_of_nights,
            'price_per_night' => number_format((float) $this->price_per_night, 2, '.', ''),
            'room_total' => number_format((float) $this->room_total, 2, '.', ''),
            'status' => $statusEnum?->value ?? (string) $this->status,
            'status_label' => $statusEnum?->label() ?? (string) $this->status,
            'note' => $this->note,
            'services_total' => $this->when(isset($this->services_total), fn () => number_format((float) $this->services_total, 2, '.', '')),
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'email' => $this->customer->email,
                'phone' => $this->customer->phone,
            ]),
            'room' => $this->whenLoaded('room', fn () => [
                'id' => $this->room->id,
                'room_number' => $this->room->room_number,
                'name' => $this->room->name,
                'capacity' => $this->room->capacity,
                'property' => $this->when($this->room->relationLoaded('property'), fn () => [
                    'id' => $this->room->property->id,
                    'name' => $this->room->property->name,
                    'address' => $this->room->property->address,
                ]),
            ]),
            'booking_services' => BookingServiceResource::collection($this->whenLoaded('bookingServices')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
