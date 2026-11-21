<?php

namespace Tests\Unit;

use App\DTOs\Dashboard\HostDashboardPeriod;
use App\DTOs\Dashboard\Requests\HostDashboardQueryInputRequest;
use App\DTOs\Dashboard\Requests\HostDashboardQueryRequest;
use App\Exceptions\AppException;
use App\Models\User;
use App\Repositories\Dashboard\HostDashboardRepository;
use App\Services\Dashboard\HostDashboardQueryService;
use App\Validators\AttributeValidator;
use App\Validators\Dashboard\HostDashboardValidator;
use DateTimeImmutable;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class HostDashboardValidatorTest extends TestCase
{
    private function attributes(): AttributeValidator
    {
        return new AttributeValidator(Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator());
    }

    private function validator(): HostDashboardValidator { return new HostDashboardValidator($this->attributes()); }

    public static function validQueries(): array
    {
        return [[[], null, null], [['from_month' => '2026-11', 'to_month' => '2026-11'], '2026-11', '2026-11'],
            [['from_month' => '2025-12', 'to_month' => '2026-11'], '2025-12', '2026-11'],
            [['from_month' => '0001-01', 'to_month' => '0001-12'], '0001-01', '0001-12'],
            [['from_month' => '9999-01', 'to_month' => '9999-12'], '9999-01', '9999-12'],
            [['from_month' => '2024-02', 'to_month' => '2024-02'], '2024-02', '2024-02'],
            [['from_month' => '2027-01', 'to_month' => '2027-12'], '2027-01', '2027-12']];
    }

    #[DataProvider('validQueries')]
    public function test_valid_query_preserves_labels_and_domain_period_agrees(array $query, ?string $from, ?string $to): void
    {
        $request = $this->validator()->validate(new HostDashboardQueryInputRequest($query));
        $this->assertSame($from, $request->fromMonth);
        $this->assertSame($to, $request->toMonth);
        $period = new HostDashboardPeriod(new DateTimeImmutable('2026-11-21T05:00:00Z'), $request->fromMonth, $request->toMonth);
        $this->assertSame($from ?? '2025-12', $period->fromMonth);
        $this->assertSame($to ?? '2026-11', $period->toMonth);
    }

    public static function invalidQueries(): array
    {
        $rows = [];
        $values = [null, '', ' ', 'null', '2026-1', ' 2026-01', '2026-01 ', "2026-01\n", "2026-01\0",
            '0000-01', '10000-01', '2026-00', '2026-13', 202601, 2026.01, false, true, [], ['2026-01'],
            ['nested' => '2026-01'], new \stdClass(), '２０２６-０１'];
        foreach ($values as $i => $value) {
            $rows['from '.$i] = [['from_month' => $value, 'to_month' => '2026-02'], ['from_month']];
            $rows['to '.$i] = [['from_month' => '2026-01', 'to_month' => $value], ['to_month']];
        }
        $rows['missing from'] = [['to_month' => '2026-01'], ['from_month']];
        $rows['missing to'] = [['from_month' => '2026-01'], ['to_month']];
        $rows['reverse'] = [['from_month' => '2026-02', 'to_month' => '2026-01'], ['to_month']];
        $rows['13 months'] = [['from_month' => '2025-11', 'to_month' => '2026-11'], ['to_month']];
        $rows['unknown only'] = [['host_id' => 'SENSITIVE_VALUE', 'timezone' => 'SENSITIVE_VALUE'], ['host_id', 'timezone']];
        $rows['unknown with pair'] = [['from_month' => '2026-01', 'to_month' => '2026-02', 'property_id' => 9], ['property_id']];
        $rows['unnormalized keys'] = [['from.month' => 'SENSITIVE_VALUE', 'to month' => 'SENSITIVE_VALUE'], ['from.month', 'to month']];
        $rows['numeric unknown'] = [[7 => 'SENSITIVE_VALUE'], [7]];
        $rows['numeric with bad from'] = [[7 => 'SENSITIVE_VALUE', 'from_month' => [], 'to_month' => '2026-01'], [7, 'from_month']];
        $rows['both invalid'] = [['from_month' => [], 'to_month' => 'wrong'], ['from_month', 'to_month']];
        return $rows;
    }

    #[DataProvider('invalidQueries')]
    public function test_invalid_input_returns_safe_field_errors_without_coercion(array $query, array $fields): void
    {
        try {
            $this->validator()->validate(new HostDashboardQueryInputRequest($query));
            $this->fail('Invalid query accepted.');
        } catch (AppException $e) {
            $this->assertSame(422, $e->status);
            $this->assertSame('HOST_DASHBOARD_INVALID_QUERY', $e->errorCode);
            $this->assertEqualsCanonicalizing($fields, array_keys($e->errors));
            foreach ($e->errors as $messages) {
                $this->assertCount(1, $messages);
                $this->assertIsString($messages[0]);
            }
            $this->assertStringNotContainsString('SENSITIVE_VALUE', $e->getMessage().json_encode($e->errors, JSON_THROW_ON_ERROR));
        }
    }

    public function test_raw_presence_and_explicit_symfony_group(): void
    {
        $absent = new HostDashboardQueryInputRequest();
        $explicit = new HostDashboardQueryInputRequest(['from_month' => null]);
        $this->assertFalse(array_key_exists('from_month', $absent->query));
        $this->assertTrue(array_key_exists('from_month', $explicit->query));
        $raw = new HostDashboardQueryInputRequest(['from_month' => ['bad'], 'to_month' => 202611]);
        $this->assertSame(['bad'], $raw->from_month);
        $this->assertSame(202611, $raw->to_month);
        $this->assertSame([], $this->attributes()->errors($raw, ['unrelated-flow']));
        $this->assertSame(['from_month', 'to_month'], array_keys($this->attributes()->errors($raw, [HostDashboardQueryInputRequest::VALIDATION_GROUP])));
    }

    public static function deniedActors(): array
    {
        return [['ADMIN', 'ACTIVE', 1], ['CUSTOMER', 'ACTIVE', 2], ['HOST', 'BLOCKED', 3],
            ['HOST', 'INACTIVE', 4], ['HOST', 'ACTIVE', null]];
    }

    #[DataProvider('deniedActors')]
    public function test_permission_helper_denies_without_any_repository_call(string $role, string $status, ?int $id): void
    {
        $repo = $this->createMock(HostDashboardRepository::class);
        $repo->expects($this->never())->method('snapshot');
        $repo->expects($this->never())->method('overview');
        $actor = new User(['role' => $role, 'status' => $status]); $actor->id = $id;
        try {
            (new HostDashboardQueryService($repo))->assertCanView($actor);
            $this->fail('Denied actor accepted.');
        } catch (AppException $e) {
            $this->assertSame(403, $e->status);
            $this->assertSame('HOST_DASHBOARD_FORBIDDEN', $e->errorCode);
            $this->assertSame([], $e->errors);
        }
    }

    public function test_validation_and_active_host_permission_work_without_a_laravel_application(): void
    {
        $originalApp = Facade::getFacadeApplication();
        Facade::setFacadeApplication(null);
        try {
            $repo = $this->createMock(HostDashboardRepository::class);
            $repo->expects($this->never())->method('snapshot');
            $repo->expects($this->never())->method('overview');
            $actor = new User(['role' => 'HOST', 'status' => 'ACTIVE']); $actor->id = 42;
            (new HostDashboardQueryService($repo))->assertCanView($actor);
            $request = $this->validator()->validate(new HostDashboardQueryInputRequest(['from_month' => '2026-01', 'to_month' => '2026-12']));
            $this->assertSame('2026-01', $request->fromMonth);
            $this->assertNull(Facade::getFacadeApplication());
        } finally { Facade::setFacadeApplication($originalApp); }
    }

    public static function invalidTypedRequests(): array
    {
        return [[null, '2026-01'], ['2026-01', null], ['', '2026-01'], ['0000-01', '0000-02'],
            ['2026-02', '2026-01'], ['2025-11', '2026-11'], ['2026-01', '2026-1']];
    }

    #[DataProvider('invalidTypedRequests')]
    public function test_direct_typed_request_preserves_internal_invariants(?string $from, ?string $to): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new HostDashboardQueryRequest($from, $to);
    }
}
