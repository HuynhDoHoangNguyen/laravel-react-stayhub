<?php

namespace App\Mappers\Invoice;

use App\Common\Constants;
use App\Mappers\EloquentObjectMapper;
use App\DTOs\Invoice\Requests\CreateInvoiceRequest;
use App\Models\Booking;
use Illuminate\Database\Eloquent\Model;
use App\DTOs\Invoice\Requests\InvoiceFilterRequest;
use App\DTOs\Invoice\Responses\InvoiceResponse;
use App\DTOs\Invoice\Responses\InvoiceItemResponse;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use LogicException;

/** Pure projection of cast attributes and explicitly loaded relationships. */
final class InvoiceMapper
{
    public function __construct(private readonly EloquentObjectMapper $objectMapper) {}

    public function toCreateRequest(int $bookingId): CreateInvoiceRequest
    {
        return new CreateInvoiceRequest($bookingId);
    }

    /** @return list<array{service_id: mixed, service_name: mixed, quantity: string, unit_price: string, amount: string}> */
    public function toServiceSnapshots(Booking $booking): array
    {
        $snapshots = [];
        foreach ($this->loadedRelation($booking, 'bookingServices') as $service) {
            $snapshots[] = [
                'service_id' => $service->service_id,
                'service_name' => $service->service_name,
                'quantity' => (string) $service->quantity,
                'unit_price' => (string) $service->unit_price,
                'amount' => (string) $service->amount,
            ];
        }
        return $snapshots;
    }

    /** @param array{subtotal: string, total_amount: string} $calculation
     *  @return array<string, mixed> Trusted persistence attributes, never a client payload.
     */
    public function toInvoiceAttributes(
        Booking $booking, string $invoiceCode, array $calculation,
        string $discountAmount, string $extraFee, string $paymentStatus, ?string $paidAt,
    ): array {
        $customer = $this->loadedRelation($booking, 'customer');
        $room = $this->loadedRelation($booking, 'room');
        $property = $this->loadedRelation($room, 'property');
        return [
            'invoice_code' => $invoiceCode,
            'booking_id' => $booking->id,
            'customer_name' => (string) $customer->name,
            'customer_email' => (string) $customer->email,
            'customer_phone' => (string) $customer->phone,
            'property_name' => (string) $property->name,
            'room_name' => (string) $room->name,
            'subtotal' => $calculation['subtotal'],
            'discount_amount' => $discountAmount,
            'extra_fee' => $extraFee,
            'total_amount' => $calculation['total_amount'],
            'payment_status' => $paymentStatus,
            'paid_at' => $paidAt,
        ];
    }

    /** @param array{room_total: string} $calculation
     *  @return array<string, mixed>
     */
    public function toRoomItemAttributes(Booking $booking, array $calculation): array
    {
        $room = $this->loadedRelation($booking, 'room');
        return [
            'item_type' => 'ROOM',
            'item_name' => (string) $room->name,
            'description' => "Tiền phòng ({$booking->number_of_nights} đêm × {$booking->price_per_night})",
            'quantity' => (string) $booking->number_of_nights,
            'unit_price' => (string) $booking->price_per_night,
            'amount' => $calculation['room_total'],
        ];
    }

    /** @param array{service_name: string, quantity: string, unit_price: string, amount: string} $service
     *  @return array<string, mixed>
     */
    public function toServiceItemAttributes(array $service): array
    {
        return [
            'item_type' => 'SERVICE', 'item_name' => $service['service_name'], 'description' => null,
            'quantity' => $service['quantity'], 'unit_price' => $service['unit_price'], 'amount' => $service['amount'],
        ];
    }

    private function loadedRelation(Model $model, string $relation): mixed
    {
        if (!$model->relationLoaded($relation)) {
            throw new LogicException("Invoice mapping requires eager-loaded relation: {$relation}.");
        }
        return $model->getRelation($relation);
    }

    /** @param array{status?: ?string, search?: ?string, page?: mixed, per_page?: mixed} $query Validated HTTP query only. */
    public function toFilterRequest(array $query): InvoiceFilterRequest
    {
        $page = filter_var($query['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
        $perPage = isset($query['per_page']) && is_numeric($query['per_page'])
            ? (int) $query['per_page'] : Constants::DEFAULT_PAGE_SIZE;

        return new InvoiceFilterRequest(
            status: $query['status'] ?? null,
            // Legacy QueryService skipped the literal "0" through empty().
            search: empty($query['search']) ? null : $query['search'],
            page: $page,
            perPage: $perPage,
        );
    }

    public function toResponse(Invoice $invoice, bool $includeItems = false): InvoiceResponse
    {
        if ($includeItems && !$invoice->relationLoaded('items')) {
            throw new LogicException('Invoice items must be eager loaded before detail mapping.');
        }

        $prepared = $includeItems
            ? ['items' => $invoice->getRelation('items')->map(fn (InvoiceItem $item) => $this->toItemResponse($item))->values()->all()]
            : [];

        return $this->objectMapper->map($invoice, InvoiceResponse::class, [
            'id',
            'invoice_code',
            'booking_id',
            'customer_name',
            'customer_email',
            'customer_phone',
            'property_name',
            'room_name',
            'subtotal',
            'discount_amount',
            'extra_fee',
            'total_amount',
            'payment_status',
            'paid_at',
            'created_at',
        ], $prepared);
    }

    public function toItemResponse(InvoiceItem $item): InvoiceItemResponse
    {
        return $this->objectMapper->map($item, InvoiceItemResponse::class, [
            'id',
            'invoice_id',
            'item_type',
            'item_name',
            'description',
            'quantity',
            'unit_price',
            'amount',
        ]);
    }
}
