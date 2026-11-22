<?php

namespace App\Repositories\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class AdminUserRepository
{
    /**
     * Truy vấn danh sách người dùng có phân trang kèm thông tin chấp thuận hợp đồng và số lượng cơ sở/đặt phòng.
     *
     * @param array{keyword: ?string, role: ?string, status: ?string, host_approval: ?string, page: int, per_page: int} $filters
     */
    public function paginateUsers(array $filters): LengthAwarePaginator
    {
        $query = User::query()
            ->withCount(['properties', 'bookings']);

        if (! empty($filters['keyword'])) {
            $kw = $filters['keyword'];
            $query->where(function (Builder $q) use ($kw): void {
                $q->where('name', 'like', "%{$kw}%")
                    ->orWhere('email', 'like', "%{$kw}%")
                    ->orWhere('phone', 'like', "%{$kw}%");
            });
        }

        if (! empty($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['host_approval'])) {
            $query->where('role', UserRole::HOST->value);
            if ($filters['host_approval'] === 'PENDING') {
                $query->whereNull('email_verified_at');
            } elseif ($filters['host_approval'] === 'APPROVED') {
                $query->whereNotNull('email_verified_at');
            }
        }

        return $query
            ->orderByRaw("CASE WHEN role = 'HOST' AND email_verified_at IS NULL THEN 0 ELSE 1 END ASC")
            ->orderByDesc('id')
            ->paginate($filters['per_page'], ['*'], 'page', $filters['page']);
    }

    /**
     * Thống kê tổng quan số lượng người dùng phục vụ các thẻ KPI trên trang /admin/users.
     *
     * @return array{total_users: int, pending_hosts: int, approved_hosts: int, total_customers: int, active_users: int, blocked_users: int}
     */
    public function getSummaryCounts(): array
    {
        return [
            'total_users' => User::query()->count(),
            'pending_hosts' => User::query()
                ->where('role', UserRole::HOST->value)
                ->whereNull('email_verified_at')
                ->count(),
            'approved_hosts' => User::query()
                ->where('role', UserRole::HOST->value)
                ->whereNotNull('email_verified_at')
                ->count(),
            'total_customers' => User::query()
                ->where('role', UserRole::CUSTOMER->value)
                ->count(),
            'active_users' => User::query()
                ->where('status', UserStatus::ACTIVE->value)
                ->count(),
            'blocked_users' => User::query()
                ->where('status', UserStatus::BLOCKED->value)
                ->count(),
        ];
    }

    /**
     * Tìm và khóa dòng người dùng để cập nhật trong transaction.
     */
    public function findForUpdate(int $userId): ?User
    {
        return User::query()
            ->withCount(['properties', 'bookings'])
            ->lockForUpdate()
            ->find($userId);
    }

    /**
     * Lưu thay đổi thông tin người dùng.
     */
    public function save(User $user): User
    {
        $user->save();

        return $user->refresh()->loadCount(['properties', 'bookings']);
    }
}
