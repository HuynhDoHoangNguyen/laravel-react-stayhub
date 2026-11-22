<?php

namespace App\Validators\Commission;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CommissionValidator
{
    /**
     * Ủy quyền truy cập: Bắt buộc role HOST.
     */
    public function authorizeHost(?User $actor): void
    {
        if ($actor === null || ($actor->role?->value !== 'HOST' && $actor->role !== 'HOST')) {
            throw new AuthorizationException('Chỉ tài khoản Host mới có quyền truy cập.');
        }
    }

    /**
     * Ủy quyền truy cập: Bắt buộc role ADMIN.
     */
    public function authorizeAdmin(?User $actor): void
    {
        if ($actor === null || ($actor->role?->value !== 'ADMIN' && $actor->role !== 'ADMIN')) {
            throw new AuthorizationException('Chỉ tài khoản Admin mới có quyền truy cập.');
        }
    }

    /**
     * Xác thực tham số định danh nguyên dương trước khi chuyển đổi.
     */
    public function validateId(int|string $id): int
    {
        $str = trim((string) $id);
        if (! preg_match('/^[1-9]\d*$/', $str)) {
            throw new NotFoundHttpException('Không tìm thấy tài nguyên yêu cầu.');
        }

        return (int) $str;
    }

    /**
     * Chuẩn hóa số lượng phần tử trên mỗi trang.
     */
    public function validatePerPage(mixed $perPage, int $default = 15, int $max = 100): int
    {
        $val = (int) $perPage;
        if ($val <= 0) {
            return $default;
        }

        return min($val, $max);
    }

    /**
     * Xác thực payload nộp khai báo đối soát của Host.
     */
    public function validateSubmitRemittancePayload(array $payload): array
    {
        $validator = Validator::make($payload, [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'bank_transaction_reference' => ['nullable', 'string', 'max:128'],
            'idempotency_key' => ['required', 'string', 'max:64'],
        ], [
            'amount.required' => 'Số tiền nộp là bắt buộc.',
            'amount.numeric' => 'Số tiền nộp phải là một giá trị số hợp lệ.',
            'amount.min' => 'Số tiền nộp phải lớn hơn 0.',
            'idempotency_key.required' => 'Khóa idempotency là bắt buộc.',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * Xác thực payload Admin duyệt đối soát (Verify).
     */
    public function validateVerifyRemittancePayload(array $payload): array
    {
        $validator = Validator::make($payload, [
            'verified_bank_reference' => ['required', 'string', 'max:128'],
            'review_note' => ['nullable', 'string', 'max:1000'],
        ], [
            'verified_bank_reference.required' => 'Mã giao dịch ngân hàng trích từ sao kê là bắt buộc.',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * Xác thực payload Admin từ chối đối soát (Reject).
     */
    public function validateRejectRemittancePayload(array $payload): array
    {
        $validator = Validator::make($payload, [
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ], [
            'reason.required' => 'Lý do từ chối là bắt buộc.',
            'reason.min' => 'Lý do từ chối phải có ít nhất 3 ký tự.',
            'reason.max' => 'Lý do từ chối không được vượt quá 1000 ký tự.',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }
}
