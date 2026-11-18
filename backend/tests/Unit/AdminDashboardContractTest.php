<?php

namespace Tests\Unit;

use Carbon\CarbonImmutable;
use App\Enums\BookingStatus;
use App\DTOs\Dashboard\Responses\AdminDashboardResponse;
use App\Exceptions\AppException;
use App\Models\User;
use App\Repositories\Dashboard\AdminDashboardRepository;
use App\Services\Dashboard\AdminDashboardQueryService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AdminDashboardContractTest extends TestCase
{
    public static function deniedActors(): array
    {
        return [['HOST', 'ACTIVE'], ['CUSTOMER', 'ACTIVE'], ['ADMIN', 'BLOCKED'], ['ADMIN', 'INACTIVE']];
    }

    #[DataProvider('deniedActors')]
    public function test_direct_service_denies_before_repository(string $role, string $status): void
    {
        $repository = $this->createMock(AdminDashboardRepository::class);
        $repository->expects($this->never())->method('aggregate');
        $actor = new User();
        $actor->setRawAttributes(['role' => $role, 'status' => $status]);
        try {
            (new AdminDashboardQueryService($repository))->getOverview($actor);
            $this->fail('Unauthorized actor accepted.');
        } catch (AppException $error) {
            $this->assertSame(403, $error->status);
            $this->assertSame('ADMIN_DASHBOARD_FORBIDDEN', $error->errorCode);
        }
    }

    public function test_projection_keeps_large_decimal_without_numeric_conversion(): void
    {
        $statuses = array_map(fn (BookingStatus $status) => ['status' => $status->value, 'count' => 1], BookingStatus::cases());
        $revenue = [['month' => '2026-11', 'total_amount' => '9007199254740993.01']];
        $dto = new AdminDashboardResponse(7, 3, 4, 4, 6, '9007199254740993.01', 2, '2026-11-18T07:00:00.000000Z', $statuses, $revenue);
        $this->assertSame([
            'summary' => ['users_count' => 7, 'hosts_count' => 3, 'properties_count' => 4,
                'rooms_count' => 4, 'bookings_count' => 6, 'revenue_total' => '9007199254740993.01'],
            'excluded_paid_invoices_count' => 2, 'generated_at' => '2026-11-18T07:00:00.000000Z',
            'booking_status_counts' => $statuses, 'revenue_by_month' => $revenue, 'report_timezone' => 'Asia/Ho_Chi_Minh',
        ], json_decode(json_encode($dto, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR));
    }

    public static function periodBoundaries(): array
    {
        return [
            ['2026-11-30T17:00:00Z', '2026-01', '2026-12', '2025-12-31T17:00:00+00:00', '2026-12-31T17:00:00+00:00'],
            ['2024-02-29T16:59:59Z', '2023-03', '2024-02', '2023-02-28T17:00:00+00:00', '2024-02-29T17:00:00+00:00'],
        ];
    }

    #[DataProvider('periodBoundaries')]
    public function test_report_period_uses_one_immutable_local_month_anchor(string $asOf, string $first, string $last, string $startUtc, string $endUtc): void
    {
        CarbonImmutable::setTestNow($asOf);
        try {
            $repository = $this->createMock(AdminDashboardRepository::class);
            $repository->expects($this->once())->method('aggregate')->with(
                $this->callback(function (array $months) use ($first, $last, $startUtc, $endUtc): bool {
                    $this->assertCount(12, $months);
                    $this->assertSame($first, $months[0]['month']);
                    $this->assertSame($last, $months[11]['month']);
                    $this->assertSame($startUtc, gmdate('c', $months[0]['start']));
                    $this->assertSame($endUtc, gmdate('c', $months[11]['end']));
                    for ($i = 1; $i < 12; $i++) $this->assertSame($months[$i - 1]['end'], $months[$i]['start']);
                    return true;
                }), CarbonImmutable::parse($asOf)->format('U.u'),
            )->willReturnCallback(function () {
                $facts = ['users_count' => 1, 'hosts_count' => 0, 'properties_count' => 0, 'rooms_count' => 0,
                    'bookings_count' => 0, 'revenue_total' => '0.00', 'excluded_paid_invoices_count' => 0];
                foreach (BookingStatus::cases() as $status) $facts['booking_'.strtolower($status->value)] = 0;
                for ($i = 0; $i < 12; $i++) $facts['revenue_month_'.$i] = '0.00';
                return (object) $facts;
            });
            $actor = new User();
            $actor->setRawAttributes(['role' => 'ADMIN', 'status' => 'ACTIVE']);
            $dto = (new AdminDashboardQueryService($repository))->getOverview($actor);
            $this->assertSame(CarbonImmutable::parse($asOf)->toISOString(), $dto->generatedAt);
            $this->assertSame($last, $dto->revenueByMonth[11]['month']);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

}
