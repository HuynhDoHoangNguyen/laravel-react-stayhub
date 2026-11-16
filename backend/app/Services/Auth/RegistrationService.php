<?php

namespace App\Services\Auth;

use App\DTOs\Auth\Requests\RegisterRequest;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Repositories\Auth\AuthRepository;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use SensitiveParameter;

class RegistrationService
{
    public const DUPLICATE_EMAIL = 'Địa chỉ email này đã được sử dụng.';

    public function __construct(private readonly AuthRepository $repository) {}

    public function register(#[SensitiveParameter] RegisterRequest $request): void
    {
        try {
            DB::transaction(function () use ($request): void {
                $this->repository->createUser([
                    'name' => $request->name,
                    'email' => $request->email,
                    // Treat every submitted string as plaintext, even if it resembles a hash.
                    // The hashed cast recognizes this generated hash and does not hash it again.
                    'password' => Hash::make($request->password),
                    'role' => UserRole::CUSTOMER,
                    'status' => UserStatus::ACTIVE,
                    'email_verified_at' => null,
                ]);

                DB::afterCommit(fn () => Log::info('Đăng ký tài khoản khách hàng thành công.'));
            });
        } catch (UniqueConstraintViolationException $exception) {
            EmailUniqueConflict::rethrow($exception);
        }
    }
}
