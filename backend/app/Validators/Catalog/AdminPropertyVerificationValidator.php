<?php

declare(strict_types=1);

namespace App\Validators\Catalog;

use App\DTOs\Catalog\Requests\ReviewPropertyVerificationRequest;
use Illuminate\Contracts\Validation\Factory;

final class AdminPropertyVerificationValidator
{
    public function __construct(private readonly Factory $validation) {}

    public function validate(array $input): ReviewPropertyVerificationRequest
    {
        if (is_string($input['reason'] ?? null)) {
            $input['reason'] = trim($input['reason']);
        }
        $data = $this->validation->make($input, [
            'decision' => ['bail', 'required', 'string', 'in:APPROVE,REJECT'],
            'reason' => ['bail', 'required_if:decision,REJECT', 'prohibited_if:decision,APPROVE', 'nullable', 'string', 'max:500'],
        ], [
            'decision.required' => 'Chọn duyệt hoặc từ chối hồ sơ.',
            'decision.string' => 'Quyết định không hợp lệ.',
            'decision.in' => 'Quyết định không hợp lệ.',
            'reason.required_if' => 'Nhập lý do từ chối hồ sơ.',
            'reason.prohibited_if' => 'Chỉ nhập lý do khi từ chối hồ sơ.',
            'reason.string' => 'Lý do từ chối phải là văn bản.',
            'reason.max' => 'Lý do từ chối tối đa 500 ký tự.',
        ])->validate();
        $approve = $data['decision'] === 'APPROVE';

        return new ReviewPropertyVerificationRequest($approve, $approve ? null : $data['reason']);
    }
}
