<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mappers\Auth\AuthUserMapper;
use App\Services\Auth\AuthService;
use App\Services\Auth\RegistrationService;
use App\Validators\Auth\AuthValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly AuthUserMapper $userMapper,
        private readonly AuthValidator $validator,
    ) {}

    public function login(Request $request): JsonResponse
    {
        $email = $request->input('email');
        if (is_string($email)) {
            $request->merge(['email' => trim($email)]);
        }
        $credentials = $this->validator->validateLogin($request->all());
        $user = $this->authService->login($credentials, (string) $request->ip(), $request->session());

        return response()->json([
            'message' => 'Đăng nhập thành công.',
            'user' => $this->userMapper->toResponse($user),
        ]);
    }

    public function register(Request $request, RegistrationService $registrationService): JsonResponse
    {
        foreach (['name', 'email'] as $field) {
            $value = $request->input($field);
            if (is_string($value)) {
                $request->merge([$field => trim($value)]);
            }
        }

        $registrationService->register($this->validator->validateRegistration($request->all()));

        return response()->json(['message' => 'Đăng ký thành công. Vui lòng đăng nhập để tiếp tục.'], 201);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->userMapper->toResponse($request->user()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->session());

        return response()->json(['message' => 'Đăng xuất thành công.']);
    }
}
