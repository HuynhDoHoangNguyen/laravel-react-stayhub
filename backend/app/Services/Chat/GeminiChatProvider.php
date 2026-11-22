<?php

namespace App\Services\Chat;

use App\Exceptions\Chat\ChatProviderException;
use DateTimeImmutable;
use DateTimeZone;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Date;
use InvalidArgumentException;

final class GeminiChatProvider implements ChatProvider
{
    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/models/';
    private const CONNECT_TIMEOUT_SECONDS = 3;

    private const INSTRUCTION = 'Bạn là trợ lý tư vấn lưu trú của StayHub. Trả lời ngắn gọn bằng tiếng Việt. '
        .'Chỉ tư vấn chung. Với giá, phòng trống hoặc dữ liệu StayHub hiện hành, hướng người dùng đến chức năng tra cứu. '
        .'Không khẳng định đã đặt phòng, sửa hoặc hủy đặt phòng hay thực hiện thanh toán. '
        .'Không yêu cầu mật khẩu, mã xác thực, thông tin thanh toán hoặc dữ liệu cá nhân.';

    public function __construct(private readonly Factory $http) {}

    public function answer(#[\SensitiveParameter] string $approvedQuestion): string
    {
        $question = trim($approvedQuestion);
        if ($question === '') {
            throw new InvalidArgumentException('An approved chat question is required.');
        }

        $key = config('chatbot.api_key');
        $model = config('chatbot.model');
        $timeoutMs = config('chatbot.timeout_ms');
        $maxOutputTokens = config('chatbot.max_output_tokens');

        if (config('chatbot.enabled') !== true
            || ! is_string($key) || preg_match('/\A[!-~]+\z/', $key) !== 1
            || ! is_string($model) || preg_match('/\A[a-z0-9]+(?:[.-][a-z0-9]+)*\z/', $model) !== 1
            || ! is_int($timeoutMs) || $timeoutMs <= 0
            || ! is_int($maxOutputTokens) || $maxOutputTokens <= 0) {
            throw new ChatProviderException(ChatProviderException::UNAVAILABLE);
        }

        $timeout = $timeoutMs / 1000;
        try {
            $response = $this->http->acceptJson()->asJson()
                ->withHeader('x-goog-api-key', $key)
                ->withOptions(['allow_redirects' => false])
                ->connectTimeout(min(self::CONNECT_TIMEOUT_SECONDS, $timeout))
                ->timeout($timeout)
                ->post(self::BASE_URL.$model.':generateContent', [
                    'systemInstruction' => ['parts' => [['text' => self::INSTRUCTION]]],
                    'contents' => [['role' => 'user', 'parts' => [['text' => $question]]]],
                    'generationConfig' => [
                        'candidateCount' => 1,
                        'maxOutputTokens' => $maxOutputTokens,
                    ],
                ]);
        } catch (ConnectionException $error) {
            $cause = $error->getPrevious();
            $context = $cause instanceof ConnectException ? $cause->getHandlerContext() : [];

            throw new ChatProviderException(
                ($context['errno'] ?? null) === 28 || ($context['timed_out'] ?? null) === true
                    ? ChatProviderException::TIMEOUT
                    : ChatProviderException::UNAVAILABLE,
            );
        }

        if ($response->tooManyRequests()) {
            throw new ChatProviderException(
                ChatProviderException::RATE_LIMITED,
                $this->retryAfterSeconds($response->header('Retry-After')),
            );
        }

        if (! $response->successful()) {
            throw new ChatProviderException(ChatProviderException::UNAVAILABLE);
        }

        return $this->extractAnswer($response->json());
    }

    private function extractAnswer(#[\SensitiveParameter] mixed $body): string
    {
        $candidates = $body['candidates'] ?? null;
        if (isset($body['promptFeedback']['blockReason'])
            || ! is_array($candidates) || ! array_is_list($candidates) || count($candidates) !== 1) {
            throw new ChatProviderException(ChatProviderException::UNAVAILABLE);
        }

        $candidate = $candidates[0];
        $parts = $candidate['content']['parts'] ?? null;
        if (($candidate['finishReason'] ?? null) !== 'STOP'
            || ! is_array($parts) || ! array_is_list($parts) || $parts === []) {
            throw new ChatProviderException(ChatProviderException::UNAVAILABLE);
        }

        $text = [];
        foreach ($parts as $part) {
            if (($part['thought'] ?? null) === true) {
                continue;
            }
            if (! is_string($part['text'] ?? null)) {
                throw new ChatProviderException(ChatProviderException::UNAVAILABLE);
            }
            $text[] = $part['text'];
        }

        $answer = trim(implode('', $text));
        if ($answer === '') {
            throw new ChatProviderException(ChatProviderException::UNAVAILABLE);
        }

        return $answer;
    }

    private function retryAfterSeconds(string $header): ?int
    {
        $value = trim($header);
        if (ctype_digit($value)) {
            $seconds = filter_var(ltrim($value, '0') ?: '0', FILTER_VALIDATE_INT);

            return $seconds === false ? null : $seconds;
        }

        $date = DateTimeImmutable::createFromFormat(DATE_RFC7231, $value, new DateTimeZone('GMT'));
        if ($date === false || $date->format(DATE_RFC7231) !== $value) {
            return null;
        }

        return max(0, $date->getTimestamp() - Date::now()->getTimestamp());
    }
}
