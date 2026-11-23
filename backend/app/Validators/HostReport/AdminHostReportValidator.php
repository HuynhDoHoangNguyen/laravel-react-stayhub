<?php

namespace App\Validators\HostReport;

use App\DTOs\HostReport\Requests\ReviewHostReportRequest;
use App\Models\HostReport;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class AdminHostReportValidator
{
    public function filters(array $query): array
    {
        $filters = Validator::make($query, [
            'status' => ['sometimes', 'required', 'string', Rule::in(['ALL', HostReport::SUBMITTED, ...HostReport::DECISIONS])],
            'booking_code' => ['nullable', 'string', 'max:50'],
            'page' => ['sometimes', 'required', 'integer', 'min:1', 'max:1000000'],
        ], [
            'status.*' => 'Trạng thái lọc không hợp lệ.',
            'booking_code.*' => 'Mã đơn phải là văn bản tối đa 50 ký tự.',
            'page.*' => 'Số trang không hợp lệ.',
        ])->validate();

        return [
            'status' => $filters['status'] ?? HostReport::SUBMITTED,
            'booking_code' => strtoupper(trim($filters['booking_code'] ?? '')),
            'page' => (int) ($filters['page'] ?? 1),
        ];
    }

    public function decision(array $input): ReviewHostReportRequest
    {
        if (is_string($input['note'] ?? null)) {
            $input['note'] = preg_replace('/^\s+|\s+$/u', '', $input['note']) ?? $input['note'];
        }
        $data = Validator::make($input, [
            'decision' => ['required', 'string', Rule::in(HostReport::DECISIONS)],
            'note' => ['required', 'string', 'max:500'],
        ], [
            'decision.*' => 'Vui lòng chọn kết luận hợp lệ.',
            'note.required' => 'Vui lòng nhập ghi chú xử lý.',
            'note.string' => 'Ghi chú phải là văn bản.',
            'note.max' => 'Ghi chú tối đa 500 ký tự.',
        ])->validate();

        return new ReviewHostReportRequest($data['decision'], $data['note']);
    }
}
