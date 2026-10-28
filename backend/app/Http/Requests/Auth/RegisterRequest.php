<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['name', 'email'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'max:255'],
            'email' => [...CredentialRules::email(), 'unique:users,email'],
            'password' => CredentialRules::password(),
            'password_confirmation' => ['bail', 'required', 'string'],
        ];
    }

    public function messages(): array
    {
        return CredentialRules::messages();
    }
}
