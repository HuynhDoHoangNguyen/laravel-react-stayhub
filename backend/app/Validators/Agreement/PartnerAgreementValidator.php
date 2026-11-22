<?php

namespace App\Validators\Agreement;

use App\Models\User;
use App\Services\Auth\RegistrationService;
use App\Validators\Auth\CredentialRules;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PartnerAgreementValidator
{
    /**
     * Ủy quyền tài khoản: Bắt buộc role HOST.
     */
    public function authorizeHost(?User $actor): void
    {
        if ($actor === null || ($actor->role?->value !== 'HOST' && $actor->role !== 'HOST')) {
            throw new AuthorizationException('Chỉ tài khoản Host mới có quyền truy cập.');
        }
    }

    /**
     * Xác thực tham số định danh hợp đồng nguyên dương.
     */
    public function validateId(int|string $id): int
    {
        $str = trim((string) $id);
        if (! preg_match('/^[1-9]\d*$/', $str)) {
            throw new NotFoundHttpException('Phiên bản hợp đồng không tồn tại.');
        }

        return (int) $str;
    }

    /**
     * Xác thực dữ liệu chấp thuận thỏa thuận đối tác (Clickwrap).
     */
    public function validateAcceptPayload(array $payload): array
    {
        $validator = Validator::make($payload, [
            'accepted' => ['required', 'boolean', 'accepted'],
            'content_sha256' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/i'],
        ], [
            'accepted.accepted' => 'Bạn phải đồng ý với các điều khoản của hợp đồng đối tác.',
            'content_sha256.required' => 'Mã xác thực nội dung hợp đồng là bắt buộc.',
            'content_sha256.size' => 'Mã xác thực nội dung không hợp lệ.',
            'content_sha256.regex' => 'Mã xác thực nội dung không hợp lệ.',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * Kiểm tra định dạng email trước khi truy vấn kiểm tra trùng lặp trong DB.
     */
    public function registrationEmailForLookup(#[SensitiveParameter] array $input): ?string
    {
        $validator = Validator::make($input, ['email' => CredentialRules::email()], CredentialRules::messages());

        return $validator->fails() ? null : $validator->validated()['email'];
    }

    /**
     * Xác thực dữ liệu đăng ký tài khoản Đối tác (Host) kèm chấp thuận hợp đồng hoa hồng 10%.
     */
    public function validateHostRegistrationPayload(#[SensitiveParameter] array $input, bool $emailExists): array
    {
        $messages = array_merge(CredentialRules::messages(), [
            'accepted.required' => 'Bạn phải đồng ý với Hợp đồng Đối tác và mức phí hoa hồng 10%.',
            'accepted.boolean' => 'Trạng thái chấp thuận hợp đồng không hợp lệ.',
            'accepted.accepted' => 'Bạn phải đồng ý với Hợp đồng Đối tác và mức phí hoa hồng 10%.',
            'content_sha256.required' => 'Mã xác thực nội dung hợp đồng là bắt buộc.',
            'content_sha256.string' => 'Mã xác thực nội dung hợp đồng không hợp lệ.',
            'content_sha256.size' => 'Mã xác thực nội dung hợp đồng không hợp lệ.',
            'content_sha256.regex' => 'Mã xác thực nội dung hợp đồng không hợp lệ.',
        ]);

        $validator = Validator::make($input, [
            'name' => ['bail', 'required', 'string', 'max:255'],
            'email' => [...CredentialRules::email(), static function (string $attribute, mixed $value, Closure $fail) use ($emailExists): void {
                if ($emailExists) {
                    $fail(RegistrationService::DUPLICATE_EMAIL);
                }
            }],
            'password' => CredentialRules::password(),
            'password_confirmation' => ['bail', 'required', 'string'],
            'accepted' => ['bail', 'required', 'boolean', 'accepted'],
            'content_sha256' => ['bail', 'required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/i'],
        ], $messages);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }
}
