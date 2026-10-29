<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PublicPropertyListRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'page' => ['bail', 'sometimes', 'required', 'integer', 'min:1', 'max:2147483647'],
            'per_page' => ['bail', 'sometimes', 'required', 'integer', 'between:1,24'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (array_diff(array_keys($this->query()), ['page', 'per_page']) as $field) {
                $validator->errors()->add($field, 'Danh sách cơ sở chỉ hỗ trợ page và per_page; chưa lọc phòng trống theo ngày.');
            }
        }];
    }

    public function messages(): array
    {
        return ['page.*' => 'Trang phải là số nguyên từ 1 đến 2147483647.',
            'per_page.*' => 'Số cơ sở mỗi trang phải là số nguyên từ 1 đến 24.'];
    }
}
