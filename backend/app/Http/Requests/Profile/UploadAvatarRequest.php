<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UploadAvatarRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() !== null; }

    public function rules(): array
    {
        return ['avatar' => ['bail', 'required', 'file', 'image',
            'mimetypes:image/jpeg,image/png,image/webp', 'max:2048', 'dimensions:max_width=4096,max_height=4096']];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (array_diff(array_keys($this->all()), ['avatar']) as $field) {
                $validator->errors()->add($field, 'Chỉ được gửi tệp ảnh đại diện của tài khoản hiện tại.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'avatar.required' => 'Vui lòng chọn ảnh từ máy của bạn.',
            'avatar.file' => 'Tệp ảnh tải lên không hợp lệ.',
            'avatar.uploaded' => 'Không thể nhận tệp ảnh. Vui lòng chọn ảnh tối đa 2 MB.',
            'avatar.image' => 'Tệp phải là ảnh JPG, PNG hoặc WebP hợp lệ.',
            'avatar.mimetypes' => 'Chỉ chấp nhận ảnh JPG, PNG hoặc WebP.',
            'avatar.max' => 'Ảnh đại diện không được vượt quá 2 MB.',
            'avatar.dimensions' => 'Kích thước ảnh không được vượt quá 4096 × 4096 pixel.',
        ];
    }
}
