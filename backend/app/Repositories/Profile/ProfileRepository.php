<?php

namespace App\Repositories\Profile;

use App\DTOs\Profile\Requests\UpdateProfileRequest;
use App\Models\User;
use SensitiveParameter;

final class ProfileRepository
{
    public function update(int $userId, UpdateProfileRequest $request): User
    {
        $user = $this->findUserForUpdate($userId);
        $user->fill($request->attributes())->save();

        return $user->refresh();
    }

    public function findUserForUpdate(int $userId): User
    {
        return User::query()->lockForUpdate()->findOrFail($userId);
    }

    public function updatePassword(User $user, #[SensitiveParameter] string $passwordHash): void
    {
        $user->password = $passwordHash;
        $user->save();
    }

    public function updateAvatar(User $user, string $path): User
    {
        $user->avatar = $path;
        $user->save();

        return $user->refresh();
    }
}
