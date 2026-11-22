<?php

namespace App\Exceptions\Chat;

use RuntimeException;

final class ChatProviderException extends RuntimeException
{
    public const UNAVAILABLE = 'CHAT_PROVIDER_UNAVAILABLE';
    public const RATE_LIMITED = 'CHAT_PROVIDER_RATE_LIMITED';
    public const TIMEOUT = 'CHAT_PROVIDER_TIMEOUT';

    public function __construct(
        public readonly string $errorCode,
        public readonly ?int $retryAfterSeconds = null,
    ) {
        parent::__construct(match ($errorCode) {
            self::RATE_LIMITED => 'Trợ lý đang nhận quá nhiều yêu cầu. Vui lòng thử lại sau.',
            self::TIMEOUT => 'Trợ lý phản hồi quá thời gian. Vui lòng thử lại.',
            default => 'Trợ lý chưa thể trả lời lúc này. Vui lòng thử lại sau.',
        });
    }
}
