<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\Auth\AuthUserResource;
use App\Services\Auth\AuthService;
use App\Services\Auth\RegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = $this->authService->login($request->validated(), (string) $request->ip(), $request->session());

        return response()->json([
            'message' => 'Đăng nhập thành công.',
            'user' => (new AuthUserResource($user))->resolve($request),
        ]);
    }

    public function register(RegisterRequest $request, RegistrationService $registrationService): JsonResponse
    {
        $registrationService->register($request->safe()->only(['name', 'email', 'password']));

        return response()->json(['message' => 'Đăng ký thành công. Vui lòng đăng nhập để tiếp tục.'], 201);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => (new AuthUserResource($request->user()))->resolve($request),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->session());

        return response()->json(['message' => 'Đăng xuất thành công.']);
    }
}
