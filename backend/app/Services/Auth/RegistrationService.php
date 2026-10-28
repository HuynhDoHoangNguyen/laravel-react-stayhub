<?php

namespace App\Services\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class RegistrationService
{
    public const DUPLICATE_EMAIL = 'Địa chỉ email này đã được sử dụng.';

    /** @param array{name: string, email: string, password: string} $data */
    public function register(array $data): void
    {
        try {
            DB::transaction(function () use ($data): void {
                User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    // Treat every submitted string as plaintext, even if it resembles a hash.
                    // The hashed cast recognizes this generated hash and does not hash it again.
                    'password' => Hash::make($data['password']),
                    'role' => UserRole::CUSTOMER,
                    'status' => UserStatus::ACTIVE,
                    'email_verified_at' => null,
                ]);

                DB::afterCommit(fn () => Log::info('Đăng ký tài khoản khách hàng thành công.'));
            });
        } catch (UniqueConstraintViolationException $exception) {
            $detail = (string) ($exception->errorInfo[2] ?? '');
            $driverCode = (int) ($exception->errorInfo[1] ?? 0);
            $emailConflict = ($driverCode === 1062
                && preg_match("/for key ['`](?:[^'`]+\\.)?users_email_unique['`]/i", $detail) === 1)
                || (in_array($driverCode, [19, 2067], true)
                    && preg_match('/^UNIQUE constraint failed: users\.email$/i', $detail) === 1);

            if (!$emailConflict) {
                throw $exception;
            }

            // Protect the gap between HTTP unique validation and insertion.
            throw ValidationException::withMessages(['email' => [self::DUPLICATE_EMAIL]]);
        }
    }
}
