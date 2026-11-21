<?php

namespace App\Validators\Agreement;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
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
            'content_sha256' => ['required', 'string', 'size:64'],
        ], [
            'accepted.accepted' => 'Bạn phải đồng ý với các điều khoản của hợp đồng đối tác.',
            'content_sha256.required' => 'Mã xác thực nội dung hợp đồng là bắt buộc.',
            'content_sha256.size' => 'Mã xác thực nội dung không hợp lệ.',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }
}
