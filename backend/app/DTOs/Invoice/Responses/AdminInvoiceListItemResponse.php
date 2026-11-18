<?php

namespace App\DTOs\Invoice\Responses;

use JsonSerializable;
use Symfony\Component\ObjectMapper\Attribute\Map;

final readonly class AdminInvoiceListItemResponse implements JsonSerializable
{
    public function __construct(
        #[Map(source: 'id', transform: 'intval')]
        public int $id,
        #[Map(source: 'invoice_code', transform: 'strval')]
        public string $invoice_code,
        #[Map(source: 'booking_id', transform: 'intval')]
        public int $booking_id,
        #[Map(source: 'customer_name', transform: 'strval')]
        public string $customer_name,
        #[Map(source: 'property_name', transform: 'strval')]
        public string $property_name,
        #[Map(source: 'room_name', transform: 'strval')]
        public string $room_name,
        #[Map(source: 'total_amount', transform: 'strval')]
        public string $total_amount,
        #[Map(source: 'payment_status', transform: 'strval')]
        public string $payment_status,
        #[Map(source: 'paid_at', transform: [InvoiceResponse::class, 'toIsoString'])]
        public ?string $paid_at,
        #[Map(source: 'created_at', transform: [InvoiceResponse::class, 'toIsoString'])]
        public ?string $created_at,
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id, 'invoice_code' => $this->invoice_code, 'booking_id' => $this->booking_id,
            'customer_name' => $this->customer_name, 'property_name' => $this->property_name,
            'room_name' => $this->room_name, 'total_amount' => $this->total_amount,
            'payment_status' => $this->payment_status, 'paid_at' => $this->paid_at, 'created_at' => $this->created_at,
        ];
    }
}
