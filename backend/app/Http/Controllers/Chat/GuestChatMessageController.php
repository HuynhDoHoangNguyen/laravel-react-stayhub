<?php

namespace App\Http\Controllers\Chat;

use App\Common\ApiResponse;
use App\Exceptions\Chat\ChatProviderException;
use App\Http\Requests\Chat\SendGuestChatMessageRequest;
use App\Services\Chat\SendGuestChatMessageService;
use Illuminate\Http\JsonResponse;

final class GuestChatMessageController
{
    public function store(SendGuestChatMessageRequest $request, SendGuestChatMessageService $service): JsonResponse
    {
        $input = $request->validated();
        try {
            $result = $service->send($request->session()->getId(), $request->ip() ?? '', $input['request_id'], $input['message']);
        } catch (ChatProviderException $error) {
            $status = $error->errorCode === ChatProviderException::TIMEOUT ? 504 : 503;

            return $this->retryAfter(ApiResponse::error($error->getMessage(), $error->errorCode, $status), $error->retryAfterSeconds);
        }

        return match ($result['status']) {
            'limited' => $this->retryAfter(ApiResponse::error('Bạn đã gửi quá nhiều yêu cầu.', 'CHAT_RATE_LIMITED', 429), $result['retry_after']),
            'duplicate' => ApiResponse::error('Lượt gửi này đã được tiếp nhận. Hãy bắt đầu lượt mới nếu cần.', 'CHAT_GUEST_DUPLICATE', 409),
            'busy' => ApiResponse::error('Trợ lý đang bận. Vui lòng thử lại.', 'CHAT_BUSY', 503),
            default => ApiResponse::success($result['data']),
        };
    }

    private function retryAfter(JsonResponse $response, ?int $seconds): JsonResponse
    {
        return $seconds === null ? $response : $response->header('Retry-After', (string) $seconds)
            ->header('Access-Control-Expose-Headers', 'Retry-After');
    }
}
