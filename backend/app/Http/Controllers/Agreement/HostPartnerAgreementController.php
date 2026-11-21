<?php

namespace App\Http\Controllers\Agreement;

use App\Http\Controllers\Controller;
use App\Services\Agreement\PartnerAgreementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HostPartnerAgreementController extends Controller
{
    public function current(Request $request, PartnerAgreementService $service): JsonResponse
    {
        $actor = $request->user();
        if ($actor->role?->value !== 'HOST' && $actor->role !== 'HOST') {
            return response()->json(['message' => 'Chỉ tài khoản Host mới có quyền truy cập.'], 403);
        }

        $agreement = $service->getCurrentAgreement($actor->id);
        if ($agreement === null) {
            return response()->json(['message' => 'Chưa có hợp đồng đối tác nào được kích hoạt.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $agreement,
        ]);
    }

    public function accept(Request $request, int|string $id, PartnerAgreementService $service): JsonResponse
    {
        $actor = $request->user();
        if ($actor->role?->value !== 'HOST' && $actor->role !== 'HOST') {
            return response()->json(['message' => 'Chỉ tài khoản Host mới có quyền truy cập.'], 403);
        }

        $validated = $request->validate([
            'accepted' => ['required', 'boolean', 'accepted'],
            'content_sha256' => ['required', 'string', 'size:64'],
        ], [
            'accepted.accepted' => 'Bạn phải đồng ý với các điều khoản của hợp đồng đối tác.',
            'content_sha256.required' => 'Mã xác thực nội dung hợp đồng là bắt buộc.',
            'content_sha256.size' => 'Mã xác thực nội dung không hợp lệ.',
        ]);

        $acceptance = $service->acceptAgreement(
            $actor->id,
            (int) $id,
            $validated['content_sha256']
        );

        return response()->json([
            'success' => true,
            'message' => 'Chấp thuận hợp đồng đối tác thành công.',
            'data' => [
                'acceptance_id' => $acceptance->id,
                'accepted_at' => $acceptance->accepted_at?->toIso8601String(),
            ],
        ], 200);
    }
}
