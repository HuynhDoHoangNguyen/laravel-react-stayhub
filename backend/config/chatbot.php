<?php

$positiveInteger = static fn (mixed $value): int|false => filter_var(
    $value,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]],
);

return [
    'enabled' => filter_var(env('SUPPORT_CHAT_AI_ENABLED', false), FILTER_VALIDATE_BOOL),
    'allow_external_questions' => filter_var(env('SUPPORT_CHAT_ALLOW_EXTERNAL_QUESTIONS', false), FILTER_VALIDATE_BOOL),
    'api_key' => env('GEMINI_API_KEY'),
    'model' => env('GEMINI_MODEL', 'gemini-3.5-flash-lite'),
    'timeout_ms' => $positiveInteger(env('SUPPORT_CHAT_TIMEOUT_MS', 12000)),
    'max_output_tokens' => $positiveInteger(env('SUPPORT_CHAT_MAX_OUTPUT_TOKENS', 500)),
    'rate_limit_per_minute' => $positiveInteger(env('SUPPORT_CHAT_RATE_LIMIT_PER_MINUTE', 10)),
    'max_concurrent' => $positiveInteger(env('SUPPORT_CHAT_MAX_CONCURRENT', 2)),
    'guest' => [
        'enabled' => filter_var(env('SUPPORT_GUEST_CHAT_ENABLED', false), FILTER_VALIDATE_BOOL),
        'allow_provider' => filter_var(env('SUPPORT_GUEST_CHAT_ALLOW_PROVIDER', false), FILTER_VALIDATE_BOOL),
    ],
];
