<?php

namespace App\Services\Profile;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ProfileService
{
    public function update(User $actor, array $data): User
    {
        return DB::transaction(function () use ($actor, $data): User {
            $user = User::query()->lockForUpdate()->findOrFail($actor->id);
            $user->fill(Arr::only($data, ['name', 'phone', 'address', 'gender', 'date_of_birth']))->save();
            DB::afterCommit(fn () => Log::info('Profile updated successfully.'));
            return $user->refresh();
        });
    }

    public function changePassword(User $actor, array $data): void
    {
        DB::transaction(function () use ($actor, $data): void {
            $user = User::query()->lockForUpdate()->findOrFail($actor->id);
            if (!Hash::check($data['current_password'], $user->password)) {
                throw ValidationException::withMessages(['current_password' => ['Mật khẩu hiện tại không chính xác.']]);
            }
            $user->password = Hash::make($data['password']);
            $user->save();
            DB::afterCommit(fn () => Log::info('Đổi mật khẩu cá nhân thành công.'));
        });
    }
}
