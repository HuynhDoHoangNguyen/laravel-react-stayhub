<?php

namespace App\DTOs\Invoice\Responses;

use JsonSerializable;
use Symfony\Component\ObjectMapper\Attribute\Map;

final readonly class InvoiceItemResponse implements JsonSerializable
{
    public function __construct(
        #[Map(source: 'id')]
        public int $id,
        #[Map(source: 'invoice_id')]
        public int $invoice_id,
        #[Map(source: 'item_type')]
        public string $item_type,
        #[Map(source: 'item_name')]
        public string $item_name,
        #[Map(source: 'description')]
        public ?string $description,
        #[Map(source: 'quantity')]
        public string $quantity,
        #[Map(source: 'unit_price')]
        public string $unit_price,
        #[Map(source: 'amount')]
        public string $amount,
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'item_type' => $this->item_type,
            'item_name' => $this->item_name,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'amount' => $this->amount,
        ];
    }
}
