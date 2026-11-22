<?php

namespace App\Http\Controllers\Chat;

use App\Common\ApiResponse;
use App\Exceptions\Chat\ChatProviderException;
use App\Http\Requests\Chat\SendChatMessageRequest;
use App\Services\Chat\SendChatMessageService;
use Illuminate\Http\JsonResponse;

final class ChatMessageController
{
    public function store(SendChatMessageRequest $request, SendChatMessageService $service): JsonResponse
    {
        $input = $request->validated();

        try {
            $result = $service->send(
                (int) $request->user()->getAuthIdentifier(),
                strtolower($input['request_id']),
                $input['session_id'] ?? null,
                $input['message'],
            );
        } catch (ChatProviderException $error) {
            $response = ApiResponse::error(
                $error->getMessage(),
                $error->errorCode,
                $error->errorCode === ChatProviderException::TIMEOUT ? 504 : 503,
            );

            return $error->retryAfterSeconds === null
                ? $response
                : $response->header('Retry-After', (string) $error->retryAfterSeconds)
                    ->header('Access-Control-Expose-Headers', 'Retry-After');
        }

        return match ($result['status']) {
            'committed' => ApiResponse::success($result['data'], 201),
            'replayed' => ApiResponse::success($result['data']),
            'not_found' => ApiResponse::error('Không tìm thấy phiên trò chuyện.', 'CHAT_SESSION_NOT_FOUND', 404),
            'conflict' => ApiResponse::error('Mã lượt gửi đã được dùng cho nội dung khác.', 'CHAT_REQUEST_CONFLICT', 409),
            'in_progress' => ApiResponse::error('Lượt gửi đang được xử lý. Vui lòng thử lại.', 'CHAT_IN_PROGRESS', 409),
            'retry_required' => ApiResponse::error('Lượt gửi cần được thử lại. Vui lòng dùng cùng câu hỏi.', 'CHAT_RETRY_REQUIRED', 409),
            'expired' => ApiResponse::error('Lượt gửi đã hết hạn. Hãy bắt đầu câu hỏi mới.', 'CHAT_REQUEST_EXPIRED', 409),
            'attempts_exhausted' => ApiResponse::error('Lượt gửi đã hết số lần thử.', 'CHAT_ATTEMPTS_EXHAUSTED', 409),
            'result_unavailable' => ApiResponse::error('Kết quả của lượt gửi không còn khả dụng.', 'CHAT_RESULT_UNAVAILABLE', 409),
            'busy' => ApiResponse::error('Trợ lý đang bận. Vui lòng thử lại.', 'CHAT_BUSY', 503),
        };
    }
}
