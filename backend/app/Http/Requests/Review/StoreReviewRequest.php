<?php

namespace App\Http\Requests\Review;

use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
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
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rating.required' => 'Điểm đánh giá là bắt buộc.',
            'rating.integer' => 'Điểm đánh giá phải là số nguyên.',
            'rating.between' => 'Điểm đánh giá phải nằm trong khoảng từ 1 đến 5 sao.',
            'comment.required' => 'Nội dung đánh giá không được để trống.',
            'comment.string' => 'Nội dung đánh giá phải là chuỗi văn bản.',
            'comment.min' => 'Nội dung đánh giá phải có tối thiểu 5 ký tự.',
            'comment.max' => 'Nội dung đánh giá không được vượt quá 1000 ký tự (quy định V1).',
        ];
    }
}
