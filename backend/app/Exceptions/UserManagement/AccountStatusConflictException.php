<?php

namespace App\Exceptions\UserManagement;

use RuntimeException;

final class AccountStatusConflictException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Trạng thái tài khoản đã thay đổi hoặc không hỗ trợ thao tác này. Vui lòng tải lại thông tin.');
    }
}
