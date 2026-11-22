<?php

namespace Tests\Unit;

use App\DTOs\Invoice\Requests\AdminInvoiceFilterRequest;
use App\DTOs\Invoice\Responses\AdminInvoiceListItemResponse;
use App\Mappers\EloquentObjectMapper;
use App\Mappers\Invoice\InvoiceMapper;
use App\Models\Invoice;
use App\Validators\AttributeValidator;
use App\Validators\Invoice\AdminInvoiceValidator;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ObjectMapper\ObjectMapper;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Validator\Validation;

final class AdminInvoiceContractTest extends TestCase
{
    private mixed $previousFacadeApplication;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousFacadeApplication = Facade::getFacadeApplication();
        $container = new Container();
        $container->instance('validator', new Factory(new Translator(new ArrayLoader(), 'vi')));
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($container);
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->previousFacadeApplication);
        parent::tearDown();
    }

    private function validator(): AdminInvoiceValidator
    {
        return new AdminInvoiceValidator(new AttributeValidator(Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()));
    }

    private function mapper(): InvoiceMapper
    {
        return new InvoiceMapper(new EloquentObjectMapper(new ObjectMapper(propertyAccessor: PropertyAccess::createPropertyAccessor())));
    }

    public function test_defaults_optional_empty_values_and_unknown_fields_are_distinguished_from_pagination_presence(): void
    {
        $validator = $this->validator();
        $mapper = $this->mapper();
        $filter = $mapper->toAdminFilterRequest($validator->validateQuery([]));
        $this->assertSame(['page' => 1, 'per_page' => 10], $filter->toQuery());
        $this->assertSame($filter->toQuery(), $mapper->toAdminFilterRequest($validator->validateQuery([
            'host_id' => '', 'property_id' => null, 'status' => ' ', 'search' => '', 'unexpected' => ['ignored'],
        ]))->toQuery());
        $filter = $mapper->toAdminFilterRequest($validator->validateQuery([
            'host_id' => ' 17 ', 'property_id' => 23, 'status' => ' PAID ', 'search' => ' 0 ', 'page' => '2', 'per_page' => '50',
        ]));
        $this->assertSame(['host_id' => 17, 'property_id' => 23, 'status' => 'PAID', 'search' => '0', 'page' => 2, 'per_page' => 50], $filter->toQuery());
    }

    public function test_maximum_ids_page_and_unicode_search_length_are_accepted_before_conversion(): void
    {
        $raw = $this->validator()->validateQuery(['host_id' => '9007199254740991', 'property_id' => '9007199254740991',
            'page' => '2147483647', 'per_page' => '1', 'search' => str_repeat('ế', 255)]);
        $this->assertSame('9007199254740991', $raw->host_id);
        $filter = $this->mapper()->toAdminFilterRequest($raw);
        $this->assertSame(AdminInvoiceFilterRequest::MAX_ID, $filter->hostId);
        $this->assertSame(AdminInvoiceFilterRequest::MAX_PAGE, $filter->page);
        $this->assertSame(255, mb_strlen($filter->search));
    }

    public static function invalidQueries(): array
    {
        $cases = [];
        foreach (['host_id', 'property_id', 'page', 'per_page'] as $field) {
            foreach (['array' => [], 'object' => new \stdClass(), 'true' => true, 'false' => false,
                'float' => 1.0, 'zero' => '0', 'negative' => '-1', 'leading zero' => '01',
                'decimal' => '1.0', 'exponent' => '1e2', 'plus' => '+1', 'huge' => str_repeat('9', 50)] as $name => $value) {
                $cases[$field.' '.$name] = [$field, $value];
            }
        }
        foreach (['page', 'per_page'] as $field) {
            foreach (['null' => null, 'empty' => '', 'blank' => '  '] as $name => $value) {
                $cases[$field.' '.$name] = [$field, $value];
            }
        }
        foreach (['host_id', 'property_id'] as $field) {
            $cases[$field.' unsafe integer'] = [$field, '9007199254740992'];
        }
        $cases['page overflow'] = ['page', '2147483648'];
        $cases['per_page overflow'] = ['per_page', '51'];
        foreach (['all' => 'ALL', 'lowercase' => 'paid', 'array' => [], 'bool' => false, 'number' => 1] as $name => $value) {
            $cases['status '.$name] = ['status', $value];
        }
        foreach (['array' => [], 'bool' => false, 'number' => 0, 'long unicode' => str_repeat('ế', 256)] as $name => $value) {
            $cases['search '.$name] = ['search', $value];
        }
        return $cases;
    }

    #[DataProvider('invalidQueries')]
    public function test_invalid_raw_values_produce_field_errors_without_conversion(string $field, mixed $value): void
    {
        try {
            $this->validator()->validateQuery([$field => $value]);
            $this->fail('Invalid raw input accepted.');
        } catch (ValidationException $error) {
            $this->assertSame(422, $error->status);
            $this->assertSame([$field], array_keys($error->errors()));
            $this->assertNotEmpty($error->errors()[$field]);
        }
    }

    public function test_projection_uses_only_loaded_attributes_preserves_snapshot_values_and_null_dates(): void
    {
        $invoice = (new Invoice())->setDateFormat('Y-m-d H:i:s')->forceFill([
            'id' => 7, 'invoice_code' => 'INV-7', 'booking_id' => 11, 'booking_code' => 'BK-11', 'customer_name' => 'Khách snapshot',
            'property_name' => 'Cơ sở snapshot', 'room_name' => 'Phòng snapshot', 'total_amount' => '1234567.89',
            'payment_status' => 'UNPAID', 'paid_at' => null, 'created_at' => null,
            'customer_email' => 'must-not-leak@example.test', 'customer_phone' => '0900000000',
            'subtotal' => '9999999999.99', 'discount_amount' => '0.01', 'extra_fee' => '0.02',
        ]);
        $before = $invoice->getAttributes();
        $response = $this->mapper()->toAdminListItem($invoice);
        $this->assertInstanceOf(AdminInvoiceListItemResponse::class, $response);
        $this->assertSame([
            'id' => 7, 'invoice_code' => 'INV-7', 'booking_id' => 11, 'booking_code' => 'BK-11', 'customer_name' => 'Khách snapshot',
            'property_name' => 'Cơ sở snapshot', 'room_name' => 'Phòng snapshot', 'total_amount' => '1234567.89',
            'payment_status' => 'UNPAID', 'paid_at' => null, 'created_at' => null,
        ], json_decode(json_encode($response, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR));
        $this->assertSame($before, $invoice->getAttributes());
        $this->assertSame([], $invoice->getRelations());
    }

    public function test_projection_serializes_paid_at_in_utc_without_calculating_state(): void
    {
        $invoice = (new Invoice())->setDateFormat('Y-m-d H:i:s')->setRawAttributes([
            'id' => 1, 'invoice_code' => 'I', 'booking_id' => 1, 'booking_code' => null, 'customer_name' => 'C', 'property_name' => 'P',
            'room_name' => 'R', 'total_amount' => '0.00', 'payment_status' => 'REFUNDED',
            'paid_at' => '2026-11-18T10:00:00+07:00', 'created_at' => '2026-11-18T02:00:00Z',
        ]);
        $result = $this->mapper()->toAdminListItem($invoice)->jsonSerialize();
        $this->assertNull($result['booking_code']);
        $this->assertSame('REFUNDED', $result['payment_status']);
        $this->assertSame('2026-11-18T03:00:00.000000Z', $result['paid_at']);
        $this->assertSame('2026-11-18T02:00:00.000000Z', $result['created_at']);
    }

    public function test_projection_rejects_missing_loaded_attributes_without_querying_a_database(): void
    {
        $this->expectException(LogicException::class);
        $this->mapper()->toAdminListItem(new Invoice());
    }
}
