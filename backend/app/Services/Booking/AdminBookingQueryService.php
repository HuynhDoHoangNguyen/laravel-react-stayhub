<?php

declare(strict_types=1);

namespace App\Services\Booking;

use App\DTOs\Booking\Requests\AdminBookingFilterOptionsRequest;
use App\DTOs\Booking\Requests\AdminBookingQueryRequest;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\AppException;
use App\Exceptions\ErrorCode;
use App\Mappers\Booking\AdminBookingMapper;
use App\Models\Booking;
use App\Models\Property;
use App\Models\User;
use App\Repositories\Booking\AdminBookingRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class AdminBookingQueryService
{
    public function __construct(
        private readonly AdminBookingRepository $repository,
        private readonly AdminBookingMapper $mapper,
    ) {}

    public function getAdminBookings(?User $actor, AdminBookingQueryRequest $filters): LengthAwarePaginator
    {
        $this->authorize($actor);
        $page = $this->repository->paginate($filters);
        $page->setCollection($page->getCollection()->map(fn (Booking $booking) => $this->mapper->toListItem($booking)));

        return $page;
    }

    public function getFilterOptions(?User $actor, AdminBookingFilterOptionsRequest $filters): LengthAwarePaginator
    {
        $this->authorize($actor);
        $page = $this->repository->paginateOptions($filters);
        $page->setCollection($page->getCollection()->map(fn (User|Property $option) => $this->mapper->toFilterOption($option)));

        return $page;
    }

    public function authorize(?User $actor): void
    {
        $persisted = $actor !== null && $actor->exists && is_int($actor->id) && $actor->id > 0
            ? $this->repository->findActor($actor->id) : null;
        if ($persisted === null) {
            $this->forbidden();
        }
        if ($persisted->status !== UserStatus::ACTIVE) {
            $blocked = $persisted->status === UserStatus::BLOCKED;
            throw new AppException($blocked ? 'ACCOUNT_BLOCKED' : 'ACCOUNT_INACTIVE', $blocked
                ? 'Tài khoản của bạn đã bị khóa. Vui lòng liên hệ quản trị viên.'
                : 'Tài khoản của bạn chưa được kích hoạt. Vui lòng liên hệ quản trị viên.', 403);
        }
        if ($persisted->role !== UserRole::ADMIN) {
            $this->forbidden();
        }
    }

    private function forbidden(): never
    {
        throw new AppException(ErrorCode::ADMIN_BOOKING_FORBIDDEN, 'Bạn không có quyền xem danh sách đặt phòng toàn hệ thống.', 403);
    }
}
