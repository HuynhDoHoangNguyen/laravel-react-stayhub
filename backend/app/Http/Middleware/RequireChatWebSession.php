<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

final class RequireChatWebSession
{
    public function __construct(private readonly ThrottleRequests $throttle) {}

    public function handle(Request $request, Closure $next): Response
    {
        $webId = Auth::guard('web')->id();

        if (! $request->hasSession() || $webId === null || $webId !== $request->user()?->getAuthIdentifier()) {
            throw new AuthenticationException();
        }

        // Register here so the limiter is available with cached routes.
        RateLimiter::for('customer-chat', static function (Request $request): Limit {
            return Limit::perMinute(config('chatbot.rate_limit_per_minute') ?: 10)
                ->by((string) $request->user()->getAuthIdentifier())
                ->response(static fn (Request $request, array $headers) => response()->json(
                    ['message' => 'Bạn đã gửi quá nhiều yêu cầu.'],
                    429,
                    [...$headers, 'Access-Control-Expose-Headers' => 'Retry-After'],
                ));
        });

        return $this->throttle->handle($request, $next, 'customer-chat');
    }
}
