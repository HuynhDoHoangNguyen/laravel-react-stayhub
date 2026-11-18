<?php

namespace Tests\Unit;

use App\DTOs\Invoice\Requests\AdminInvoiceFilterOptionsRequest;
use App\Mappers\EloquentObjectMapper;
use App\Mappers\Invoice\InvoiceMapper;
use App\Models\Property;
use App\Validators\AttributeValidator;
use App\Validators\Invoice\AdminInvoiceValidator;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ObjectMapper\ObjectMapper;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Validator\Validation;

final class AdminInvoicePropertyOptionsContractTest extends TestCase
{
    private mixed $previous;
    private AdminInvoiceValidator $validator;
    private InvoiceMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previous = Facade::getFacadeApplication();
        $container = new Container();
        $container->instance('validator', new Factory(new Translator(new ArrayLoader(), 'vi')));
        Facade::clearResolvedInstances(); Facade::setFacadeApplication($container);
        $this->validator = new AdminInvoiceValidator(new AttributeValidator(Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()));
        $this->mapper = new InvoiceMapper(new EloquentObjectMapper(new ObjectMapper(propertyAccessor: PropertyAccess::createPropertyAccessor())));
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances(); Facade::setFacadeApplication($this->previous);
        parent::tearDown();
    }

    public function test_defaults_presence_normalization_limits_and_whitelist(): void
    {
        $map = fn (array $q) => $this->mapper->toAdminFilterOptionsRequest($this->validator->validateFilterOptions($q))->toQuery();
        foreach ([[], ['host_id' => null], ['host_id' => ' ']] as $extra) {
            $this->assertSame(['type' => 'properties', 'page' => 1, 'per_page' => 10], $map(['type' => 'properties', ...$extra]));
        }
        $this->assertSame(['type' => 'properties', 'search' => '0', 'page' => 2, 'per_page' => 50, 'host_id' => 17],
            $map(['type' => ' properties ', 'search' => ' 0 ', 'host_id' => ' 17 ', 'page' => '2', 'per_page' => '50', 'ignored' => []]));
        $query = $map(['type' => 'properties', 'host_id' => '9007199254740991', 'page' => '2147483647', 'search' => str_repeat('ệ', 255)]);
        $this->assertSame(9007199254740991, $query['host_id']);
        $this->assertSame(2147483647, $query['page']);
        $this->assertSame(255, mb_strlen($query['search']));
    }

    public static function invalidQueries(): iterable
    {
        foreach (['host_id' => [[], new \stdClass(), true, false, 1.0, 0, -1, '01', '+1', '1.0', '1e2', '9007199254740992'],
            'page' => [null, '', [], true, 1.0, '01', 0, '2147483648'],
            'per_page' => [null, '', [], false, 0, 51], 'search' => [[], true, str_repeat('ệ', 256)]] as $field => $values) {
            foreach ($values as $i => $value) yield $field.'-'.$i => [['type' => 'properties', $field => $value], $field];
        }
    }

    #[DataProvider('invalidQueries')]
    public function test_validation_precedes_conversion(array $query, string $field): void
    {
        try { $this->validator->validateFilterOptions($query); $this->fail('Invalid input accepted.'); }
        catch (ValidationException $error) { $this->assertArrayHasKey($field, $error->errors()); }
    }

    public function test_property_projection_is_exact_and_refuses_missing_attributes(): void
    {
        $property = new Property();
        $property->setRawAttributes(['id' => 21, 'name' => 'Cơ sở hiện tại', 'host_id' => 17, 'address' => 'Private']);
        $this->assertSame(['id' => 21, 'name' => 'Cơ sở hiện tại', 'host_id' => 17], $this->mapper->toAdminPropertyOption($property)->jsonSerialize());
        $property->setRawAttributes(['id' => 21, 'name' => 'Cơ sở']);
        $this->expectException(\LogicException::class);
        $this->mapper->toAdminPropertyOption($property);
    }

    public static function invalidTyped(): iterable
    {
        yield [['type' => 'hosts', 'hostId' => 17]];
        yield [['type' => 'properties', 'hostId' => 0]];
        yield [['type' => 'properties', 'hostId' => 9007199254740992]];
        yield [['type' => 'properties', 'page' => 0]];
        yield [['type' => 'properties', 'search' => ' untrimmed ']];
    }

    #[DataProvider('invalidTyped')]
    public function test_typed_input_enforces_domain_invariants(array $args): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new AdminInvoiceFilterOptionsRequest(...$args);
    }
}
