<?php

namespace App\Services\Dashboard;

use App\DTOs\Dashboard\Responses\HostDashboardSnapshotResponse;
use App\DTOs\Dashboard\Responses\HostDashboardResponse;
use App\DTOs\Dashboard\HostDashboardPeriod;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\AppException;
use App\Exceptions\ErrorCode;
use App\Models\User;
use App\Repositories\Dashboard\HostDashboardRepository;
use Carbon\CarbonImmutable;

class HostDashboardQueryService
{
    public function __construct(private readonly HostDashboardRepository $repository) {}

    /** Query-free permission entrypoint for the HTTP boundary to call before raw validation. */
    public function assertCanView(User $actor): void
    {
        $this->authorizedHostId($actor);
    }

    /** Internal foundation only; not the complete Dashboard HTTP response. */
    public function getSnapshot(User $actor): HostDashboardSnapshotResponse
    {
        $id = $this->authorizedHostId($actor);
        $generatedAt = CarbonImmutable::now('UTC');
        return new HostDashboardSnapshotResponse($generatedAt, $this->repository->snapshot($id, $generatedAt));
    }

    public function getOverview(User $actor, ?string $fromMonth = null, ?string $toMonth = null): HostDashboardResponse
    {
        $id = $this->authorizedHostId($actor);
        $period = new HostDashboardPeriod(CarbonImmutable::now('UTC'), $fromMonth, $toMonth);
        return new HostDashboardResponse($period, $this->repository->overview($id, $period));
    }

    private function authorizedHostId(User $actor): string
    {
        $id = (string) $actor->getKey();
        if ($actor->role !== UserRole::HOST || $actor->status !== UserStatus::ACTIVE
            || preg_match('/\A[1-9][0-9]*\z/', $id) !== 1) {
            throw new AppException(ErrorCode::HOST_DASHBOARD_FORBIDDEN, 'Bạn không có quyền xem tổng quan Host.', 403);
        }
        return $id;
    }
}
