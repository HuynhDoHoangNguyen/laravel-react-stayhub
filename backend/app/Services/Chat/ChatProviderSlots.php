<?php

namespace App\Services\Chat;

use App\Exceptions\Chat\ChatProviderException;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;

final class ChatProviderSlots
{
    private const LEASE_SECONDS = 30;

    public function acquire(bool $public = false): ?Lock
    {
        $limit = config('chatbot.max_concurrent');
        $timeout = config('chatbot.timeout_ms');
        if (! is_int($limit) || $limit < 1 || ! is_int($timeout) || $timeout < 1 || $timeout >= self::LEASE_SECONDS * 1000) {
            throw new ChatProviderException(ChatProviderException::UNAVAILABLE);
        }

        $cache = Cache::store('database');
        // Public advice uses only the first slot of the same pool as authenticated chat.
        for ($index = 0; $index < ($public ? 1 : $limit); $index++) {
            $lock = $cache->lock('customer-chat:provider-slot:'.$index, self::LEASE_SECONDS);
            if ($lock->get()) {
                return $lock;
            }
        }

        return null;
    }

    public function hasTimeForProvider(int $startedAt): bool
    {
        // The cache lease rounds down to seconds. Reserve the full provider timeout.
        $elapsedMs = (hrtime(true) - $startedAt) / 1_000_000;

        return $elapsedMs < (self::LEASE_SECONDS - 1) * 1000 - config('chatbot.timeout_ms');
    }
}
