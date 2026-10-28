<?php

namespace App\Services\Profile;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class AvatarService
{
    public static function managedPath(User $user): ?string
    {
        $path = (string) $user->avatar;
        return preg_match('~\Aprofile-avatars/'.$user->id.'/[a-f0-9-]{36}\.(?:jpg|jpeg|png|webp)\z~D', $path) === 1 ? $path : null;
    }

    public function upload(User $actor, UploadedFile $file): User
    {
        $path = $file->storeAs('profile-avatars/'.$actor->id, Str::uuid().'.'.$file->extension(), 'local');
        if ($path === false) throw new RuntimeException('Avatar storage failed.');
        $oldPath = null;
        try {
            $user = DB::transaction(function () use ($actor, $path, &$oldPath): User {
                $user = User::query()->lockForUpdate()->findOrFail($actor->id);
                $oldPath = self::managedPath($user);
                $user->avatar = $path;
                $user->save();
                return $user->refresh();
            });
        } catch (Throwable $error) {
            // Compensate the file write when the database transaction fails.
            $this->cleanup($path);
            throw $error;
        }
        if ($oldPath !== null && $oldPath !== $path) $this->cleanup($oldPath);
        Log::info('Cập nhật ảnh đại diện thành công.');
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
