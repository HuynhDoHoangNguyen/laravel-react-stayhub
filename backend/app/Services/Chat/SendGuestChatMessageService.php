<?php

namespace App\Services\Chat;

use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class SendGuestChatMessageService
{
    public function __construct(
        private readonly ChatProvider $provider,
        private readonly ChatProviderSlots $slots,
        private readonly PublicCatalogChatService $catalog,
        private readonly PublicFaqQueryService $faq,
        private readonly TravelRecommendationService $travel,
    ) {}

    public function send(#[\SensitiveParameter] string $session, #[\SensitiveParameter] string $ip, string $requestId, #[\SensitiveParameter] string $question): array
    {
        $cache = Cache::store('database');
        $sessionKey = $this->key('session', $session);
        $limitLock = $cache->lock('guest-chat:limit-lock', 5);
        if (! $limitLock->get()) {
            return ['status' => 'limited', 'retry_after' => 1];
        }

        try {
            $limiter = new RateLimiter($cache);
            $limits = [$sessionKey => 5, $this->key('ip', $ip) => 15, 'guest-chat:all' => 30];
            $wait = 0;
            foreach ($limits as $key => $maximum) {
                if ($limiter->tooManyAttempts($key, $maximum)) {
                    $wait = max($wait, 1, $limiter->availableIn($key));
                }
            }
            if ($wait > 0) {
                return ['status' => 'limited', 'retry_after' => $wait];
            }
            foreach (array_keys($limits) as $key) {
                $limiter->hit($key, 60);
            }
        } finally {
            $limitLock->release();
        }

        if (! $cache->add($this->key('turn', $session.'|'.strtolower($requestId)), true, 120)) {
            return ['status' => 'duplicate'];
        }

        $text = Str::lower(Str::ascii($question, 'vi'));
        $text = preg_replace('/\s+/', ' ', $text);
        if (preg_match('/\b(hoa don|thanh toan|cong no)\b/', $text)) {
            return $this->answer('Bạn có thể xem trạng thái thanh toán trong mục Hóa đơn của tôi.', 'invoices');
        }
        if (preg_match('/\b(da dat|don dat|booking cua|ma (booking|dat phong|dat cho)|phong cua toi|phong toi dat|lich dat phong)\b/', $text)) {
            return $this->answer('Bạn có thể xem ngày lưu trú và trạng thái trong mục Đơn đặt phòng.', 'bookings');
        }
        if (preg_match('/@|https?:\/\/|\b(?:\d[ .+\-()]*){7,}|\b(mat khau|otp|token|cccd|so the|email|so dien thoai|dia chi cua toi)\b/', $this->catalog->privacyText($text))) {
            return $this->answer('Bạn hãy bỏ thông tin cá nhân hoặc mã bảo mật rồi hỏi lại nhé.');
        }
        $faqReply = $this->faq->reply($question);
        if ($faqReply !== null) {
            return $this->answer($faqReply);
        }
        $catalogReply = $this->catalog->reply($question, $text);
        if ($catalogReply !== null) {
            return ['status' => 'answered', 'data' => $catalogReply];
        }

        $travelReply = $this->travel->reply($text);
        if ($travelReply !== null) {
            return ['status' => 'answered', 'data' => $travelReply];
        }

        $topics = [
            '/\b(di dau|di dao|thu gian|buon qua|thien nhien|gan nha|doi gio)\b/' => [
                'Gợi ý hoạt động nhẹ nhàng gần nhà, bằng tiếng Việt.',
                'Bạn có thể đi dạo ở công viên, ghé quán cà phê yên tĩnh hoặc thử một chuyến đi ngắn gần nhà. Bạn thích thiên nhiên hay không khí thành phố hơn?',
            ],
            '/\b(chuan bi|hanh ly|kinh nghiem|luu y|nhan phong|tra phong|dat phong)\b/' => [
                'Gợi ý điều cần kiểm tra trước khi đặt phòng, bằng tiếng Việt.',
                'Bạn nên kiểm tra giờ nhận phòng, chính sách hủy và các tiện nghi cần thiết trước khi đặt.',
            ],
        ];
        foreach ($topics as $pattern => [$prompt, $fallback]) {
            if (preg_match($pattern, $text)) {
                return $this->consult($prompt, $fallback);
            }
        }

        return $this->answer('Bạn muốn gợi ý hoạt động thư giãn, kinh nghiệm lưu trú hay tìm chỗ nghỉ theo số khách và ngân sách?');
    }

    private function key(string $type, #[\SensitiveParameter] string $value): string
    {
        return 'guest-chat:'.$type.':'.hash_hmac('sha256', $value, config('app.key'));
    }

    private function consult(string $prompt, string $fallback): array
    {
        if (! config('chatbot.guest.allow_provider') || ! config('chatbot.enabled') || ! config('chatbot.allow_external_questions')) {
            return $this->answer($fallback);
        }

        $startedAt = hrtime(true);
        $slot = $this->slots->acquire(public: true);
        if ($slot === null) {
            return ['status' => 'busy'];
        }
        try {
            if (! $this->slots->hasTimeForProvider($startedAt)) {
                return ['status' => 'busy'];
            }

            return $this->answer($this->provider->answer($prompt));
        } finally {
            $slot->release();
        }
    }

    private function answer(string $message, ?string $action = null, array $rooms = []): array
    {
        return ['status' => 'answered', 'data' => ['message' => $message, 'action' => $action, 'rooms' => $rooms]];
    }
}
