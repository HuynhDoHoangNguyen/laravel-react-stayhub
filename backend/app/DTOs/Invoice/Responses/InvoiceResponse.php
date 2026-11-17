<?php

namespace App\DTOs\Invoice\Responses;

use Carbon\CarbonInterface;
use JsonSerializable;
use Symfony\Component\ObjectMapper\Attribute\Map;

final readonly class InvoiceResponse implements JsonSerializable
{
    /** @param list<InvoiceItemResponse>|null $items Null means omitted list projection; [] is loaded empty detail. */
    public function __construct(
        #[Map(source: 'id', transform: 'intval')]
        public int $id,
        #[Map(source: 'invoice_code', transform: 'strval')]
        public string $invoice_code,
        #[Map(source: 'booking_id', transform: 'intval')]
        public int $booking_id,
        #[Map(source: 'customer_name', transform: 'strval')]
        public string $customer_name,
        #[Map(source: 'customer_email', transform: 'strval')]
        public string $customer_email,
        #[Map(source: 'customer_phone', transform: 'strval')]
        public string $customer_phone,
        #[Map(source: 'property_name', transform: 'strval')]
        public string $property_name,
        #[Map(source: 'room_name', transform: 'strval')]
        public string $room_name,
        #[Map(source: 'subtotal', transform: 'strval')]
        public string $subtotal,
        #[Map(source: 'discount_amount', transform: 'strval')]
        public string $discount_amount,
        #[Map(source: 'extra_fee', transform: 'strval')]
        public string $extra_fee,
        #[Map(source: 'total_amount', transform: 'strval')]
        public string $total_amount,
        #[Map(source: 'payment_status', transform: 'strval')]
        public string $payment_status,
        #[Map(source: 'paid_at', transform: [self::class, 'toIsoString'])]
        public ?string $paid_at,
        #[Map(source: 'created_at', transform: [self::class, 'toIsoString'])]
        public ?string $created_at,
        public ?array $items = null,
    ) {}

    public static function toIsoString(?CarbonInterface $date): ?string
    {
        return $date?->toISOString();
    }

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
