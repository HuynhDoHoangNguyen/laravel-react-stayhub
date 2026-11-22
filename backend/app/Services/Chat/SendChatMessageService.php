<?php

namespace App\Services\Chat;

use App\Exceptions\Chat\ChatProviderException;
use App\Repositories\Chat\ChatTurnRepository;

final class SendChatMessageService
{
    public function __construct(
        private readonly ChatTurnPersistenceService $persistence,
        private readonly ChatTurnRepository $repository,
        private readonly ChatProvider $provider,
        private readonly ChatProviderSlots $slots,
    ) {}

    public function send(int $actorId, string $requestId, ?int $sessionId, #[\SensitiveParameter] string $question): array
    {
        $turn = $this->persistence->claim($actorId, $requestId, $sessionId, $question, allowNewAttempt: false);
        if ($turn['status'] !== 'needs_capacity') {
            return $this->project($actorId, $requestId, $turn);
        }

        if (config('chatbot.enabled') !== true || config('chatbot.allow_external_questions') !== true) {
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
                $answer = $this->provider->answer($question);
            } catch (ChatProviderException $error) {
                if (! $this->persistence->fail($actorId, $requestId, $turn['token'])) {
                    return $this->readRecovery($actorId, $requestId, $sessionId, $question);
                }

                throw $error;
            }

            $result = $this->persistence->complete($actorId, $requestId, $turn['token'], $sessionId, $question, $answer);

            return $result['status'] === 'stale'
                ? $this->readRecovery($actorId, $requestId, $sessionId, $question)
                : $this->project($actorId, $requestId, $result);
        } finally {
            $slot->release();
        }
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
