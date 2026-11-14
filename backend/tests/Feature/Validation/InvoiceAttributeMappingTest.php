<?php

namespace Tests\Feature\Validation;

use App\DTOs\Invoice\Responses\InvoiceItemResponse;
use App\Mappers\Invoice\InvoiceMapper;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Validators\AttributeValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;

class InvoiceAttributeMappingTest extends TestCase
{
    use RefreshDatabase;

    public function createApplication()
    {
        foreach (['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => ''] as $key => $expected) {
            if (($_ENV[$key] ?? getenv($key)) !== $expected) {
                throw new RuntimeException('Pilot requires isolated SQLite in-memory.');
            }
        }
        if (is_file(dirname(__DIR__, 3).'/bootstrap/cache/config.php')) {
            throw new RuntimeException('Pilot must not use cached configuration.');
        }
        $app = parent::createApplication();
        if ($app['config']->get('database.default') !== 'sqlite'
            || $app['config']->get('database.connections.sqlite.database') !== ':memory:'
            || !empty($app['config']->get('database.connections.sqlite.url'))) {
            throw new RuntimeException('Unsafe pilot database.');
        }
        return $app;
    }

    private function persistedInvoice(): Invoice
    {
        // Synthetic pilot fixture, not evidence of the create API workflow.
        foreach ([1 => 'HOST', 2 => 'CUSTOMER'] as $id => $role) {
            DB::table('users')->insert(['id' => $id, 'name' => 'Fixture', 'email' => $id.'@example.test', 'password' => 'unused', 'role' => $role, 'status' => 'ACTIVE']);
        }
        DB::table('properties')->insert(['id' => 1, 'host_id' => 1, 'name' => 'Property', 'type' => 'HOTEL', 'address' => 'Fixture', 'phone' => '0900000000', 'status' => 'ACTIVE']);
        DB::table('room_types')->insert(['id' => 1, 'property_id' => 1, 'name' => 'Type']);
        DB::table('rooms')->insert(['id' => 1, 'property_id' => 1, 'room_type_id' => 1, 'room_number' => 'R1', 'name' => 'Room', 'capacity' => 2, 'price_per_night' => '100.00', 'status' => 'ACTIVE']);
        DB::table('bookings')->insert(['id' => 1, 'booking_code' => 'BK-PILOT', 'room_id' => 1, 'customer_id' => 2,
            'check_in_date' => '2026-11-01', 'check_out_date' => '2026-11-02', 'guest_count' => 1, 'number_of_nights' => 1,
            'price_per_night' => '100.00', 'room_total' => '100.00', 'status' => 'COMPLETED']);
        return Invoice::create(['invoice_code' => 'INV-PILOT', 'booking_id' => 1, 'customer_name' => 'Customer',
            'customer_email' => 'fixture@example.test', 'customer_phone' => '0900000000', 'property_name' => 'Snapshot property', 'room_name' => 'Snapshot room',
            'subtotal' => '9999999999.99', 'discount_amount' => '0.01', 'extra_fee' => '0.02', 'total_amount' => '1234567.89',
            'payment_status' => 'UNPAID', 'paid_at' => null]);
    }

    public function test_model_group_constraints_are_explicit_and_do_not_change_attributes_or_query(): void
    {
        $adapter = app(AttributeValidator::class);
        $invoice = (new Invoice)->forceFill(['invoice_code' => '   ', 'total_amount' => '123.45']);
        $item = (new InvoiceItem)->forceFill(['item_name' => '   ']);
        $invoiceBefore = $invoice->getAttributes();
        $itemBefore = $item->getAttributes();
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $this->assertSame([], $adapter->errors($invoice));
            $this->assertSame([], $adapter->errors($item));
            $this->assertSame(['invoiceCodeValidationValue' => ['Dữ liệu hóa đơn không hợp lệ.']], $adapter->errors($invoice, ['invoice-pilot']));
            $this->assertSame(['itemNameValidationValue' => ['Dữ liệu hóa đơn không hợp lệ.']], $adapter->errors($item, ['invoice-pilot']));
            $this->assertSame([], DB::getQueryLog());
            $this->assertSame($invoiceBefore, $invoice->getAttributes());
            $this->assertSame($itemBefore, $item->getAttributes());
        } finally {
            DB::disableQueryLog();
        }
    }

    public function test_model_save_refresh_dirty_tracking_and_json_are_unchanged(): void
    {
        $invoice = $this->persistedInvoice();
        $this->assertSame([], app(AttributeValidator::class)->errors($invoice, ['invoice-pilot']));
        $this->assertSame('1234567.89', $invoice->total_amount);
        $this->assertNull($invoice->paid_at);
        $invoice->invoice_code = 'INV-PILOT-UPDATED';
        $this->assertTrue($invoice->isDirty('invoice_code'));
        $this->assertSame('INV-PILOT-UPDATED', $invoice->getInvoiceCodeValidationValue());
        $this->assertTrue($invoice->save());
        $invoice->refresh();
        $this->assertFalse($invoice->isDirty());
        $this->assertSame('INV-PILOT-UPDATED', $invoice->invoice_code);
        $this->assertArrayNotHasKey('invoice_code_validation_value', $invoice->toArray());
        $this->assertArrayNotHasKey('invoiceCodeValidationValue', $invoice->toArray());
        $item = $invoice->items()->create(['item_type' => 'ROOM', 'item_name' => 'Room', 'quantity' => '1.25', 'unit_price' => '12.34', 'amount' => '15.43']);
        $this->assertSame([], app(AttributeValidator::class)->errors($item, ['invoice-pilot']));
        $item->item_name = 'Updated room';
        $this->assertTrue($item->isDirty('item_name'));
        $item->save();
        $this->assertSame('Updated room', $item->fresh()->getItemNameValidationValue());
    }

    public function test_model_is_not_globally_validated_on_save(): void
    {
        $invoice = $this->persistedInvoice();
        $invoice->invoice_code = '   ';
        $invoice->save();
        $this->assertSame('   ', $invoice->fresh()->invoice_code);
        $this->assertNotEmpty(app(AttributeValidator::class)->errors($invoice, ['invoice-pilot']));
    }

    public function test_mapping_eloquent_item_to_existing_readonly_dto_preserves_projection_without_queries(): void
    {
        $invoice = $this->persistedInvoice();
        $item = $invoice->items()->create(['item_type' => 'SERVICE', 'item_name' => 'Breakfast', 'description' => null,
            'quantity' => '1.25', 'unit_price' => '12.34', 'amount' => '15.43']);
        $item->setAttribute('internal_note', 'must not leak');
        $before = $item->getAttributes();
        $expected = ['id' => $item->id, 'invoice_id' => $invoice->id, 'item_type' => 'SERVICE', 'item_name' => 'Breakfast',
            'description' => null, 'quantity' => '1.25', 'unit_price' => '12.34', 'amount' => '15.43'];
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $dto = app(\App\Mapping\EloquentObjectMapper::class)->map($item, InvoiceItemResponse::class,
                ['id', 'invoice_id', 'item_type', 'item_name', 'description', 'quantity', 'unit_price', 'amount']);
            $this->assertInstanceOf(InvoiceItemResponse::class, $dto);
            $this->assertTrue((new \ReflectionClass($dto))->isReadOnly());
            $this->assertSame($expected, $dto->jsonSerialize());
            $this->assertSame((new InvoiceMapper)->toItemResponse($item)->jsonSerialize(), $dto->jsonSerialize());
            $this->assertSame($expected, json_decode(json_encode($dto, JSON_THROW_ON_ERROR), true));
            $this->assertSame([], DB::getQueryLog());
            $this->assertFalse($item->relationLoaded('invoice'));
            $this->assertSame($before, $item->getAttributes());
        } finally {
            DB::disableQueryLog();
        }
    }


    public function test_mapping_adapter_rejects_relationships_and_missing_fields_without_queries(): void
    {
        $invoice = $this->persistedInvoice();
        $item = $invoice->items()->create(['item_type' => 'ROOM', 'item_name' => 'Room', 'quantity' => '1.00', 'unit_price' => '0.00', 'amount' => '0.00']);
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            foreach (['invoice', 'missing_field'] as $field) {
                try {
                    app(\App\Mapping\EloquentObjectMapper::class)->map($item, InvoiceItemResponse::class, [$field]);
                    $this->fail('Missing attribute or relation accepted.');
                } catch (\LogicException $error) {
                    $this->assertSame('Only loaded model attributes may be mapped.', $error->getMessage());
                }
            }
            $this->assertFalse($item->relationLoaded('invoice'));
            $this->assertSame([], DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
    }

    public function test_mapper_date_transform_and_nullable_constructor_arguments_are_supported(): void
    {
        $mapper = app(ObjectMapperInterface::class);
        foreach ([null, new \DateTimeImmutable('2026-11-02T01:02:03+00:00')] as $date) {
            $source = (object) ['paid_at' => $date, 'amount' => '9999999999.99'];
            $dto = $mapper->map($source, DateMappingPilot::class);
            $this->assertSame($date?->format(DATE_ATOM), $dto->paid_at);
            $this->assertSame('9999999999.99', $dto->amount);
        }
    }

    public function test_empty_and_loaded_item_collections_keep_existing_list_detail_semantics(): void
    {
        $invoice = $this->persistedInvoice()->load('items');
        $mapper = new InvoiceMapper;
        $this->assertArrayNotHasKey('items', $mapper->toResponse($invoice)->jsonSerialize());
        $this->assertSame([], $mapper->toResponse($invoice, true)->jsonSerialize()['items']);
        $this->assertSame([], $invoice->items->map(fn ($item) => app(\App\Mapping\EloquentObjectMapper::class)->map($item, InvoiceItemResponse::class,
            ['id', 'invoice_id', 'item_type', 'item_name', 'description', 'quantity', 'unit_price', 'amount']))->all());
        foreach (['ROOM', 'SERVICE'] as $type) {
            $invoice->items()->create(['item_type' => $type, 'item_name' => $type, 'description' => null,
                'quantity' => '1.00', 'unit_price' => '0.00', 'amount' => '0.00']);
        }
        $invoice->load('items');
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $mapped = $invoice->items->map(fn ($item) => app(\App\Mapping\EloquentObjectMapper::class)->map($item, InvoiceItemResponse::class,
                ['id', 'invoice_id', 'item_type', 'item_name', 'description', 'quantity', 'unit_price', 'amount'])->jsonSerialize())->all();
            $this->assertCount(2, $mapped);
            $this->assertSame($mapper->toResponse($invoice, true)->jsonSerialize()['items'], $mapped);
            $this->assertSame([], DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
    }
    public function test_create_groups_are_explicit_and_validation_is_query_free_and_non_mutating(): void
    {
        $adapter = app(AttributeValidator::class);
        $validator = app(\App\Validators\Invoice\InvoiceValidator::class);
        $invoice = new Invoice(['invoice_code' => ' INV-CREATE ', 'total_amount' => '123.45']);
        $item = new InvoiceItem(['item_type' => 'ROOM', 'item_name' => ' Room snapshot ', 'amount' => '123.45']);
        $before = [$invoice->getAttributes(), $invoice->getDirty(), $invoice->toArray(), $item->getAttributes(), $item->getDirty(), $item->toArray()];
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $validator->validateInvoiceModel($invoice);
            $validator->validateRoomItemModel($item);
            $this->assertSame($before, [$invoice->getAttributes(), $invoice->getDirty(), $invoice->toArray(), $item->getAttributes(), $item->getDirty(), $item->toArray()]);
            $invoice->invoice_code = '   ';
            $item->item_name = '   ';
            $this->assertSame([], $adapter->errors($invoice));
            $this->assertSame([], $adapter->errors($item));
            $this->assertSame([], $adapter->errors($invoice, ['invoice-create-room']));
            $this->assertSame([], $adapter->errors($item, ['invoice-create']));
            $this->assertSame($adapter->errors($invoice, ['invoice-pilot']), $adapter->errors($invoice, ['invoice-create']));
            $this->assertNotEmpty($adapter->errors($invoice, ['invoice-create']));
            $this->assertSame($adapter->errors($item, ['invoice-pilot']), $adapter->errors($item, ['invoice-create-room']));
            $this->assertNotEmpty($adapter->errors($item, ['invoice-create-room']));
            $this->assertSame([], DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }
}

final readonly class DateMappingPilot
{
    public function __construct(
        #[Map(source: 'paid_at', transform: [self::class, 'toIso'])]
        public ?string $paid_at,
        public string $amount,
    ) {}

    public static function toIso(?\DateTimeInterface $value): ?string
    {
        return $value?->format(DATE_ATOM);
    }

}
