<?php

namespace App\Exceptions\Auth;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

final class LoginThrottledException extends RuntimeException implements ShouldntReport
{
    public function __construct(public readonly int $retryAfter)
    {
        parent::__construct('Bạn đã thử đăng nhập quá nhiều lần. Vui lòng thử lại sau.');
    }
}
