<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ThrottleRegistration
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = 'auth:register:'.hash('sha256', (string) $request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $retryAfter = max(1, RateLimiter::availableIn($key));
            return response()->json([
                'message' => 'Bạn đã gửi quá nhiều yêu cầu đăng ký. Vui lòng thử lại sau.',
                'retry_after' => $retryAfter,
            ], 429, ['Retry-After' => (string) $retryAfter]);
        }

        // Count all requests reaching the route, including validation and guest-policy errors.
        RateLimiter::hit($key, 60);

        return $next($request);
    }
}
