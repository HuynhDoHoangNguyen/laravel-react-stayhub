<?php

namespace App\Services\Statistics;

use App\DTOs\Statistics\AdminStatisticsPeriod;
use App\DTOs\Statistics\Requests\AdminStatisticsQueryInputRequest;
use App\DTOs\Statistics\Responses\AdminStatisticsResponse;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\AppException;
use App\Exceptions\ErrorCode;
use App\Models\User;
use App\Repositories\Statistics\AdminStatisticsRepository;
use App\Validators\Statistics\AdminStatisticsValidator;
use Carbon\CarbonImmutable;

class AdminStatisticsQueryService
{
    public function __construct(private readonly AdminStatisticsRepository $repository, private readonly AdminStatisticsValidator $validator) {}

    public function getStatistics(User $actor, AdminStatisticsQueryInputRequest $input): AdminStatisticsResponse
    {
        if ($actor->role !== UserRole::ADMIN || $actor->status !== UserStatus::ACTIVE) {
            throw new AppException(ErrorCode::ADMIN_STATISTICS_FORBIDDEN, 'Bạn không có quyền xem thống kê hệ thống.', 403);
        }
        $generatedAt = CarbonImmutable::now('UTC');
        $period = new AdminStatisticsPeriod($this->validator->validate($input), $generatedAt);
        return new AdminStatisticsResponse($period, $this->repository->aggregate($period));
    }
}
