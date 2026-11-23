<?php

namespace App\Services\Profile;

use App\DTOs\Profile\Requests\ChangePasswordRequest;
use App\DTOs\Profile\Requests\UpdateProfileRequest;
use App\Models\User;
use App\Repositories\Profile\ProfileRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

class ProfileService
{
    public function __construct(private readonly ProfileRepository $profiles) {}

    public function update(User $actor, UpdateProfileRequest $request): User
    {
        return DB::transaction(function () use ($actor, $request): User {
            $user = $this->profiles->update($actor->id, $request);
            DB::afterCommit(fn () => Log::info('Profile updated successfully.'));

            return $user;
        });
    }

    public function changePassword(User $actor, #[SensitiveParameter] ChangePasswordRequest $request): void
    {
        DB::transaction(function () use ($actor, $request): void {
            $user = $this->profiles->findUserForUpdate($actor->id);
            if (!Hash::check($request->currentPassword, $user->password)) {
                throw ValidationException::withMessages(['current_password' => ['Mật khẩu hiện tại không chính xác.']]);
            }
            $this->profiles->updatePassword($user, Hash::make($request->newPassword));
            DB::afterCommit(fn () => Log::info('Đổi mật khẩu cá nhân thành công.'));
        });
    }
}
