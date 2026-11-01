<?php

namespace App\Services\Auth;

use App\Enums\UserStatus;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class AccountStatusException extends RuntimeException
{
    public readonly string $accountCode;

    public function __construct(UserStatus $status)
    {
        $this->accountCode = $status === UserStatus::BLOCKED ? 'ACCOUNT_BLOCKED' : 'ACCOUNT_INACTIVE';

        parent::__construct($status === UserStatus::BLOCKED
            ? 'Tài khoản của bạn đã bị khóa. Vui lòng liên hệ quản trị viên.'
            : 'Tài khoản của bạn chưa được kích hoạt. Vui lòng liên hệ quản trị viên.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => $this->accountCode,
        ], 403);
    }
}
