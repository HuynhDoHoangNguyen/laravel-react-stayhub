<?php

namespace Tests\Unit;

use App\DTOs\Statistics\AdminStatisticsPeriod;
use App\DTOs\Statistics\Requests\AdminStatisticsQueryInputRequest;
use App\DTOs\Statistics\Requests\AdminStatisticsQueryRequest;
use App\DTOs\Statistics\Responses\AdminStatisticsResponse;
use App\Enums\BookingStatus;
use App\Exceptions\AppException;
use App\Models\User;
use App\Repositories\Statistics\AdminStatisticsRepository;
use App\Services\Statistics\AdminStatisticsQueryService;
use App\Validators\AttributeValidator;
use App\Validators\Statistics\AdminStatisticsValidator;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use UnexpectedValueException;

final class AdminStatisticsContractTest extends TestCase
{
    private function validator(): AdminStatisticsValidator
    {
        return new AdminStatisticsValidator(new AttributeValidator(Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()));
    }

    private function actor(string $role = 'ADMIN', string $status = 'ACTIVE'): User
    {
        $actor = new User();
        $actor->setRawAttributes(compact('role', 'status'));
        return $actor;
    }

    private function facts(int $months = 1): object
    {
        $facts = ['missing_paid_at' => '0', 'summary_count' => '0', 'summary_revenue' => '0.00'];
        foreach (['summary', ...array_map(fn ($i) => 'month_'.$i, range(0, $months - 1))] as $prefix) {
            $facts[$prefix.'_count'] = '0'; $facts[$prefix.'_revenue'] = '0.00';
            foreach (BookingStatus::cases() as $status) { $facts[$prefix.'_'.strtolower($status->value)] = '0'; }
        }
        return (object) $facts;
    }

    public static function deniedActors(): array
    {
        return [['HOST', 'ACTIVE'], ['CUSTOMER', 'ACTIVE'], ['ADMIN', 'BLOCKED'], ['ADMIN', 'INACTIVE']];
    }

    #[DataProvider('deniedActors')]
    public function test_direct_service_denies_before_invalid_query_or_repository(string $role, string $status): void
    {
        $repository = $this->createMock(AdminStatisticsRepository::class);
        $repository->expects($this->never())->method('aggregate');
        try {
            (new AdminStatisticsQueryService($repository, $this->validator()))->getStatistics($this->actor($role, $status), new AdminStatisticsQueryInputRequest(['from_month' => []]));
            $this->fail('Denied actor accepted.');
        } catch (AppException $error) {
            $this->assertSame(403, $error->status);
            $this->assertSame('ADMIN_STATISTICS_FORBIDDEN', $error->errorCode);
        }
    }

    public function test_invalid_authorized_query_never_reaches_repository(): void
    {
        $repository = $this->createMock(AdminStatisticsRepository::class);
        $repository->expects($this->never())->method('aggregate');
        try {
            (new AdminStatisticsQueryService($repository, $this->validator()))->getStatistics($this->actor(), new AdminStatisticsQueryInputRequest(['as_of' => 'anything']));
            $this->fail('Invalid query accepted.');
        } catch (AppException $e) {
            $this->assertSame(422, $e->status);
            $this->assertSame('ADMIN_STATISTICS_INVALID_QUERY', $e->errorCode);
        }
    }

    public function test_clock_is_captured_once_and_survives_month_change_during_repository_call(): void
    {
        CarbonImmutable::setTestNow('2026-11-30T16:59:59.999999Z');
        try {
            $repository = $this->createMock(AdminStatisticsRepository::class);
            $repository->expects($this->once())->method('aggregate')->willReturnCallback(function (AdminStatisticsPeriod $period) {
                $this->assertSame('2025-12', $period->fromMonth);
                $this->assertSame('2026-11', $period->toMonth);
                CarbonImmutable::setTestNow('2026-11-30T17:00:01Z');
                return $this->facts(12);
            });
            $data = (new AdminStatisticsQueryService($repository, $this->validator()))->getStatistics($this->actor(), new AdminStatisticsQueryInputRequest())->jsonSerialize();
            $this->assertSame('2026-11-30T16:59:59.999999Z', $data['generated_at']);
            $this->assertSame('2026-11', $data['monthly'][11]['month']);
        } finally { CarbonImmutable::setTestNow(); }
    }

    public function test_projection_keeps_large_decimal_and_safe_integer_counts_without_pii_or_envelope(): void
    {
        $facts = $this->facts();
        foreach (['summary', 'month_0'] as $prefix) {
            $facts->{$prefix.'_revenue'} = '9007199254740993.01';
            $facts->{$prefix.'_count'} = '9007199254740991';
            $facts->{$prefix.'_pending'} = '9007199254740991';
        }
        $facts->email = 'must-not-escape@example.test';
        $period = new AdminStatisticsPeriod(new AdminStatisticsQueryRequest('2026-11', '2026-11'), new DateTimeImmutable('2026-11-19T05:00:00Z'));
        $data = json_decode(json_encode(new AdminStatisticsResponse($period, $facts), JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(['period', 'report_timezone', 'generated_at', 'summary', 'booking_status_counts', 'monthly', 'warnings'], array_keys($data));
        $this->assertSame(['bookings_count' => 9007199254740991, 'revenue_total' => '9007199254740993.01'], $data['summary']);
        $this->assertSame(['PENDING', 'CONFIRMED', 'CHECKED_IN', 'COMPLETED', 'CANCELLED', 'REJECTED'], array_column($data['booking_status_counts'], 'status'));
        $this->assertSame('Asia/Ho_Chi_Minh', $data['report_timezone']);
        $this->assertStringNotContainsString('must-not-escape', json_encode($data));
    }

    public static function malformedFacts(): array
    {
        $cases = [];
        foreach ([null, -1, 1.0, true, [], '01', '1e2', '9007199254740992', '9223372036854775808'] as $i => $bad) {
            $cases['count '.$i] = ['missing_paid_at', $bad];
        }
        foreach ([null, 1.25, '1.2', '01.25', '-1.25', 'NaN', '1e2', []] as $i => $bad) {
            $cases['money '.$i] = ['summary_revenue', $bad];
        }
        $cases['status sum'] = ['summary_pending', '1'];
        $cases['monthly money sum'] = ['month_0_revenue', '0.01'];
        return $cases;
    }

    #[DataProvider('malformedFacts')]
    public function test_malformed_or_inconsistent_facts_fail_closed(string $field, mixed $value): void
    {
        $facts = $this->facts(); $facts->{$field} = $value;
        $this->expectException(UnexpectedValueException::class);
        new AdminStatisticsResponse(new AdminStatisticsPeriod(new AdminStatisticsQueryRequest('2026-11', '2026-11'), new DateTimeImmutable('2026-11-19T05:00:00Z')), $facts);
    }

    public function test_monthly_status_distribution_must_match_summary_not_only_overall_counts(): void
    {
        $facts = $this->facts();
        $facts->summary_count = $facts->month_0_count = '1';
        $facts->summary_pending = $facts->month_0_confirmed = '1';
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Phân bố trạng thái');
        new AdminStatisticsResponse(new AdminStatisticsPeriod(new AdminStatisticsQueryRequest('2026-11', '2026-11'), new DateTimeImmutable('2026-11-19T05:00:00Z')), $facts);
    }
}
