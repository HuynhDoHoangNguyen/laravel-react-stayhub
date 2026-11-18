<?php

namespace App\Http\Resources;

use App\Support\DecimalMoney;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'service_id' => $this->service_id,
            'service_name' => $this->service_name,
            'unit' => $this->unit,
            'quantity' => DecimalMoney::formatNullable($this->quantity),
            'unit_price' => DecimalMoney::formatNullable($this->unit_price),
            'amount' => DecimalMoney::formatNullable($this->amount),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
