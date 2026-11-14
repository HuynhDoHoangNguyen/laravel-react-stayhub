<?php

namespace App\DTOs\Invoice\Responses;

use JsonSerializable;

final readonly class InvoiceResponse implements JsonSerializable
{
    /** @param list<InvoiceItemResponse>|null $items Null means omitted list projection; [] is loaded empty detail. */
    public function __construct(
        public int $id,
        public string $invoice_code,
        public int $booking_id,
        public string $customer_name,
        public string $customer_email,
        public string $customer_phone,
        public string $property_name,
        public string $room_name,
        public string $subtotal,
        public string $discount_amount,
        public string $extra_fee,
        public string $total_amount,
        public string $payment_status,
        public ?string $paid_at,
        public ?string $created_at,
        public ?array $items = null,
    ) {}

    public function jsonSerialize(): array
    {
        $data = [
            'id' => $this->id,
            'invoice_code' => $this->invoice_code,
            'booking_id' => $this->booking_id,
            'customer_name' => $this->customer_name,
            'customer_email' => $this->customer_email,
            'customer_phone' => $this->customer_phone,
            'property_name' => $this->property_name,
            'room_name' => $this->room_name,
            'subtotal' => $this->subtotal,
            'discount_amount' => $this->discount_amount,
            'extra_fee' => $this->extra_fee,
            'total_amount' => $this->total_amount,
            'payment_status' => $this->payment_status,
            'paid_at' => $this->paid_at,
            'created_at' => $this->created_at,
        ];
        if ($this->items !== null) {
            $data['items'] = array_map(static fn (InvoiceItemResponse $item): array => $item->jsonSerialize(), $this->items);
        }

        return $data;
    }
}
