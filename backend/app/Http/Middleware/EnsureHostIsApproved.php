<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\HostAgreementAcceptance;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHostIsApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null) {
            $role = $user->role instanceof UserRole ? $user->role->value : (string) $user->role;

            if ($role === UserRole::HOST->value) {
                $isPendingRegistrationApproval = HostAgreementAcceptance::query()
                    ->where('host_id', $user->id)
                    ->where('method', 'REGISTRATION_PENDING')
                    ->exists();

                if ($isPendingRegistrationApproval) {
                    return response()->json([
                        'success' => false,
                        'code' => 'HOST_APPROVAL_PENDING',
                        'message' => 'Tài khoản Đối tác của bạn đang chờ Quản trị viên phê duyệt. Hiện tại bạn chỉ có thể xem và cập nhật Hồ sơ cá nhân.',
                    ], Response::HTTP_FORBIDDEN);
                }
            }
        }

        return $next($request);
    }
}

