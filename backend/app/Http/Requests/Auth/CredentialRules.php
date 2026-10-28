<?php

namespace App\Http\Requests\Auth;

use App\Services\Auth\RegistrationService;
use Closure;

/** Shared credential policy for registration and self-profile updates. */
final class CredentialRules
{
    private const EMAIL_PATTERN = '~\A[A-Za-z0-9!#$%&\'*+/=?^_`{|}\x7E-]+(?:\.[A-Za-z0-9!#$%&\'*+/=?^_`{|}\x7E-]+)*@(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?\.)+[A-Za-z]{2,63}\z~';

    public static function email(): array
    {
        return ['bail', 'required', 'string', 'max:254', 'email:filter', 'regex:'.self::EMAIL_PATTERN];
    }

    public static function password(): array
    {
        return ['bail', 'required', 'string', 'min:8', 'max:72', 'confirmed',
                function (string $attribute, mixed $value, Closure $fail): void {
                    // Bcrypt accepts at most 72 bytes, fewer than 72 characters for some Unicode input.
                    if (strlen($value) > 72) {
                        $fail('Mật khẩu không được vượt quá 72 byte UTF-8.');
                    }
                    if (str_contains($value, "\0")) {
                        $fail('Mật khẩu không được chứa ký tự null.');
                    }
                    if (preg_match('/[\s\p{C}]/u', $value)) {
                        $fail('Mật khẩu không được chứa khoảng trắng hoặc ký tự điều khiển.');
                    }
                    if (!preg_match('/\p{Lu}/u', $value) || !preg_match('/\p{Ll}/u', $value)
                        || !preg_match('/[0-9]/', $value) || !preg_match('/[^\p{L}\p{N}\s\p{C}]/u', $value)) {
                        $fail('Mật khẩu phải có chữ hoa, chữ thường, số và ký tự đặc biệt.');
                    }
                },
            ];
    }

    public static function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập họ và tên.',
            'name.string' => 'Họ và tên phải là chuỗi ký tự.',
            'name.max' => 'Họ và tên không được vượt quá 255 ký tự.',
            'email.required' => 'Vui lòng nhập địa chỉ email.',
            'email.string' => 'Địa chỉ email không hợp lệ.',
            'email.email' => 'Email phải đúng định dạng, ví dụ: ten@example.com.',
            'email.regex' => 'Email phải đúng định dạng, ví dụ: ten@example.com.',
            'email.max' => 'Địa chỉ email không được vượt quá 254 ký tự.',
            'email.unique' => RegistrationService::DUPLICATE_EMAIL,
            'password.required' => 'Vui lòng nhập mật khẩu.',
            'password.string' => 'Mật khẩu phải là chuỗi ký tự.',
            'password.min' => 'Mật khẩu phải có ít nhất 8 ký tự.',
            'password.max' => 'Mật khẩu không được vượt quá 72 ký tự hoặc 72 byte UTF-8.',
            'password.confirmed' => 'Mật khẩu xác nhận không khớp.',
            'password_confirmation.required' => 'Vui lòng xác nhận mật khẩu.',
            'password_confirmation.string' => 'Mật khẩu xác nhận phải là chuỗi ký tự.',
        ];
    }
}
