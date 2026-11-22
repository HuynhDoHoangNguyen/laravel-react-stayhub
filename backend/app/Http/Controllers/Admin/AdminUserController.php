<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mappers\Admin\AdminUserMapper;
use App\Services\Admin\AdminUserService;
use App\Validators\Admin\AdminUserValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    public function __construct(
        protected readonly AdminUserService $service,
        protected readonly AdminUserValidator $validator,
        protected readonly AdminUserMapper $mapper,
    ) {}

    /**
     * Lấy danh sách người dùng có phân trang, bộ lọc và thống kê KPI tổng quan.
     */
    public function index(Request $request): JsonResponse
    {
        $this->validator->authorizeAdmin($request->user());
        $filters = $this->validator->validateListFilters($request->query());
        $result = $this->service->listUsersWithSummary($filters);

        return response()->json(
            $this->mapper->toPaginatedResponse($result['paginator'], $result['summary']),
            200
        );
    }

    /**
     * Admin phê duyệt mở khóa tài khoản Đối tác (Host).
     */
    public function approveHost(Request $request, int|string $id): JsonResponse
    {
        $actor = $request->user();
        $this->validator->authorizeAdmin($actor);
        $userId = $this->validator->validateUserId($id);

        $approvedUser = $this->service->approveHost($userId, (int) $actor->id);

        return response()->json([
            'success' => true,
            'message' => 'Đã phê duyệt mở khóa tài khoản Đối tác thành công.',
            'data' => $this->mapper->toAdminUserItem($approvedUser),
        ], 200);
    }

    /**
     * Admin cập nhật trạng thái hoạt động/khóa của tài khoản người dùng.
     */
    public function updateStatus(Request $request, int|string $id): JsonResponse
    {
        $actor = $request->user();
        $this->validator->authorizeAdmin($actor);
        $userId = $this->validator->validateUserId($id);
        $newStatus = $this->validator->validateStatusPayload($request->all());

        $updatedUser = $this->service->updateUserStatus($userId, $newStatus, (int) $actor->id);

        return response()->json([
            'success' => true,
            'message' => 'Đã cập nhật trạng thái tài khoản người dùng thành công.',
            'data' => $this->mapper->toAdminUserItem($updatedUser),
        ], 200);
    }
}
