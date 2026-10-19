<?php

namespace App\Http\Requests\Review;

use Illuminate\Foundation\Http\FormRequest;

class PublicReviewQueryRequest extends FormRequest
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
            'sort_by' => ['sometimes', 'string', 'in:latest,rating_desc,rating_asc'],
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
            'sort_by.in' => 'Kiểu sắp xếp (sort_by) không hợp lệ. Chỉ chấp nhận: latest, rating_desc, rating_asc.',
        ];
    }
}
