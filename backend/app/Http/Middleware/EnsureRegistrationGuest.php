<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureRegistrationGuest
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('web')->check()) {
            return response()->json([
                'message' => 'Bạn đang đăng nhập. Vui lòng đăng xuất trước khi đăng ký tài khoản mới.',
                'code' => 'ALREADY_AUTHENTICATED',
            ], 409);
        }

        return $next($request);
    }
}
