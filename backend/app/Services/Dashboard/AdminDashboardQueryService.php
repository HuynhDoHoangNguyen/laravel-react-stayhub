<?php

namespace App\Services\Dashboard;

use App\DTOs\Dashboard\Responses\AdminDashboardResponse;
use App\Enums\BookingStatus;
use Carbon\CarbonImmutable;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\AppException;
use App\Exceptions\ErrorCode;
use App\Models\User;
use App\Repositories\Dashboard\AdminDashboardRepository;

class AdminDashboardQueryService
{
    public function __construct(private readonly AdminDashboardRepository $repository) {}

    public function getOverview(User $actor): AdminDashboardResponse
    {
        if ($actor->role !== UserRole::ADMIN || $actor->status !== UserStatus::ACTIVE) {
            throw new AppException(ErrorCode::ADMIN_DASHBOARD_FORBIDDEN, 'Bạn không có quyền xem tổng quan hệ thống.', 403);
        }
        $asOf = CarbonImmutable::now('UTC');
        $firstMonth = $asOf->setTimezone('Asia/Ho_Chi_Minh')->startOfMonth()->subMonths(11);
        $months = [];
        for ($index = 0; $index < 12; $index++) {
            $start = $firstMonth->addMonths($index);
            $months[] = ['month' => $start->format('Y-m'), 'start' => $start->getTimestamp(),
                'end' => $start->addMonth()->getTimestamp()];
        }
        $facts = $this->repository->aggregate($months, $asOf->format('U.u'));
        $statuses = array_map(static fn (BookingStatus $status) => [
            'status' => $status->value, 'count' => (int) $facts->{'booking_'.strtolower($status->value)},
        ], BookingStatus::cases());
        $revenue = [];
        foreach ($months as $index => $month) {
            $revenue[] = ['month' => $month['month'], 'total_amount' => $facts->{'revenue_month_'.$index}];
        }

        return new AdminDashboardResponse(
            (int) $facts->users_count, (int) $facts->hosts_count, (int) $facts->properties_count,
            (int) $facts->rooms_count, (int) $facts->bookings_count, $facts->revenue_total,
            (int) $facts->excluded_paid_invoices_count, $asOf->toISOString(), $statuses, $revenue,
        );
    }
}
