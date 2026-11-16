<?php

namespace App\Validators\Auth;

use App\DTOs\Auth\Requests\LoginRequest;
use App\DTOs\Auth\Requests\RegisterRequest;
use App\Enums\UserRole;
use App\Validators\Auth\CredentialRules;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Validation\Rule;
use SensitiveParameter;

final class AuthValidator
{
    public function __construct(private readonly Factory $validation) {}

    public function validateLogin(#[SensitiveParameter] array $input): LoginRequest
    {
        $credentials = $this->validation->make($input, [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'expected_role' => ['bail', 'sometimes', 'required', 'string', Rule::in(array_column(UserRole::cases(), 'value'))],
        ], [
            'email.required' => 'Vui lòng nhập địa chỉ email.',
            'email.string' => 'Địa chỉ email không hợp lệ.',
            'email.email' => 'Địa chỉ email không hợp lệ.',
            'email.max' => 'Địa chỉ email không được vượt quá 255 ký tự.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
            'password.string' => 'Mật khẩu phải là chuỗi ký tự.',
            'expected_role.required' => 'Vui lòng chọn khu vực đăng nhập.',
            'expected_role.string' => 'Khu vực đăng nhập không hợp lệ.',
            'expected_role.in' => 'Khu vực đăng nhập không hợp lệ.',
        ])->validate();

        return new LoginRequest(
            $credentials['email'],
            $credentials['password'],
            isset($credentials['expected_role']) ? UserRole::from($credentials['expected_role']) : null,
        );
    }

    public function validateRegistration(#[SensitiveParameter] array $input): RegisterRequest
    {
        $validated = $this->validation->make($input, [
            'name' => ['bail', 'required', 'string', 'max:255'],
            'email' => [...CredentialRules::email(), 'unique:users,email'],
            'password' => CredentialRules::password(),
            'password_confirmation' => ['bail', 'required', 'string'],
        ], CredentialRules::messages())->validate();

        return new RegisterRequest($validated['name'], $validated['email'], $validated['password']);
    }
}
