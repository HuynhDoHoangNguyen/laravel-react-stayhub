<?php

namespace App\Validators\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AdminUserValidator
{
    /**
     * Bắt buộc tài khoản thực thi phải có vai trò ADMIN.
     */
    public function authorizeAdmin(?User $actor): void
    {
        if ($actor === null || ($actor->role?->value !== UserRole::ADMIN->value && $actor->role !== UserRole::ADMIN->value)) {
            throw new AuthorizationException('Chỉ Quản trị viên (Admin) mới có quyền truy cập chức năng này.');
        }
    }

    /**
     * Xác thực ID người dùng nguyên dương.
     */
    public function validateUserId(int|string $id): int
    {
        $str = trim((string) $id);
        if (! preg_match('/^[1-9]\d*$/', $str)) {
            throw new NotFoundHttpException('Không tìm thấy tài khoản người dùng.');
        }

        return (int) $str;
    }

    /**
     * Xác thực bộ lọc danh sách người dùng của Admin.
     *
     * @param array<string, mixed> $query
     * @return array{keyword: ?string, role: ?string, status: ?string, host_approval: ?string, page: int, per_page: int}
     */
    public function validateListFilters(array $query): array
    {
        $validator = Validator::make($query, [
            'keyword' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', 'string', Rule::in(['ALL', 'ADMIN', 'HOST', 'CUSTOMER'])],
            'status' => ['nullable', 'string', Rule::in(['ALL', 'ACTIVE', 'INACTIVE', 'BLOCKED'])],
            'host_approval' => ['nullable', 'string', Rule::in(['ALL', 'PENDING', 'APPROVED'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ], [
            'role.in' => 'Vai trò lọc không hợp lệ.',
            'status.in' => 'Trạng thái lọc không hợp lệ.',
            'host_approval.in' => 'Trạng thái duyệt đối tác không hợp lệ.',
            'per_page.max' => 'Số bản ghi mỗi trang tối đa là 100.',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();
        $keyword = isset($validated['keyword']) ? trim((string) $validated['keyword']) : '';
        $role = $validated['role'] ?? null;
        $status = $validated['status'] ?? null;
        $hostApproval = $validated['host_approval'] ?? null;

        return [
            'keyword' => $keyword !== '' ? $keyword : null,
            'role' => ($role !== null && $role !== 'ALL') ? $role : null,
            'status' => ($status !== null && $status !== 'ALL') ? $status : null,
            'host_approval' => ($hostApproval !== null && $hostApproval !== 'ALL') ? $hostApproval : null,
            'page' => isset($validated['page']) ? (int) $validated['page'] : 1,
            'per_page' => isset($validated['per_page']) ? (int) $validated['per_page'] : 15,
        ];
    }

    /**
     * Xác thực payload cập nhật trạng thái tài khoản người dùng (ACTIVE / BLOCKED).
     *
     * @param array<string, mixed> $payload
     */
    public function validateStatusPayload(array $payload): UserStatus
    {
        $validator = Validator::make($payload, [
            'status' => ['required', 'string', Rule::in([UserStatus::ACTIVE->value, UserStatus::BLOCKED->value])],
        ], [
            'status.required' => 'Vui lòng chọn trạng thái tài khoản.',
            'status.in' => 'Trạng thái tài khoản chỉ hỗ trợ ACTIVE hoặc BLOCKED.',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return UserStatus::from($validator->validated()['status']);
    }
}
