<?php

namespace App\Repositories\UserManagement;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Collection;

class AccountStatusRepository
{
    private const COLUMNS = ['id', 'role', 'status', 'updated_at'];

    /** @return Collection<int, User> */
    public function lockActorAndTarget(int $actorId, int $targetId): Collection
    {
        $ids = array_unique([$actorId, $targetId]);
        sort($ids, SORT_NUMERIC);
        $users = new Collection;

        // Individual primary-key lookups make lock acquisition order explicit.
        foreach ($ids as $id) {
            $user = User::query()->select(self::COLUMNS)->lockForUpdate()->find($id);
            if ($user !== null) {
                $users->put($id, $user);
            }
        }

        return $users;
    }

    public function updateStatus(User $target, UserStatus $status): User
    {
        User::query()->whereKey($target->id)->update([
            'status' => $status->value,
            'updated_at' => $target->freshTimestamp(),
        ]);

        // Read the stored timestamp so response precision matches persistence.
        return User::query()->select(self::COLUMNS)->findOrFail($target->id);
    }
}
