<?php

namespace App\Services\Chat;

use App\Exceptions\Chat\ChatProviderException;
use Illuminate\Container\Attributes\Bind;

#[Bind(GeminiChatProvider::class)]
interface ChatProvider
{
    /**
     * The caller must approve the current question for external disclosure before invoking the provider.
     *
     * @throws ChatProviderException
     */
    public function answer(#[\SensitiveParameter] string $approvedQuestion): string;
}
