<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Mappers\Auth\AuthUserMapper;
use App\Services\Profile\ProfileService;
use App\Services\Profile\AvatarService;
use App\Validators\Profile\ProfileValidator;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(private readonly AuthUserMapper $userMapper) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userMapper->toResponse($request->user())]);
    }

    public function update(Request $request, ProfileValidator $validator, ProfileService $service): JsonResponse
    {
        $user = $service->update($request->user(), $validator->validateUpdate($request->all()));
        return response()->json([
            'message' => 'Cập nhật hồ sơ thành công.',
            'user' => $this->userMapper->toResponse($user),
        ]);
    }

    public function password(Request $request, ProfileValidator $validator, ProfileService $service): JsonResponse
    {
        $service->changePassword($request->user(), $validator->validatePasswordChange($request->all()));
        // Sanctum stores this guard user's current password fingerprint after response.
        $request->user()->refresh();
        return response()->json(['message' => 'Đổi mật khẩu thành công.']);
    }

    public function uploadAvatar(Request $request, ProfileValidator $validator, AvatarService $service): JsonResponse
    {
        $user = $service->upload($request->user(), $validator->validateAvatarUpload($request->all()));
        return response()->json(['message' => 'Cập nhật ảnh đại diện thành công.',
            'user' => $this->userMapper->toResponse($user)]);
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
