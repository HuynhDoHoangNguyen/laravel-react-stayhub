<?php

namespace App\Http\Requests\Review;

use Illuminate\Foundation\Http\FormRequest;

class HostReviewQueryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'rating' => ['sometimes', 'integer', 'between:1,5'],
            'property_id' => ['sometimes', 'integer', 'min:1'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get custom error messages for validator failures.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'page.integer' => 'Số trang (page) phải là số nguyên.',
            'page.min' => 'Số trang (page) phải lớn hơn hoặc bằng 1.',
            'per_page.integer' => 'Số phần tử trên mỗi trang (per_page) phải là số nguyên.',
            'per_page.min' => 'Số phần tử trên mỗi trang (per_page) tối thiểu là 1.',
            'per_page.max' => 'Số phần tử trên mỗi trang (per_page) tối đa là 50.',
            'rating.integer' => 'Bộ lọc số sao (rating) phải là số nguyên.',
            'rating.between' => 'Bộ lọc số sao (rating) phải nằm trong khoảng từ 1 đến 5.',
            'property_id.integer' => 'Mã cơ sở lưu trú (property_id) phải là số nguyên.',
            'property_id.min' => 'Mã cơ sở lưu trú (property_id) không hợp lệ.',
            'search.max' => 'Từ khóa tìm kiếm không được vượt quá 255 ký tự.',
        ];
    }
}
