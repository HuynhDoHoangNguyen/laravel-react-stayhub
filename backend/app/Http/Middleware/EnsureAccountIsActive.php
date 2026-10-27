<?php

namespace App\Http\Middleware;

use App\Services\Auth\AuthService;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        // Query again rather than trusting a model cached by the auth guard.
        $user = $request->user()?->fresh();
        $session = $request->hasSession() ? $request->session() : null;

        if ($user === null) {
            $this->authService->logout($session);
            throw new AuthenticationException();
        }

        $this->authService->ensureActive($user, $session);

        return $next($request);
    }
}
