<?php

namespace Tests\Unit;

use App\DTOs\Invoice\Requests\AdminInvoiceFilterOptionsRequest;
use App\Mappers\EloquentObjectMapper;
use App\Mappers\Invoice\InvoiceMapper;
use App\Models\User;
use App\Validators\AttributeValidator;
use App\Validators\Invoice\AdminInvoiceValidator;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\ObjectMapper\ObjectMapper;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Validator\Validation;

final class AdminInvoiceHostOptionsContractTest extends TestCase
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
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($container);
        $this->validator = new AdminInvoiceValidator(new AttributeValidator(Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()));
        $this->mapper = new InvoiceMapper(new EloquentObjectMapper(new ObjectMapper(propertyAccessor: PropertyAccess::createPropertyAccessor())));
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->previous);
        parent::tearDown();
    }

    public function test_defaults_trimming_unicode_zero_and_whitelist(): void
    {
        $map = fn (array $query) => $this->mapper->toAdminFilterOptionsRequest($this->validator->validateFilterOptions($query))->toQuery();
        $this->assertSame(['type' => 'hosts', 'page' => 1, 'per_page' => 10], $map(['type' => ' hosts ', 'search' => ' ', 'ignored' => []]));
        $this->assertSame(['type' => 'hosts', 'search' => '0', 'page' => 2, 'per_page' => 50], $map(['type' => 'hosts', 'search' => ' 0 ', 'page' => '2', 'per_page' => '50']));
        $this->assertSame(str_repeat('ệ', 255), $map(['type' => 'hosts', 'search' => str_repeat('ệ', 255)])['search']);
        $this->assertSame(2147483647, $map(['type' => 'hosts', 'page' => '2147483647'])['page']);
    }

    public static function invalidQueries(): iterable
    {
        foreach (['type' => [null, '', [], true, 1, 'unsupported', 'HOSTS'],
            'search' => [[], true, 1, str_repeat('ệ', 256)],
            'page' => [null, '', ' ', [], true, 1.0, '01', '0', '-1', '+1', '1.0', '1e2', '2147483648'],
            'per_page' => [null, '', [], false, 1.0, '01', 0, 51],
            'host_id' => [null, '', 1, []]] as $field => $values) {
            foreach ($values as $index => $value) yield $field.'-'.$index => [array_replace(['type' => 'hosts'], [$field => $value]), $field];
        }
        yield 'missing-type' => [[], 'type'];
    }

    #[DataProvider('invalidQueries')]
    public function test_raw_values_are_validated_before_casting(array $query, string $field): void
    {
        try {
            $this->validator->validateFilterOptions($query);
            $this->fail('Invalid query accepted.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey($field, $error->errors());
        }
    }

    public function test_projection_only_reads_loaded_id_and_name(): void
    {
        $user = new User();
        $user->setRawAttributes(['id' => 17, 'name' => 'Tên hiện tại', 'email' => 'private@example.test']);
        $this->assertSame(['id' => 17, 'name' => 'Tên hiện tại'], $this->mapper->toAdminHostOption($user)->jsonSerialize());
        $user->setRawAttributes(['id' => 17]);
        $this->expectException(\LogicException::class);
        $this->mapper->toAdminHostOption($user);
    }

    public function test_typed_input_refuses_invalid_domain_state(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new AdminInvoiceFilterOptionsRequest(type: 'unsupported');
    }
}
