<?php

namespace App\Http\Requests\Profile;

use App\Http\Requests\Auth\CredentialRules;
use App\Enums\Gender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        foreach (['name', 'phone', 'address', 'gender', 'date_of_birth'] as $field) {
            if (is_string($this->input($field))) {
                $value = trim($this->input($field));
                $this->merge([$field => $field !== 'name' && $value === '' ? null : $value]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['bail', 'sometimes', 'required', 'string', 'max:255'],
            'phone' => ['bail', 'sometimes', 'nullable', 'string', 'regex:/\A[0-9]{10}\z/'],
            'address' => ['bail', 'sometimes', 'nullable', 'string', 'max:500'],
            'gender' => ['bail', 'sometimes', 'nullable', 'string', Rule::in(array_column(Gender::cases(), 'value'))],
            'date_of_birth' => ['bail', 'sometimes', 'nullable', 'string', 'date_format:Y-m-d',
                'after_or_equal:1900-01-01', 'before_or_equal:'.now('Asia/Ho_Chi_Minh')->toDateString()],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $allowed = ['name', 'phone', 'address', 'gender', 'date_of_birth'];
            foreach (array_diff(array_keys($this->all()), $allowed) as $field) {
                $validator->errors()->add($field, $field === 'email'
                    ? 'Email đăng nhập không được phép thay đổi.' : 'Trường này không được phép cập nhật trong hồ sơ.');
            }
            if (!array_intersect(array_keys($this->all()), $allowed)) {
                $validator->errors()->add('profile', 'Vui lòng gửi ít nhất một trường hồ sơ cần cập nhật.');
            }
        }];
    }

    public function messages(): array
    {
        return [...CredentialRules::messages(),
            'phone.string' => 'Số điện thoại phải là chuỗi ký tự.',
            'phone.regex' => 'Số điện thoại phải gồm đúng 10 chữ số (0–9), không có chữ, mã quốc gia hoặc dấu phân cách.',
            'address.string' => 'Địa chỉ phải là chuỗi ký tự.',
            'address.max' => 'Địa chỉ không được vượt quá 500 ký tự.',
            'gender.string' => 'Giới tính không hợp lệ.',
            'gender.in' => 'Vui lòng chọn Nam, Nữ, Khác hoặc để trống.',
            'date_of_birth.string' => 'Ngày sinh không hợp lệ.',
            'date_of_birth.date_format' => 'Ngày sinh phải là ngày hợp lệ theo định dạng năm-tháng-ngày.',
            'date_of_birth.after_or_equal' => 'Ngày sinh phải từ ngày 01/01/1900 trở đi.',
            'date_of_birth.before_or_equal' => 'Ngày sinh không được ở tương lai.',
        ];
    }
}
