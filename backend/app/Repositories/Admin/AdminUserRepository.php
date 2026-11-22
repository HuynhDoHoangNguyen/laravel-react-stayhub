<?php

namespace App\Repositories\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

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
                $query->whereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('host_agreement_acceptances')
                        ->whereColumn('host_agreement_acceptances.host_id', 'users.id')
                        ->where('host_agreement_acceptances.method', 'REGISTRATION_PENDING');
                });
            } elseif ($filters['host_approval'] === 'APPROVED') {
                $query->whereNotExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('host_agreement_acceptances')
                        ->whereColumn('host_agreement_acceptances.host_id', 'users.id')
                        ->where('host_agreement_acceptances.method', 'REGISTRATION_PENDING');
                });
            }
        }

        return $query
            ->select('users.*')
            ->selectRaw("EXISTS (
                SELECT 1 FROM host_agreement_acceptances
                WHERE host_agreement_acceptances.host_id = users.id
                  AND host_agreement_acceptances.method = 'REGISTRATION_PENDING'
            ) AS is_pending_approval")
            ->orderByRaw("CASE WHEN role = 'HOST' AND EXISTS (
                SELECT 1 FROM host_agreement_acceptances
                WHERE host_agreement_acceptances.host_id = users.id
                  AND host_agreement_acceptances.method = 'REGISTRATION_PENDING'
            ) THEN 0 ELSE 1 END ASC")
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
                ->whereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('host_agreement_acceptances')
                        ->whereColumn('host_agreement_acceptances.host_id', 'users.id')
                        ->where('host_agreement_acceptances.method', 'REGISTRATION_PENDING');
                })
                ->count(),
            'approved_hosts' => User::query()
                ->where('role', UserRole::HOST->value)
                ->whereNotExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('host_agreement_acceptances')
                        ->whereColumn('host_agreement_acceptances.host_id', 'users.id')
                        ->where('host_agreement_acceptances.method', 'REGISTRATION_PENDING');
                })
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
