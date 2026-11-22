<?php

namespace App\Repositories\Chat;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\ChatTurn;
use App\Models\User;

final class ChatTurnRepository
{
    /** Lock the actor first to serialize short writes, including the first claim of a missing UUID. */
    public function lockActor(int $actorId): bool
    {
        return User::query()->select('id')->whereKey($actorId)->lockForUpdate()->first() !== null;
    }

    public function lockTurn(int $actorId, string $requestId): ?ChatTurn
    {
        return ChatTurn::query()->where('user_id', $actorId)
            ->where('request_id', $requestId)->lockForUpdate()->first();
    }

    public function lockOwnedSession(int $actorId, int $sessionId): ?ChatSession
    {
        return ChatSession::query()->where('user_id', $actorId)
            ->whereKey($sessionId)->lockForUpdate()->first();
    }

    public function findResult(int $actorId, int $sessionId, int $userMessageId, int $assistantMessageId): ?array
    {
        $messages = ChatMessage::query()->where('chat_session_id', $sessionId)
            ->whereHas('chatSession', static fn ($session) => $session->where('user_id', $actorId))
            ->whereKey([$userMessageId, $assistantMessageId])->get()->keyBy('id');
        $user = $messages->get($userMessageId);
        $assistant = $messages->get($assistantMessageId);

        if ($user?->sender !== 'USER' || $assistant?->sender !== 'ASSISTANT') {
            return null;
        }

        $project = static fn (ChatMessage $message): array => [
            'id' => $message->id,
            'sender' => $message->sender,
            'message' => $message->message,
            'created_at' => $message->created_at->toISOString(),
        ];

        return ['user_message' => $project($user), 'assistant_message' => $project($assistant)];
    }

    public function createSession(int $actorId): ChatSession
    {
        return ChatSession::query()->create(['user_id' => $actorId]);
    }

    /** @return array{session_id: int, user_message_id: int, assistant_message_id: int} */
    public function writeMessages(ChatSession $session, string $question, string $answer): array
    {
        $user = $session->messages()->create(['sender' => 'USER', 'message' => $question]);
        $assistant = $session->messages()->create(['sender' => 'ASSISTANT', 'message' => $answer]);

        return [
            'session_id' => $session->id,
            'user_message_id' => $user->id,
            'assistant_message_id' => $assistant->id,
        ];
    }
}
