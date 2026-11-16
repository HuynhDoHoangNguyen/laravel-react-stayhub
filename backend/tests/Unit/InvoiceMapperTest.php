<?php

namespace Tests\Unit;

use App\DTOs\Invoice\Requests\InvoiceFilterRequest;
use App\Mappers\EloquentObjectMapper;
use App\Mappers\Invoice\InvoiceMapper;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Collection;
use LogicException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ObjectMapper\ObjectMapper;
use Symfony\Component\PropertyAccess\PropertyAccess;

class InvoiceMapperTest extends TestCase
{
    private function mapper(): InvoiceMapper
    {
        $engine = new ObjectMapper(propertyAccessor: PropertyAccess::createPropertyAccessor());

        return new InvoiceMapper(new EloquentObjectMapper($engine));
    }

    private function invoice(): Invoice
    {
        // No application/DB booted: accessing an unloaded relationship would fail.
        return (new Invoice)->forceFill([
            'id' => 7, 'invoice_code' => 'INV-7', 'booking_id' => 11,
            'customer_name' => 'Khách', 'customer_email' => 'fixture@example.test', 'customer_phone' => '0900000000',
            'property_name' => 'Snapshot Property', 'room_name' => 'Snapshot Room',
            'subtotal' => '9999999999.99', 'discount_amount' => '0.01', 'extra_fee' => '0.02',
            // Deliberately independent snapshot: mapper must not recalculate it.
            'total_amount' => '1234567.89', 'payment_status' => 'UNPAID', 'paid_at' => null, 'created_at' => null,
        ]);
    }

    public function test_list_projection_preserves_all_public_snapshot_fields_and_decimals(): void
    {
        $model = $this->invoice();
        $before = $model->getAttributes();
        $data = $this->mapper()->toResponse($model);
        $this->assertSame(7, $data->id);
        $this->assertSame(11, $data->booking_id);
        $this->assertSame('INV-7', $data->invoice_code);
        $this->assertSame('Khách', $data->customer_name);
        $this->assertSame('fixture@example.test', $data->customer_email);
        $this->assertSame('0900000000', $data->customer_phone);
        $this->assertSame('Snapshot Property', $data->property_name);
        $this->assertSame('Snapshot Room', $data->room_name);
        $this->assertSame('9999999999.99', $data->subtotal);
        $this->assertSame('0.01', $data->discount_amount);
        $this->assertSame('0.02', $data->extra_fee);
        $this->assertSame('1234567.89', $data->total_amount);
        $this->assertSame('UNPAID', $data->payment_status);
        $this->assertNull($data->paid_at);
        $this->assertNull($data->created_at);
        $this->assertNull($data->items);
        $this->assertSame($before, $model->getAttributes());
        $this->assertFalse($model->relationLoaded('items'));
    }

    public function test_detail_distinguishes_empty_items_and_preserves_item_snapshot(): void
    {
        $model = $this->invoice()->setRelation('items', new Collection);
        $mapper = $this->mapper();
        $this->assertSame([], $mapper->toResponse($model, true)->items);
        $item = (new InvoiceItem)->forceFill([
            'id' => 8, 'invoice_id' => 7, 'item_type' => 'SERVICE', 'item_name' => 'Snapshot Breakfast',
            'description' => null, 'quantity' => '1.25', 'unit_price' => '12.34', 'amount' => '15.43',
        ]);
        $model->setRelation('items', new Collection([$item]));
        $data = $mapper->toResponse($model, true)->items[0];
        $this->assertSame([8, 7, 'SERVICE', 'Snapshot Breakfast', null, '1.25', '12.34', '15.43'], array_values(get_object_vars($data)));
        $this->assertNull($mapper->toResponse($model)->items);
    }

    public function test_header_keeps_utc_iso_dates_with_microseconds(): void
    {
        $model = $this->invoice()->setDateFormat('Y-m-d H:i:s');
        $model->setRawAttributes(array_replace($model->getAttributes(), [
            'id' => '7',
            'booking_id' => '11',
            'paid_at' => '2026-11-14T08:02:03.456789+07:00',
            'created_at' => '2026-11-14T01:01:00.123456Z',
        ]));
        $data = $this->mapper()->toResponse($model)->jsonSerialize();

        $this->assertSame(7, $data['id']);
        $this->assertSame(11, $data['booking_id']);
        $this->assertSame('2026-11-14T01:02:03.456789Z', $data['paid_at']);
        $this->assertSame('2026-11-14T01:01:00.123456Z', $data['created_at']);
    }

    public function test_detail_refuses_unloaded_relationship_instead_of_querying(): void
    {
        $this->expectException(LogicException::class);
        $this->mapper()->toResponse($this->invoice(), true);
    }

    public function test_filter_normalization_preserves_legacy_clamping_and_unknown_status_behavior(): void
    {
        $filter = new InvoiceFilterRequest('unsupported', '  0  ', -3, 500);
        $this->assertNull($filter->status);
        $this->assertSame('0', $filter->search);
        $this->assertSame(1, $filter->page);
        $this->assertSame(50, $filter->perPage);
        $this->assertSame(1, (new InvoiceFilterRequest(perPage: -1))->perPage);
        $this->assertSame('PAID', (new InvoiceFilterRequest('PAID'))->status);
        $this->assertNull((new InvoiceFilterRequest(search: ' '))->search);
    }

    public function test_response_dto_serialization_has_exact_public_fields_and_never_recalculates_money(): void
    {
        $model = $this->invoice()->forceFill(['internal_note' => 'must not be exposed']);
        $before = $model->getAttributes();
        $dto = $this->mapper()->toResponse($model);
        $expected = [
            'id' => 7, 'invoice_code' => 'INV-7', 'booking_id' => 11,
            'customer_name' => 'Khách', 'customer_email' => 'fixture@example.test', 'customer_phone' => '0900000000',
            'property_name' => 'Snapshot Property', 'room_name' => 'Snapshot Room',
            'subtotal' => '9999999999.99', 'discount_amount' => '0.01', 'extra_fee' => '0.02',
            'total_amount' => '1234567.89', 'payment_status' => 'UNPAID', 'paid_at' => null, 'created_at' => null,
        ];
        $this->assertSame($expected, $dto->jsonSerialize());
        $this->assertSame($expected, json_decode(json_encode($dto, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR));
        $this->assertSame($before, $model->getAttributes());
        $this->assertFalse($model->relationLoaded('items'));
    }

    public function test_detail_json_distinguishes_omitted_and_empty_items_and_serializes_item_fields(): void
    {
        $model = $this->invoice()->setRelation('items', new Collection);
        $mapper = $this->mapper();
        $this->assertSame([], $mapper->toResponse($model, true)->jsonSerialize()['items']);
        $item = (new InvoiceItem)->forceFill([
            'id' => 8, 'invoice_id' => 7, 'item_type' => 'ROOM', 'item_name' => 'Snapshot room',
            'description' => null, 'quantity' => '1.00', 'unit_price' => '0.00', 'amount' => '0.00',
            'internal_note' => 'must not be exposed',
        ]);
        $before = $item->getAttributes();
        $model->setRelation('items', new Collection([$item]));
        $dto = $mapper->toResponse($model, true);
        $expected = [['id' => 8, 'invoice_id' => 7, 'item_type' => 'ROOM', 'item_name' => 'Snapshot room',
            'description' => null, 'quantity' => '1.00', 'unit_price' => '0.00', 'amount' => '0.00']];
        $this->assertSame($expected, $dto->jsonSerialize()['items']);
        $this->assertSame($expected, json_decode(json_encode($dto, JSON_THROW_ON_ERROR), true)['items']);
        $this->assertArrayNotHasKey('items', $mapper->toResponse($model)->jsonSerialize());
        $this->assertSame($before, $item->getAttributes());
    }


    public function test_consolidated_filter_mapper_keeps_legacy_input_normalization(): void
    {
        $mapper = $this->mapper();
        foreach ([
            [[], [null, null, 1, 10]],
            [['status' => 'PAID', 'search' => '  INV-1  ', 'page' => '2', 'per_page' => '1.5'], ['PAID', 'INV-1', 2, 1]],
            [['status' => 'UNKNOWN', 'search' => '0', 'page' => 'bad', 'per_page' => '1e2'], [null, null, 1, 50]],
            [['status' => null, 'search' => ' ', 'page' => '-1', 'per_page' => '-2'], [null, null, 1, 1]],
        ] as [$input, $expected]) {
            $before = $input;
            $dto = $mapper->toFilterRequest($input);
            $this->assertSame($expected, [$dto->status, $dto->search, $dto->page, $dto->perPage]);
            $this->assertSame($before, $input);
        }
    }


    public function test_create_mapping_preserves_snapshots_decimals_and_workflow_values_without_recalculation(): void
    {
        $mapper = $this->mapper();
        $this->assertSame(11, $mapper->toCreateRequest(11)->bookingId);
        $property = (new \App\Models\Property)->forceFill(['name' => 'Property snapshot']);
        $room = (new \App\Models\Room)->forceFill(['name' => 'Room snapshot'])->setRelation('property', $property);
        $customer = (new \App\Models\User)->forceFill(['name' => 'Customer snapshot', 'email' => 'synthetic@example.test', 'phone' => '0900000000']);
        $service = (new \App\Models\BookingService)->forceFill([
            'service_id' => 9, 'service_name' => 'Breakfast snapshot', 'quantity' => '1.25', 'unit_price' => '12.34', 'amount' => '15.43',
        ]);
        $booking = (new \App\Models\Booking)->forceFill(['id' => 11, 'number_of_nights' => 2, 'price_per_night' => '999999.99'])
            ->setRelation('customer', $customer)->setRelation('room', $room)->setRelation('bookingServices', new Collection([$service]));
        $before = [$booking->getAttributes(), $customer->getAttributes(), $room->getAttributes(), $property->getAttributes(), $service->getAttributes()];
        $this->assertSame([['service_id' => 9, 'service_name' => 'Breakfast snapshot', 'quantity' => '1.25', 'unit_price' => '12.34', 'amount' => '15.43']], $mapper->toServiceSnapshots($booking));
        // Intentionally independent values: projection must not calculate or override them.
        $calculation = ['subtotal' => '777.77', 'total_amount' => '123.45', 'room_total' => '456.78'];
        $this->assertSame([
            'invoice_code' => 'INV-FIXTURE', 'booking_id' => 11,
            'customer_name' => 'Customer snapshot', 'customer_email' => 'synthetic@example.test', 'customer_phone' => '0900000000',
            'property_name' => 'Property snapshot', 'room_name' => 'Room snapshot',
            'subtotal' => '777.77', 'discount_amount' => '0.01', 'extra_fee' => '0.02', 'total_amount' => '123.45',
            'payment_status' => 'UNPAID', 'paid_at' => null,
        ], $mapper->toInvoiceAttributes($booking, 'INV-FIXTURE', $calculation, '0.01', '0.02', 'UNPAID', null));
        $this->assertSame([
            'item_type' => 'ROOM', 'item_name' => 'Room snapshot', 'description' => 'Tiền phòng (2 đêm × 999999.99)',
            'quantity' => '2', 'unit_price' => '999999.99', 'amount' => '456.78',
        ], $mapper->toRoomItemAttributes($booking, $calculation));
        $this->assertSame([
            'item_type' => 'SERVICE', 'item_name' => 'Breakfast snapshot', 'description' => null,
            'quantity' => '1.25', 'unit_price' => '12.34', 'amount' => '15.43',
        ], $mapper->toServiceItemAttributes($mapper->toServiceSnapshots($booking)[0]));
        $this->assertSame($before, [$booking->getAttributes(), $customer->getAttributes(), $room->getAttributes(), $property->getAttributes(), $service->getAttributes()]);
    }

    public function test_create_mapping_rejects_unloaded_relations_without_database_access(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('requires eager-loaded relation: bookingServices');
        $this->mapper()->toServiceSnapshots(new \App\Models\Booking);
    }
}
