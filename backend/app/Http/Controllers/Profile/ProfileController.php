<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\ChangePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Requests\Profile\UploadAvatarRequest;
use App\Http\Resources\Auth\AuthUserResource;
use App\Services\Profile\ProfileService;
use App\Services\Profile\AvatarService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['user' => (new AuthUserResource($request->user()))->resolve($request)]);
    }

    public function update(UpdateProfileRequest $request, ProfileService $service): JsonResponse
    {
        $user = $service->update($request->user(), $request->safe()->only(['name', 'phone', 'address', 'gender', 'date_of_birth']));
        return response()->json([
            'message' => 'Cập nhật hồ sơ thành công.',
            'user' => (new AuthUserResource($user))->resolve($request),
        ]);
    }

    public function password(ChangePasswordRequest $request, ProfileService $service): JsonResponse
    {
        $service->changePassword($request->user(), $request->safe()->only(['current_password', 'password']));
        // Sanctum stores this guard user's current password fingerprint after response.
        $request->user()->refresh();
        return response()->json(['message' => 'Đổi mật khẩu thành công.']);
    }

    public function uploadAvatar(UploadAvatarRequest $request, AvatarService $service): JsonResponse
    {
        $user = $service->upload($request->user(), $request->file('avatar'));
        return response()->json(['message' => 'Cập nhật ảnh đại diện thành công.',
            'user' => (new AuthUserResource($user))->resolve($request)]);
    }

    public function avatar(Request $request): StreamedResponse
    {
        $path = AvatarService::managedPath($request->user());
        abort_unless($path !== null && Storage::disk('local')->exists($path), 404);
        return Storage::disk('local')->response($path, null, [
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }
}
