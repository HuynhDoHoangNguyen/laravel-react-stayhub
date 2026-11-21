<?php

namespace App\Http\Controllers\Commission;

use App\Http\Controllers\Controller;
use App\Services\Commission\CommissionRemittanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HostCommissionController extends Controller
{
    public function __construct(
        protected readonly CommissionRemittanceService $service,
    ) {}

    /**
     * Danh sách hoa hồng của Host.
     */
    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();
        if ($actor->role?->value !== 'HOST' && $actor->role !== 'HOST') {
            return response()->json(['message' => 'Chỉ tài khoản Host mới có quyền truy cập.'], 403);
        }

        $perPage = max(1, min(100, (int) $request->get('per_page', 15)));
        $paginator = $this->service->listForHost(
            $actor->id,
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
     * Chi tiết khoản hoa hồng của Host kèm thông tin chuyển khoản và lịch sử nộp tiền.
     */
    public function show(Request $request, int|string $id): JsonResponse
    {
        $actor = $request->user();
        if ($actor->role?->value !== 'HOST' && $actor->role !== 'HOST') {
            return response()->json(['message' => 'Chỉ tài khoản Host mới có quyền truy cập.'], 403);
        }

        $result = $this->service->findForHost((int) $id, $actor->id);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Host nộp thông tin chuyển khoản đối soát (Remittance).
     */
    public function submitRemittance(Request $request, int|string $id): JsonResponse
    {
        $actor = $request->user();
        if ($actor->role?->value !== 'HOST' && $actor->role !== 'HOST') {
            return response()->json(['message' => 'Chỉ tài khoản Host mới có quyền truy cập.'], 403);
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'bank_transaction_reference' => ['nullable', 'string', 'max:128'],
            'idempotency_key' => ['required', 'string', 'max:64'],
        ], [
            'amount.required' => 'Số tiền nộp là bắt buộc.',
            'amount.numeric' => 'Số tiền nộp phải là một số hợp lệ.',
            'amount.gt' => 'Số tiền nộp phải lớn hơn 0.',
            'idempotency_key.required' => 'Khóa idempotency là bắt buộc để chống gửi trùng.',
        ]);

        $remittance = $this->service->submitRemittance((int) $id, $actor->id, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Đã gửi thông tin chuyển khoản đối soát. Vui lòng chờ Ban quản trị xác nhận.',
            'data' => [
                'id' => $remittance->id,
                'booking_commission_id' => $remittance->booking_commission_id,
                'amount' => $remittance->amount,
                'bank_transaction_reference' => $remittance->bank_transaction_reference,
                'status' => $remittance->status->value,
                'submitted_at' => $remittance->submitted_at?->toIso8601String(),
            ],
        ], 201);
    }
}
