<?php

namespace App\Services\Chat;

use App\Exceptions\Chat\ChatProviderException;
use App\Repositories\Chat\ChatTurnRepository;
use Illuminate\Validation\ValidationException;

final class SendChatMessageService
{
    public function __construct(
        private readonly ChatTurnPersistenceService $persistence,
        private readonly ChatTurnRepository $repository,
        private readonly ChatProvider $provider,
        private readonly ChatProviderSlots $slots,
        private readonly PublicFaqQueryService $faq,
        private readonly PublicCatalogChatService $catalog,
        private readonly PublicServiceChatService $services,
        private readonly PublicRoomAvailabilityChatService $availability,
        private readonly NaturalAvailabilityChatService $naturalAvailability,
    ) {}

    public function send(int $actorId, string $requestId, ?int $sessionId, #[\SensitiveParameter] string $question): array
    {
        $turn = $this->persistence->claim($actorId, $requestId, $sessionId, $question, allowNewAttempt: false);
        if ($turn['status'] !== 'needs_capacity') {
            return $this->project($actorId, $requestId, $turn);
        }

        $faq = $this->faq->classify($question);
        if ($faq === PublicFaqQueryService::UNSUPPORTED) {
            throw ValidationException::withMessages([
                'message' => 'FAQ công khai hiện chỉ hỗ trợ câu hỏi “Làm thế nào để đặt phòng?”.',
            ]);
        }

        $catalog = null;
        if ($this->catalog->isSavedListingCommand($question)) {
            $catalog = $this->catalog->prepareSavedListing($question);
            if ($catalog === null) {
                throw ValidationException::withMessages([
                    'message' => 'Dùng mẫu “Catalog: Tìm phòng cho 4 người dưới 1 triệu”. Bạn có thể thêm tại "Tên cơ sở" hoặc tại #71.',
                ]);
            }
            if ($catalog['status'] === 'unknown') {
                throw ValidationException::withMessages([
                    'message' => 'Chưa tìm thấy cơ sở công khai theo tên. Bạn hãy kiểm tra tên hoặc chọn bằng mã, ví dụ tại #71.',
                ]);
            }
        }

        $amenityReply = null;
        if ($this->catalog->isSavedAmenityCommand($question)) {
            $amenity = $this->catalog->prepareSavedAmenity($question);
            if (isset($amenity['error'])) {
                throw ValidationException::withMessages(['message' => $amenity['error']]);
            }
            $amenityReply = $amenity['reply'];
        }

        $service = null;
        if ($this->services->isSavedCommand($question)) {
            $service = $this->services->parseSavedCommand($question);
            if ($service === null) {
                throw ValidationException::withMessages([
                    'message' => 'Dùng mẫu “Dịch vụ: Giá giặt ủi là bao nhiêu tại #71?” hoặc “Dịch vụ: Có đưa đón sân bay không tại #71?”.',
                ]);
            }
        }

        $availability = $this->availability->isSavedCommand($question)
            ? $this->availability->validateSavedCommand($question)
            : null;

        if ($faq === PublicFaqQueryService::APPROVED
            || $catalog !== null || $amenityReply !== null || $service !== null || $availability !== null) {
            $turn = $this->persistence->claim($actorId, $requestId, $sessionId, $question);
            if ($turn['status'] !== 'claimed') {
                return $this->project($actorId, $requestId, $turn);
            }

            $answer = $amenityReply ?? match (true) {
                $availability !== null => $this->availability->savedReply($availability['roomId'], $availability['query']),
                $service !== null => $this->services->savedReply($service['propertyId'], $service['name']),
                ($catalog['status'] ?? null) === 'ready' => $this->catalog->savedListingReply($catalog['filters']),
                ($catalog['status'] ?? null) === 'clarification' => $catalog['reply'],
                default => $this->faq->bookingReply(),
            };

            return $this->complete($actorId, $requestId, $turn['token'], $sessionId, $question, $answer);
        }

        $natural = $this->naturalAvailability->prepare($question);
        if (isset($natural['clarification'])) {
            $turn = $this->persistence->claim($actorId, $requestId, $sessionId, $question);
            if ($turn['status'] !== 'claimed') {
                return $this->project($actorId, $requestId, $turn);
            }

            return $this->complete($actorId, $requestId, $turn['token'], $sessionId, $question, $natural['clarification']);
        }

        if (config('chatbot.enabled') !== true || config('chatbot.allow_external_questions') !== true) {
            if ($natural !== null) {
                throw ValidationException::withMessages(['message' => NaturalAvailabilityChatService::LOCAL_GUIDE]);
            }
            throw new ChatProviderException(ChatProviderException::UNAVAILABLE);
        }

        $slotRequestedAt = hrtime(true);
        $slot = $this->slots->acquire();
        if ($slot === null) {
            return ['status' => 'busy'];
        }

        try {
            $turn = $this->persistence->claim($actorId, $requestId, $sessionId, $question);
            if ($turn['status'] !== 'claimed') {
                return $this->project($actorId, $requestId, $turn);
            }

            if (! $this->slots->hasTimeForProvider($slotRequestedAt)) {
                if ($this->persistence->fail($actorId, $requestId, $turn['token'])) {
                    return ['status' => 'retry_required'];
                }

                return $this->readRecovery($actorId, $requestId, $sessionId, $question);
            }

            try {
                $answer = $natural === null
                    ? $this->provider->answer($question)
                    : $this->naturalAvailability->reply($natural['prompt'], $natural['location'], $natural['query'], $natural['weekend']);
            } catch (ChatProviderException $error) {
                if (! $this->persistence->fail($actorId, $requestId, $turn['token'])) {
                    return $this->readRecovery($actorId, $requestId, $sessionId, $question);
                }

                throw $error;
            }

            return $this->complete($actorId, $requestId, $turn['token'], $sessionId, $question, $answer);
        } finally {
            $slot->release();
        }
    }

    private function complete(
        int $actorId,
        string $requestId,
        string $token,
        ?int $sessionId,
        #[\SensitiveParameter] string $question,
        #[\SensitiveParameter] string $answer,
    ): array {
        $result = $this->persistence->complete($actorId, $requestId, $token, $sessionId, $question, $answer);

        return $result['status'] === 'stale'
            ? $this->readRecovery($actorId, $requestId, $sessionId, $question)
            : $this->project($actorId, $requestId, $result);
    }

    private function readRecovery(int $actorId, string $requestId, ?int $sessionId, #[\SensitiveParameter] string $question): array
    {
        $turn = $this->persistence->claim($actorId, $requestId, $sessionId, $question, allowNewAttempt: false);

        return $turn['status'] === 'needs_capacity'
            ? ['status' => 'retry_required']
            : $this->project($actorId, $requestId, $turn);
    }

    private function project(int $actorId, string $requestId, array $turn): array
    {
        if (! in_array($turn['status'], ['committed', 'replayed'], true)) {
            return ['status' => $turn['status']];
        }

        $pair = $this->repository->findResult(
            $actorId,
            $turn['session_id'],
            $turn['user_message_id'],
            $turn['assistant_message_id'],
        );

        if ($pair === null) {
            return ['status' => 'result_unavailable'];
        }

        return ['status' => $turn['status'], 'data' => [
            'session_id' => $turn['session_id'],
            'request_id' => $requestId,
            ...$pair,
        ]];
    }
}
