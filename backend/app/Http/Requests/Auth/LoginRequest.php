<?php

namespace App\Http\Requests\Auth;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            // Preserve the database's existing case sensitivity for credential lookup.
            $this->merge(['email' => trim($this->input('email'))]);
        }
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'expected_role' => ['bail', 'sometimes', 'required', 'string', Rule::in(array_column(UserRole::cases(), 'value'))],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Vui lòng nhập địa chỉ email.',
            'email.string' => 'Địa chỉ email không hợp lệ.',
            'email.email' => 'Địa chỉ email không hợp lệ.',
            'email.max' => 'Địa chỉ email không được vượt quá 255 ký tự.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
            'password.string' => 'Mật khẩu phải là chuỗi ký tự.',
            'expected_role.required' => 'Vui lòng chọn khu vực đăng nhập.',
            'expected_role.string' => 'Khu vực đăng nhập không hợp lệ.',
            'expected_role.in' => 'Khu vực đăng nhập không hợp lệ.',
        ];
    }
}
