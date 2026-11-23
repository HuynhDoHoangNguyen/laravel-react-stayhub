<?php

namespace App\Validators\HostReport;

use App\DTOs\HostReport\Requests\CreateHostReportRequest;
use App\Enums\HostReportReason;
use App\Models\HostReport;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class HostReportValidator
{
    public function validate(array $input): CreateHostReportRequest
    {
        if (is_string($input['description'] ?? null)) {
            $input['description'] = preg_replace('/^\s+|\s+$/u', '', $input['description']) ?? $input['description'];
        }

        $data = Validator::make($input, [
            'reason' => ['bail', 'required', 'string', Rule::enum(HostReportReason::class)],
            'description' => ['bail', 'required', 'string', 'min:20', 'max:1000'],
            'evidence' => ['bail', 'nullable', 'file', 'max:'.(HostReport::EVIDENCE_MAX_BYTES / 1024)],
        ], [
            'reason.*' => 'Vui lòng chọn lý do báo cáo hợp lệ.',
            'description.required' => 'Vui lòng mô tả sự việc.',
            'description.string' => 'Mô tả phải là văn bản.',
            'description.min' => 'Mô tả cần ít nhất 20 ký tự.',
            'description.max' => 'Mô tả tối đa 1.000 ký tự.',
            'evidence.file' => 'Chứng cứ phải là một tệp PDF, JPG hoặc PNG.',
            'evidence.max' => 'Chứng cứ tối đa 2 MiB.',
            'evidence.uploaded' => 'Không tải lên được chứng cứ. Hãy chọn tệp tối đa 2 MiB.',
        ])->validate();

        $evidence = $data['evidence'] ?? null;
        $mime = $evidence?->getMimeType();
        if ($evidence !== null) {
            $extension = strtolower($evidence->getClientOriginalExtension());
            $extension = $extension === 'jpeg' ? 'jpg' : $extension;
            if ($evidence->getSize() < 1 || (HostReport::EVIDENCE_EXTENSIONS[$mime] ?? null) !== $extension) {
                throw ValidationException::withMessages(['evidence' => 'Chọn tệp PDF, JPG hoặc PNG có nội dung đúng định dạng và không rỗng.']);
            }
        }

        return new CreateHostReportRequest(HostReportReason::from($data['reason']), $data['description'], $evidence, $mime);
    }

    /** @param list<HostReportReason> $allowedReasons */
    public function ensureAllowedReason(HostReportReason $reason, array $allowedReasons): void
    {
        if (!in_array($reason, $allowedReasons, true)) {
            throw ValidationException::withMessages(['reason' => 'Lý do này chưa áp dụng cho đơn ở thời điểm hiện tại.']);
        }
    }
}
