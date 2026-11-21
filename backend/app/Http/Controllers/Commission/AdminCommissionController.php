<?php

namespace App\Http\Controllers\Commission;

use App\Http\Controllers\Controller;
use App\Services\Commission\AdminCommissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCommissionController extends Controller
{
    public function __construct(
        protected readonly AdminCommissionService $service,
    ) {}

    /**
     * Danh sách toàn bộ hoa hồng hệ thống dành cho Admin.
     */
    public function commissions(Request $request): JsonResponse
    {
        $actor = $request->user();
        if ($actor->role?->value !== 'ADMIN' && $actor->role !== 'ADMIN') {
            return response()->json(['message' => 'Chỉ tài khoản Admin mới có quyền truy cập.'], 403);
        }

        $perPage = max(1, min(100, (int) $request->get('per_page', 15)));
        $paginator = $this->service->listCommissions(
            $request->only(['status', 'host_id']),
            $perPage
        );

        return response()->json([
            'success' => true,
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Hàng chờ khai báo đối soát (Remittance Queue) dành cho Admin.
     */
    public function remittances(Request $request): JsonResponse
    {
        $actor = $request->user();
        if ($actor->role?->value !== 'ADMIN' && $actor->role !== 'ADMIN') {
            return response()->json(['message' => 'Chỉ tài khoản Admin mới có quyền truy cập.'], 403);
        }

        $perPage = max(1, min(100, (int) $request->get('per_page', 15)));
        $paginator = $this->service->listRemittances(
            $request->only(['status']),
            $perPage
        );

        return response()->json([
            'success' => true,
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Admin xác minh giao dịch đối soát (Verify).
     */
    public function verify(Request $request, int|string $id): JsonResponse
    {
        $actor = $request->user();
        if ($actor->role?->value !== 'ADMIN' && $actor->role !== 'ADMIN') {
            return response()->json(['message' => 'Chỉ tài khoản Admin mới có quyền truy cập.'], 403);
        }

        $validated = $request->validate([
            'verified_bank_reference' => ['required', 'string', 'max:128'],
            'review_note' => ['nullable', 'string', 'max:1000'],
        ], [
            'verified_bank_reference.required' => 'Mã giao dịch ngân hàng trích từ sao kê là bắt buộc.',
            'verified_bank_reference.max' => 'Mã giao dịch ngân hàng không được vượt quá 128 ký tự.',
        ]);

        $result = $this->service->verifyRemittance((int) $id, $actor->id, $validated);

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => [
                'remittance_id' => $result['remittance']->id,
                'verified_bank_reference' => $result['remittance']->verified_bank_reference,
                'status' => $result['remittance']->status->value,
                'reviewed_at' => $result['remittance']->reviewed_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Admin từ chối giao dịch đối soát (Reject) với lý do bắt buộc.
     */
    public function reject(Request $request, int|string $id): JsonResponse
    {
        $actor = $request->user();
        if ($actor->role?->value !== 'ADMIN' && $actor->role !== 'ADMIN') {
            return response()->json(['message' => 'Chỉ tài khoản Admin mới có quyền truy cập.'], 403);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ], [
            'reason.required' => 'Lý do từ chối là bắt buộc.',
            'reason.min' => 'Lý do từ chối phải có ít nhất 3 ký tự.',
            'reason.max' => 'Lý do từ chối không được vượt quá 1000 ký tự.',
        ]);

        $result = $this->service->rejectRemittance((int) $id, $actor->id, $validated);

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => [
                'remittance_id' => $result['remittance']->id,
                'status' => $result['remittance']->status->value,
                'review_note' => $result['remittance']->review_note,
                'reviewed_at' => $result['remittance']->reviewed_at?->toIso8601String(),
            ],
        ]);
    }
}
