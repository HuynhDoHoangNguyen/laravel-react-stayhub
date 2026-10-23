<?php

namespace App\Http\Resources\Invoice;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'invoice_code' => (string) $this->invoice_code,
            'booking_id' => (int) $this->booking_id,
            'customer_name' => (string) $this->customer_name,
            'customer_email' => (string) $this->customer_email,
            'customer_phone' => (string) $this->customer_phone,
            'property_name' => (string) $this->property_name,
            'room_name' => (string) $this->room_name,
            'subtotal' => bcadd((string) $this->subtotal, '0', 2),
            'discount_amount' => bcadd((string) $this->discount_amount, '0', 2),
            'extra_fee' => bcadd((string) $this->extra_fee, '0', 2),
            'total_amount' => bcadd((string) $this->total_amount, '0', 2),
            'payment_status' => (string) $this->payment_status,
            'paid_at' => $this->paid_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
