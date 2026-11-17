<?php

namespace App\Validators;

use Illuminate\Foundation\Http\FormRequest;

class SetServiceStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'boolean'],
        ];
    }
}
