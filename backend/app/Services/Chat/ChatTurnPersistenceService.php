<?php

namespace App\Services\Chat;

use App\Models\ChatTurn;
use App\Repositories\Chat\ChatTurnRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

final class ChatTurnPersistenceService
{
    public const RETENTION_DAYS = 7;

    private const RETRY_HOURS = 24;
    private const MAX_ATTEMPTS = 3;
    private const LEASE_SECONDS = 30;

    public function __construct(private readonly ChatTurnRepository $repository) {}

    /**
     * @return array{status: 'claimed', token: string}
     *     |array{status: 'replayed', session_id: int, user_message_id: int, assistant_message_id: int}
     *     |array{status: 'not_found'|'conflict'|'expired'|'in_progress'|'attempts_exhausted'|'result_unavailable'|'needs_capacity'}
     */
    public function claim(
        int $actorId,
        string $requestId,
        ?int $sessionId,
        #[\SensitiveParameter] string $question,
        bool $allowNewAttempt = true,
    ): array {
        $requestId = $this->normalizeRequestId($requestId);
        $fingerprint = $this->fingerprint($actorId, $requestId, $sessionId, trim($question));

        return DB::transaction(function () use ($actorId, $requestId, $sessionId, $fingerprint, $allowNewAttempt): array {
            if (! $this->repository->lockActor($actorId)) {
                return ['status' => 'not_found'];
            }

            $turn = $this->repository->lockTurn($actorId, $requestId);
            if ($sessionId !== null && $this->repository->lockOwnedSession($actorId, $sessionId) === null) {
                if ($turn?->status === ChatTurn::SUCCEEDED
                    && $turn->chat_session_id === null
                    && hash_equals($turn->payload_fingerprint, $fingerprint)) {
                    return ['status' => $this->isExpired($turn, $this->now()) ? 'expired' : 'result_unavailable'];
                }

                return ['status' => 'not_found'];
            }

            $now = $this->now();
            if ($turn !== null) {
                if (! hash_equals($turn->payload_fingerprint, $fingerprint)) {
                    return ['status' => 'conflict'];
                }
                if ($this->isExpired($turn, $now)) {
                    return ['status' => 'expired'];
                }
                if ($turn->status === ChatTurn::SUCCEEDED) {
                    return $this->replay($turn);
                }
                if ($turn->status === ChatTurn::PROCESSING && $turn->lease_expires_at->isAfter($now)) {
                    return ['status' => 'in_progress'];
                }
                if ($turn->attempts >= self::MAX_ATTEMPTS) {
                    return ['status' => 'attempts_exhausted'];
                }
            }

            if (! $allowNewAttempt) {
                return ['status' => 'needs_capacity'];
            }

            $turn ??= new ChatTurn([
                'user_id' => $actorId,
                'request_id' => $requestId,
                'payload_fingerprint' => $fingerprint,
                'attempts' => 0,
                'created_at' => $now,
            ]);
            $token = (string) Str::uuid();
            $turn->fill([
                'status' => ChatTurn::PROCESSING,
                'attempts' => $turn->attempts + 1,
                'processing_token' => $token,
                'lease_expires_at' => $now->addSeconds(self::LEASE_SECONDS),
            ])->save();

            return ['status' => 'claimed', 'token' => $token];
        }, 3);
    }

    /**
     * @return array{status: 'committed', session_id: int, user_message_id: int, assistant_message_id: int}
     *     |array{status: 'not_found'|'stale'|'conflict'}
     */
    public function complete(
        int $actorId,
        string $requestId,
        string $token,
        ?int $sessionId,
        string $question,
        string $answer,
    ): array {
        $requestId = $this->normalizeRequestId($requestId);
        $question = trim($question);
        $fingerprint = $this->fingerprint($actorId, $requestId, $sessionId, $question);

        return DB::transaction(function () use ($actorId, $requestId, $token, $sessionId, $question, $answer, $fingerprint): array {
            if (! $this->repository->lockActor($actorId)) {
                return ['status' => 'not_found'];
            }

            $turn = $this->repository->lockTurn($actorId, $requestId);
            if ($turn === null) {
                return ['status' => 'not_found'];
            }
            if (! hash_equals($turn->payload_fingerprint, $fingerprint)) {
                return ['status' => 'conflict'];
            }

            $session = $sessionId === null ? null : $this->repository->lockOwnedSession($actorId, $sessionId);
            if ($sessionId !== null && $session === null) {
                return ['status' => 'not_found'];
            }
            if (! $this->canFinish($turn, $token, $this->now())) {
                return ['status' => 'stale'];
            }

            $session ??= $this->repository->createSession($actorId);
            $result = $this->repository->writeMessages($session, $question, $answer);
            $turn->fill([
                'status' => ChatTurn::SUCCEEDED,
                'processing_token' => null,
                'lease_expires_at' => null,
                'chat_session_id' => $result['session_id'],
                'user_message_id' => $result['user_message_id'],
                'assistant_message_id' => $result['assistant_message_id'],
            ])->save();

            return ['status' => 'committed', ...$result];
        }, 3);
    }

    /** Mark a confirmed provider failure while this processing attempt still owns the turn. */
    public function fail(int $actorId, string $requestId, string $token): bool
    {
        $requestId = $this->normalizeRequestId($requestId);

        return DB::transaction(function () use ($actorId, $requestId, $token): bool {
            if (! $this->repository->lockActor($actorId)) {
                return false;
            }

            $turn = $this->repository->lockTurn($actorId, $requestId);
            if ($turn === null || ! $this->canFinish($turn, $token, $this->now())) {
                return false;
            }

            return $turn->fill([
                'status' => ChatTurn::FAILED,
                'processing_token' => null,
                'lease_expires_at' => null,
            ])->save();
        }, 3);
    }

    private function replay(ChatTurn $turn): array
    {
        if ($turn->chat_session_id === null || $turn->user_message_id === null || $turn->assistant_message_id === null
            || $this->repository->lockOwnedSession($turn->user_id, $turn->chat_session_id) === null) {
            return ['status' => 'result_unavailable'];
        }

        return [
            'status' => 'replayed',
            'session_id' => $turn->chat_session_id,
            'user_message_id' => $turn->user_message_id,
            'assistant_message_id' => $turn->assistant_message_id,
        ];
    }

    private function canFinish(ChatTurn $turn, string $token, CarbonImmutable $now): bool
    {
        return $turn->status === ChatTurn::PROCESSING
            && hash_equals($turn->processing_token, $token)
            && $turn->lease_expires_at->isAfter($now)
            && ! $this->isExpired($turn, $now);
    }

    private function isExpired(ChatTurn $turn, CarbonImmutable $now): bool
    {
        return ! $turn->created_at->addHours(self::RETRY_HOURS)->isAfter($now);
    }

    private function now(): CarbonImmutable
    {
        return Date::now()->toImmutable()->startOfSecond();
    }

    private function normalizeRequestId(string $requestId): string
    {
        if (! Str::isUuid($requestId)) {
            throw new InvalidArgumentException('A valid chat request UUID is required.');
        }

        return strtolower($requestId);
    }

    private function fingerprint(int $actorId, string $requestId, ?int $sessionId, string $question): string
    {
        $key = config('app.key');
        if (! is_string($key) || $key === '') {
            throw new LogicException('An application key is required for chat turn fingerprints.');
        }

        $payload = json_encode([$actorId, $requestId, $sessionId, $question], JSON_THROW_ON_ERROR);

        return hash_hmac('sha256', 'stayhub:chat-turn:'.$payload, $key);
    }
}
