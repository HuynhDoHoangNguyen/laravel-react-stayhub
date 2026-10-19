<?php

namespace App\Http\Resources\Invoice;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceItemResource extends JsonResource
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
            'invoice_id' => (int) $this->invoice_id,
            'item_type' => (string) $this->item_type,
            'item_name' => (string) $this->item_name,
            'description' => $this->description !== null ? (string) $this->description : null,
            'quantity' => bcadd((string) $this->quantity, '0', 2),
            'unit_price' => bcadd((string) $this->unit_price, '0', 2),
            'amount' => bcadd((string) $this->amount, '0', 2),
        ];
    }
}
