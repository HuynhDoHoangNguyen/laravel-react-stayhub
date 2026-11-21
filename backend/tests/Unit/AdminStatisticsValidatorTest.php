<?php

namespace Tests\Unit;

use App\DTOs\Statistics\Requests\AdminStatisticsQueryInputRequest;
use App\DTOs\Statistics\Requests\AdminStatisticsQueryRequest;
use App\Exceptions\AppException;
use App\Validators\AttributeValidator;
use App\Validators\Statistics\AdminStatisticsValidator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class AdminStatisticsValidatorTest extends TestCase
{
    private function validator(): AdminStatisticsValidator
    {
        return new AdminStatisticsValidator(new AttributeValidator(Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()));
    }

    public static function validQueries(): array
    {
        return [[[], null, null], [['from_month' => '2026-11', 'to_month' => '2026-11'], '2026-11', '2026-11'],
            [['from_month' => '2025-12', 'to_month' => '2026-11'], '2025-12', '2026-11'],
            [['from_month' => '0001-01', 'to_month' => '0001-12'], '0001-01', '0001-12'],
            [['from_month' => '9999-01', 'to_month' => '9999-12'], '9999-01', '9999-12']];
    }

    #[DataProvider('validQueries')]
    public function test_valid_query_preserves_labels(array $query, ?string $from, ?string $to): void
    {
        $result = $this->validator()->validate(new AdminStatisticsQueryInputRequest($query));
        $this->assertSame($from, $result->fromMonth);
        $this->assertSame($to, $result->toMonth);
    }

    public static function invalidQueries(): array
    {
        $rows = [];
        foreach ([null, '', ' ', '2026-1', ' 2026-01', '2026-01 ', "2026-01\n", '0000-01', '10000-01', '2026-00', '2026-13',
            202601, 2026.01, false, true, [], ['2026-01'], new \stdClass(), '２０２６-０１'] as $i => $value) {
            $rows['from '.$i] = [['from_month' => $value, 'to_month' => '2026-02'], ['from_month']];
            $rows['to '.$i] = [['from_month' => '2026-01', 'to_month' => $value], ['to_month']];
        }
        $rows['missing from'] = [['to_month' => '2026-01'], ['from_month']];
        $rows['missing to'] = [['from_month' => '2026-01'], ['to_month']];
        $rows['reversed'] = [['from_month' => '2026-02', 'to_month' => '2026-01'], ['to_month']];
        $rows['13 months'] = [['from_month' => '2025-11', 'to_month' => '2026-11'], ['to_month']];
        $rows['unknown only'] = [['timezone' => 'UTC', 'as_of' => null], ['timezone', 'as_of']];
        $rows['unknown with pair'] = [['from_month' => '2026-01', 'to_month' => '2026-02', 'status' => 'PAID'], ['status']];
        $rows['both bad without cross error'] = [['from_month' => [], 'to_month' => 'wrong'], ['from_month', 'to_month']];
        return $rows;
    }

    #[DataProvider('invalidQueries')]
    public function test_invalid_query_has_safe_field_errors(array $query, array $fields): void
    {
        try {
            $this->validator()->validate(new AdminStatisticsQueryInputRequest($query));
            $this->fail('Invalid query accepted.');
        } catch (AppException $error) {
            $this->assertSame('ADMIN_STATISTICS_INVALID_QUERY', $error->errorCode);
            $this->assertSame(422, $error->status);
            $this->assertEqualsCanonicalizing($fields, array_keys($error->errors));
            foreach ($error->errors as $messages) { $this->assertCount(1, $messages); }
        }
    }

    public static function invalidTypedQueries(): array
    {
        return [[null, '2026-01'], ['2026-01', null], ['0000-01', '0000-02'], ['2026-02', '2026-01'],
            ['2025-11', '2026-11'], ['2026-01 ', '2026-02']];
    }

    #[DataProvider('invalidTypedQueries')]
    public function test_typed_input_cannot_bypass_invariants(?string $from, ?string $to): void
    {
        $this->expectException(InvalidArgumentException::class);
        new AdminStatisticsQueryRequest($from, $to);
    }
}
