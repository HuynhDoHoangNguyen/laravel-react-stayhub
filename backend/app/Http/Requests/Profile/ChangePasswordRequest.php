<?php

namespace App\Http\Requests\Profile;

use App\Http\Requests\Auth\CredentialRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['bail', 'required', 'string', 'max:4096'],
            'password' => [...CredentialRules::password(), 'different:current_password'],
            'password_confirmation' => ['required', 'string'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (array_diff(array_keys($this->all()), ['current_password', 'password', 'password_confirmation']) as $field) {
                $validator->errors()->add($field, 'Trường này không được phép gửi khi đổi mật khẩu.');
            }
        }];
    }

    public function messages(): array
    {
        return [...CredentialRules::messages(),
            'current_password.required' => 'Vui lòng nhập mật khẩu hiện tại.',
            'current_password.string' => 'Mật khẩu hiện tại không hợp lệ.',
            'current_password.max' => 'Mật khẩu hiện tại quá dài.',
            'password.different' => 'Mật khẩu mới phải khác mật khẩu hiện tại.',
        ];
    }
}
