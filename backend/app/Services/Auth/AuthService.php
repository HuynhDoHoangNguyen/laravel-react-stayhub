<?php

namespace App\Services\Auth;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthService
{
    private const MAX_FAILURES = 5;
    private const DECAY_SECONDS = 60;

    /** @param array{email: string, password: string} $credentials */
    public function login(array $credentials, string $ip, Session $session): User
    {
        // Hash the identifier so the cache key does not contain an email or IP.
        $key = 'auth:login:'.hash('sha256', Str::lower(trim($credentials['email'])).'|'.$ip);

        if (RateLimiter::tooManyAttempts($key, self::MAX_FAILURES)) {
            $retryAfter = max(1, RateLimiter::availableIn($key));
            throw new HttpResponseException(response()->json([
                'message' => 'Bạn đã thử đăng nhập quá nhiều lần. Vui lòng thử lại sau.',
                'retry_after' => $retryAfter,
            ], 429, ['Retry-After' => (string) $retryAfter]));
        }

        $rejectedStatus = null;
        $guard = Auth::guard('web');
        $authenticated = $guard->attemptWhen($credentials, function (User $user) use (&$rejectedStatus): bool {
            // Laravel only invokes this callback after verifying the password.
            if ($user->status !== UserStatus::ACTIVE) {
                $rejectedStatus = $user->status;
                return false;
            }

            return true;
        });

        if (!$authenticated) {
            RateLimiter::hit($key, self::DECAY_SECONDS);

            if ($rejectedStatus !== null) {
                $this->logout($session);
                throw new AccountStatusException($rejectedStatus);
            }

            throw ValidationException::withMessages([
                'email' => ['Email hoặc mật khẩu không chính xác.'],
            ]);
        }

        RateLimiter::clear($key);
        $session->regenerate();
        Log::info('Đăng nhập thành công.');

        return $guard->user();
    }

    public function ensureActive(User $user, ?Session $session): void
    {
        if ($user->status !== UserStatus::ACTIVE) {
            $this->logout($session);
            throw new AccountStatusException($user->status);
        }
    }

    public function logout(?Session $session): void
    {
        Auth::guard('web')->logout();

        if ($session !== null) {
            $session->invalidate();
            $session->regenerateToken();
        }

        Log::info('Đã dọn phiên đăng nhập hiện tại.');
    }
}
