<?php

namespace App\Mappers\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AdminUserMapper
{
    /**
     * Chuẩn hóa thông tin một người dùng cho bảng Quản lý người dùng của Admin.
     *
     * @return array<string, mixed>
     */
    public function toAdminUserItem(User $user): array
    {
        $role = $user->role instanceof UserRole ? $user->role->value : (string) $user->role;
        $status = $user->status instanceof UserStatus ? $user->status->value : (string) $user->status;
        $isHost = $role === UserRole::HOST->value;
        $isHostApproved = $isHost ? ($user->email_verified_at !== null) : null;

        return [
            'id' => (int) $user->id,
            'name' => (string) $user->name,
            'email' => (string) $user->email,
            'phone' => $user->phone !== null ? (string) $user->phone : null,
            'address' => $user->address !== null ? (string) $user->address : null,
            'avatar' => $user->avatar !== null ? (string) $user->avatar : null,
            'role' => $role,
            'status' => $status,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'host_approval_status' => $isHost ? ($isHostApproved ? 'APPROVED' : 'PENDING') : 'NOT_APPLICABLE',
            'is_host_approved' => $isHostApproved,
            'properties_count' => (int) ($user->properties_count ?? 0),
            'bookings_count' => (int) ($user->bookings_count ?? 0),
            'created_at' => $user->created_at?->toIso8601String(),
            'updated_at' => $user->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Chuẩn hóa dữ liệu danh sách người dùng phân trang kèm thống kê tổng quan.
     *
     * @param array<string, int> $summary
     * @return array<string, mixed>
     */
    public function toPaginatedResponse(LengthAwarePaginator $paginator, array $summary): array
    {
        return [
            'success' => true,
            'data' => collect($paginator->items())->map(fn (User $u) => $this->toAdminUserItem($u))->values()->all(),
            'summary' => $summary,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }
}
