<?php

namespace App\Services\Profile;

use App\DTOs\Profile\Requests\UploadAvatarRequest;
use App\Models\User;
use App\Repositories\Profile\ProfileRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class AvatarService
{
    public function __construct(private readonly ProfileRepository $profiles) {}

    public static function managedPath(User $user): ?string
    {
        $path = (string) $user->avatar;
        return preg_match('~\Aprofile-avatars/'.$user->id.'/[a-f0-9-]{36}\.(?:jpg|jpeg|png|webp)\z~D', $path) === 1 ? $path : null;
    }

    public function upload(User $actor, UploadAvatarRequest $request): User
    {
        $file = $request->avatar;
        $path = $file->storeAs('profile-avatars/'.$actor->id, Str::uuid().'.'.$file->extension(), 'local');
        if ($path === false) throw new RuntimeException('Avatar storage failed.');
        $oldPath = null;
        $compensated = false;
        $compensate = function () use ($path, &$compensated): void {
            if ($compensated) return;
            $compensated = true;
            $this->cleanup($path);
        };
        $connection = DB::connection();
        try {
            $user = $connection->transaction(function () use ($actor, $path, &$oldPath, $connection, $compensate): User {
                // Committed savepoints lose their rollback callbacks in Laravel. Each active
                // ancestor must also own compensation if it later rolls back.
                foreach (app('db.transactions')->getPendingTransactions() as $transaction) {
                    if ($transaction->connection === $connection->getName()) {
                        $transaction->addCallbackForRollback($compensate);
                    }
                }
                $user = $this->profiles->findUserForUpdate($actor->id);
                $oldPath = self::managedPath($user);

                return $this->profiles->updateAvatar($user, $path);
            });
        } catch (Throwable $error) {
            // Compensate the file write when the database transaction fails.
            $compensate();
            throw $error;
        }
        // Register after the inner transaction returns, so commit callback failures
        // cannot trigger compensation for an already committed avatar.
        $connection->afterCommit(function () use ($oldPath, $path): void {
            if ($oldPath !== null && $oldPath !== $path) $this->cleanup($oldPath);
            Log::info('Cập nhật ảnh đại diện thành công.');
        });

        return $user;
    }

    private function cleanup(string $path): void
    {
        try {
            if (!Storage::disk('local')->delete($path)) Log::warning('Không thể dọn tệp ảnh đại diện cũ.');
        } catch (Throwable) {
            // A cleanup failure must not undo a committed avatar or hide the DB error.
            Log::warning('Không thể dọn tệp ảnh đại diện.');
        }
    }
}
