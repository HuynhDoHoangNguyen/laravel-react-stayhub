<?php
// TEMP-M1-STUB

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
                'errors' => [],
            ], Response::HTTP_UNAUTHORIZED);
        }

        $userStatus = $user->status instanceof UserStatus ? $user->status->value : (string) $user->status;
        if ($userStatus === UserStatus::BLOCKED->value) {
            return response()->json([
                'success' => false,
                'message' => 'Tài khoản của bạn đã bị khóa.',
                'errors' => [],
            ], Response::HTTP_FORBIDDEN);
        }

        if (empty($roles)) {
            return $next($request);
        }

        $userRole = $user->role instanceof \UnitEnum ? $user->role->value : (string) $user->role;

        // Flatten roles if passed like "role:HOST,ADMIN"
        $allowedRoles = [];
        foreach ($roles as $role) {
            foreach (explode(',', $role) as $r) {
                $allowedRoles[] = trim($r);
            }
        }

        if (! in_array($userRole, $allowedRoles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền truy cập.',
                'errors' => [],
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
