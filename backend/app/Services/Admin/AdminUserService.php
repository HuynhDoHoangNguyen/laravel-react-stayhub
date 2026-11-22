<?php

namespace App\Services\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Repositories\Admin\AdminUserRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AdminUserService
{
    public function __construct(
        protected readonly AdminUserRepository $repository,
    ) {}

    /**
     * Lấy danh sách người dùng có phân trang và bộ đếm tổng quan.
     *
     * @param array{keyword: ?string, role: ?string, status: ?string, host_approval: ?string, page: int, per_page: int} $filters
     * @return array{paginator: LengthAwarePaginator, summary: array<string, int>}
     */
    public function listUsersWithSummary(array $filters): array
    {
        return [
            'paginator' => $this->repository->paginateUsers($filters),
            'summary' => $this->repository->getSummaryCounts(),
        ];
    }

    /**
     * Admin phê duyệt tài khoản Đối tác (Host), mở khóa toàn bộ chức năng kinh doanh của Host.
     */
    public function approveHost(int $userId, int $adminId): User
    {
        return DB::transaction(function () use ($userId, $adminId): User {
            $user = $this->repository->findForUpdate($userId);
            if ($user === null) {
                throw new NotFoundHttpException('Không tìm thấy tài khoản người dùng.');
            }

            $role = $user->role instanceof UserRole ? $user->role->value : (string) $user->role;
            if ($role !== UserRole::HOST->value) {
                throw ValidationException::withMessages([
                    'user_id' => ['Chỉ có thể phê duyệt đối với tài khoản có vai trò Đối tác (HOST).'],
                ]);
            }

            if ($user->email_verified_at === null) {
                $user->forceFill(['email_verified_at' => now()]);
            }
            if ($user->status !== UserStatus::ACTIVE) {
                $user->status = UserStatus::ACTIVE;
            }

            $saved = $this->repository->save($user);

            DB::afterCommit(fn () => Log::info('Admin đã phê duyệt mở khóa tài khoản Đối tác (Host).', [
                'admin_id' => $adminId,
                'host_id' => $saved->id,
            ]));

            return $saved;
        });
    }

    /**
     * Admin cập nhật trạng thái hoạt động/khóa của tài khoản người dùng (ACTIVE / BLOCKED).
     */
    public function updateUserStatus(int $userId, UserStatus $newStatus, int $adminId): User
    {
        return DB::transaction(function () use ($userId, $newStatus, $adminId): User {
            $user = $this->repository->findForUpdate($userId);
            if ($user === null) {
                throw new NotFoundHttpException('Không tìm thấy tài khoản người dùng.');
            }

            if ((int) $user->id === $adminId) {
                throw ValidationException::withMessages([
                    'status' => ['Bạn không thể tự thay đổi trạng thái khóa cho tài khoản của chính mình.'],
                ]);
            }

            $role = $user->role instanceof UserRole ? $user->role->value : (string) $user->role;
            if ($role === UserRole::ADMIN->value) {
                throw ValidationException::withMessages([
                    'status' => ['Không thể khóa hoặc thay đổi trạng thái của tài khoản Quản trị viên khác.'],
                ]);
            }

            $user->status = $newStatus;
            $saved = $this->repository->save($user);

            DB::afterCommit(fn () => Log::info('Admin đã cập nhật trạng thái tài khoản người dùng.', [
                'admin_id' => $adminId,
                'target_user_id' => $saved->id,
                'new_status' => $newStatus->value,
            ]));

            return $saved;
        });
    }
}
