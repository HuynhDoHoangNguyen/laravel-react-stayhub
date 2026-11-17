<?php

namespace App\Validators;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBookingServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantity' => ['required', 'numeric', 'min:0.01', 'max:9999.99'],
        ];
    }
}
