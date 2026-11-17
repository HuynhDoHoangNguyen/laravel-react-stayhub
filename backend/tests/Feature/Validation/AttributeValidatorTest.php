<?php

namespace Tests\Feature\Validation;

use App\DTOs\Invoice\Requests\InvoiceCreateInputRequest;
use App\Validators\AttributeValidator;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AttributeValidatorTest extends TestCase
{
    public function test_container_resolves_singleton_engines_and_adapter_via_constructor_di(): void
    {
        $this->assertInstanceOf(ValidatorInterface::class, app(ValidatorInterface::class));
        $this->assertSame(app(ValidatorInterface::class), app(ValidatorInterface::class));
        $this->assertInstanceOf(ObjectMapperInterface::class, app(ObjectMapperInterface::class));
        $this->assertSame(app(ObjectMapperInterface::class), app(ObjectMapperInterface::class));
        $this->assertInstanceOf(AttributeValidator::class, app(AttributeValidator::class));
    }

    public function test_readonly_input_dto_executes_constraints_before_any_integer_coercion(): void
    {
        $adapter = app(AttributeValidator::class);
        foreach ([1, '7', (string) PHP_INT_MAX, PHP_INT_MAX, 1.0] as $id) {
            $dto = new InvoiceCreateInputRequest($id);
            $this->assertSame([], $adapter->errors($dto));
            $this->assertSame($id, $dto->bookingId);
        }
        foreach ([null, 0, -1, '01', '1e2', '9223372036854775808', '1.0', 1.5, false, [], ['secret']] as $id) {
            $dto = new InvoiceCreateInputRequest($id);
            $this->assertSame(['bookingId' => ['Mã đơn đặt phòng không hợp lệ.']], $adapter->errors($dto));
            $this->assertSame($id, $dto->bookingId);
        }
    }

    public function test_http_adapter_uses_explicit_public_field_alias_and_safe_message(): void
    {
        $adapter = app(AttributeValidator::class);
        $adapter->validateHttp(new InvoiceCreateInputRequest(7));
        try {
            $adapter->validateHttp(new InvoiceCreateInputRequest(['sensitive-value']), fieldMap: ['bookingId' => 'booking_id']);
            $this->fail('Expected validation rejection.');
        } catch (ValidationException $error) {
            $this->assertSame(422, $error->status);
            $this->assertSame(['booking_id' => ['Mã đơn đặt phòng không hợp lệ.']], $error->errors());
            $this->assertStringNotContainsString('sensitive-value', json_encode($error->errors()));
        }
    }

    public function test_domain_adapter_preserves_caller_message_without_http_envelope(): void
    {
        app(AttributeValidator::class)->validateDomain(new InvoiceCreateInputRequest(7), 'Existing public message');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Existing public message');
        app(AttributeValidator::class)->validateDomain(new InvoiceCreateInputRequest(0), 'Existing public message');
    }

    public function test_canonical_identifier_attributes_preserve_read_and_create_error_contracts(): void
    {
        $validator = app(\App\Validators\Invoice\InvoiceValidator::class);
        foreach ([[1, 1], ['7', 7], [1.0, 1], [(string) PHP_INT_MAX, PHP_INT_MAX]] as [$raw, $expected]) {
            $this->assertSame($expected, $validator->validateInvoiceId($raw));
            $this->assertSame($expected, $validator->validateBookingId($raw));
        }
        foreach ([null, false, true, [], new \stdClass, '0', '-1', '01', '+1', '1e2', '1.0', ' 1', '1 ', (string) PHP_INT_MAX.'0', 1.5, INF, NAN] as $raw) {
            try {
                $validator->validateInvoiceId($raw);
                $this->fail('Invalid read ID accepted.');
            } catch (\App\Exceptions\AppException $error) {
                $this->assertSame(404, $error->status);
                $this->assertSame('INVOICE_NOT_FOUND', $error->errorCode);
                $this->assertSame('Không tìm thấy hóa đơn.', $error->getMessage());
            }
            try {
                $validator->validateBookingId($raw);
                $this->fail('Invalid booking ID accepted.');
            } catch (InvalidArgumentException $error) {
                $this->assertSame('Mã đơn đặt phòng không hợp lệ.', $error->getMessage());
            }
        }
    }

    public function test_query_attributes_preserve_presence_scalars_defaults_and_field_messages(): void
    {
        $validator = app(\App\Validators\Invoice\InvoiceValidator::class);
        $this->assertSame([], $validator->validateQuery(['unknown' => 'ignored']));
        foreach ([null, '', 'invalid', 1, 1.5, false, true] as $value) {
            $this->assertSame(['page' => $value, 'per_page' => $value], $validator->validateQuery(['page' => $value, 'per_page' => $value]));
        }
        foreach (['status', 'search'] as $field) {
            foreach ([[], 1, false, new \stdClass] as $value) {
                try {
                    $validator->validateQuery([$field => $value]);
                    $this->fail('Malformed query accepted.');
                } catch (ValidationException $error) {
                    $this->assertSame([$field => [__('validation.string', ['attribute' => $field])]], $error->errors());
                }
            }
        }
        foreach (['page', 'per_page'] as $field) {
            foreach ([[], new \stdClass] as $value) {
                try {
                    $validator->validateQuery([$field => $value]);
                    $this->fail('Nonscalar pagination accepted.');
                } catch (ValidationException $error) {
                    $this->assertSame([$field => ["Tham số {$field} không hợp lệ."]], $error->errors());
                }
            }
        }
    }


    public function test_unified_validator_namespace_autoloads_without_legacy_aliases(): void
    {
        foreach ([AttributeValidator::class, \App\Validators\Constraints\CanonicalPositiveId::class, \App\Validators\Constraints\CanonicalPositiveIdValidator::class] as $class) {
            $this->assertTrue(class_exists($class));
        }
        foreach (['App\\Validation\\AttributeValidator', 'App\\Validation\\Constraints\\CanonicalPositiveId', 'App\\Validation\\Constraints\\CanonicalPositiveIdValidator'] as $oldClass) {
            $this->assertFalse(class_exists($oldClass), 'No alias or forwarding class may retain the old namespace.');
        }
        $this->assertInstanceOf(AttributeValidator::class, app(AttributeValidator::class));
    }

    public function test_renamed_constraint_resolves_its_validator_and_dto_attributes_through_symfony(): void
    {
        $constraint = new \App\Validators\Constraints\CanonicalPositiveId(message: 'Canonical ID required.', groups: ['namespace-check']);
        $this->assertSame(\App\Validators\Constraints\CanonicalPositiveIdValidator::class, $constraint->validatedBy());
        $engine = app(ValidatorInterface::class);
        $this->assertCount(0, $engine->validate('7', $constraint, ['namespace-check']));
        $violations = $engine->validate('01', $constraint, ['namespace-check']);
        $this->assertCount(1, $violations);
        $this->assertSame('Canonical ID required.', $violations[0]->getMessage());
        $property = new \ReflectionProperty(InvoiceCreateInputRequest::class, 'bookingId');
        $attributes = $property->getAttributes(\App\Validators\Constraints\CanonicalPositiveId::class);
        $this->assertCount(1, $attributes);
        $this->assertInstanceOf(\App\Validators\Constraints\CanonicalPositiveId::class, $attributes[0]->newInstance());
        $this->assertSame([], app(AttributeValidator::class)->errors(new InvoiceCreateInputRequest('7')));
        $this->assertSame(['bookingId' => ['Mã đơn đặt phòng không hợp lệ.']], app(AttributeValidator::class)->errors(new InvoiceCreateInputRequest('01')));
    }
}
