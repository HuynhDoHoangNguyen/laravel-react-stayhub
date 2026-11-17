<?php

namespace App\Validators\Profile;

use App\DTOs\Profile\Requests\ChangePasswordRequest;
use App\DTOs\Profile\Requests\UpdateProfileRequest;
use App\DTOs\Profile\Requests\UploadAvatarRequest;
use App\Enums\Gender;
use App\Validators\Auth\CredentialRules;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use SensitiveParameter;

final class ProfileValidator
{
    public function __construct(private readonly Factory $validation) {}

    public function validateUpdate(array $input): UpdateProfileRequest
    {
        $rules = $this->rules();
        $allowed = array_keys($rules);
        foreach ($allowed as $field) {
            if (is_string($input[$field] ?? null)) {
                $value = trim($input[$field]);
                $input[$field] = $field !== 'name' && $value === '' ? null : $value;
            }
        }

        $validator = $this->validation->make($input, $rules, $this->messages());
        $validator->after(function (Validator $validator) use ($allowed, $input): void {
            $fields = array_keys($input);
            foreach (array_diff($fields, $allowed) as $field) {
                $validator->errors()->add($field, $field === 'email'
                    ? 'Email đăng nhập không được phép thay đổi.' : 'Trường này không được phép cập nhật trong hồ sơ.');
            }
            if (!array_intersect($fields, $allowed)) {
                $validator->errors()->add('profile', 'Vui lòng gửi ít nhất một trường hồ sơ cần cập nhật.');
            }
        });

        return UpdateProfileRequest::fromValidated($validator->validate());
    }

    public function validatePasswordChange(#[SensitiveParameter] array $input): ChangePasswordRequest
    {
        $rules = [
            'current_password' => ['bail', 'required', 'string', 'max:4096'],
            'password' => [...CredentialRules::password(), 'different:current_password'],
            'password_confirmation' => ['required', 'string'],
        ];
        $validator = $this->validation->make($input, $rules, [
            ...CredentialRules::messages(),
            'current_password.required' => 'Vui lòng nhập mật khẩu hiện tại.',
            'current_password.string' => 'Mật khẩu hiện tại không hợp lệ.',
            'current_password.max' => 'Mật khẩu hiện tại quá dài.',
            'password.different' => 'Mật khẩu mới phải khác mật khẩu hiện tại.',
        ]);

        $forbiddenFields = array_diff(array_keys($input), array_keys($rules));
        $validator->after(function (Validator $validator) use ($forbiddenFields): void {
            foreach ($forbiddenFields as $field) {
                $validator->errors()->add($field, 'Trường này không được phép gửi khi đổi mật khẩu.');
            }
        });

        $validated = $validator->validate();

        return new ChangePasswordRequest($validated['current_password'], $validated['password']);
    }

    public function validateAvatarUpload(array $input): UploadAvatarRequest
    {
        $validator = $this->validation->make($input, [
            'avatar' => ['bail', 'required', 'file', 'image',
                'mimetypes:image/jpeg,image/png,image/webp', 'max:2048', 'dimensions:max_width=4096,max_height=4096'],
        ], [
            'avatar.required' => 'Vui lòng chọn ảnh từ máy của bạn.',
            'avatar.file' => 'Tệp ảnh tải lên không hợp lệ.',
            'avatar.uploaded' => 'Không thể nhận tệp ảnh. Vui lòng chọn ảnh tối đa 2 MB.',
            'avatar.image' => 'Tệp phải là ảnh JPG, PNG hoặc WebP hợp lệ.',
            'avatar.mimetypes' => 'Chỉ chấp nhận ảnh JPG, PNG hoặc WebP.',
            'avatar.max' => 'Ảnh đại diện không được vượt quá 2 MB.',
            'avatar.dimensions' => 'Kích thước ảnh không được vượt quá 4096 × 4096 pixel.',
        ]);

        $forbiddenFields = array_diff(array_keys($input), ['avatar']);
        $validator->after(function (Validator $validator) use ($forbiddenFields): void {
            foreach ($forbiddenFields as $field) {
                $validator->errors()->add($field, 'Chỉ được gửi tệp ảnh đại diện của tài khoản hiện tại.');
            }
        });

        return new UploadAvatarRequest($validator->validate()['avatar']);
    }

    private function rules(): array
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

    private function messages(): array
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
