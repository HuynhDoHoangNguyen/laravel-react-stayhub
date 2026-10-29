<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PublicPropertyDetailRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return []; }
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (array_keys($this->query()) as $field) {
                $validator->errors()->add($field, 'Chi tiết cơ sở không nhận bộ lọc phòng trống hoặc tham số truy vấn.');
            }
        }];
    }
}
