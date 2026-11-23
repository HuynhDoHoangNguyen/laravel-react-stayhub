<?php

namespace App\Services\HostReport;

use App\DTOs\HostReport\Requests\ReviewHostReportRequest;
use App\DTOs\HostReport\Responses\AdminHostReportResponse;
use App\Models\HostReport;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class AdminHostReportService
{
    private const DETAIL_RELATIONS = ['booking:id,booking_code,status,check_in_date,check_out_date',
        'property:id,name', 'host:id,name', 'customer:id,name', 'reviewer:id,name'];

    public function list(array $filters): LengthAwarePaginator
    {
        return HostReport::query()
            ->with(['booking:id,booking_code', 'property:id,name', 'host:id,name'])
            ->when($filters['status'] !== 'ALL', fn ($query) => $query->where('status', $filters['status']))
            ->when($filters['booking_code'] !== '', fn ($query) => $query->whereRelation('booking', 'booking_code', '=', $filters['booking_code']))
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(10, ['id', 'booking_id', 'property_id', 'host_id', 'status', 'reason', 'created_at'], 'page', $filters['page'])
            ->appends(['status' => $filters['status'], 'booking_code' => $filters['booking_code']])
            ->through(fn (HostReport $report): array => AdminHostReportResponse::summary($report)->jsonSerialize());
    }

    public function detail(int $id): AdminHostReportResponse
    {
        return AdminHostReportResponse::detail(HostReport::query()->with(self::DETAIL_RELATIONS)->findOrFail($id));
    }

    public function decide(User $admin, int $id, ReviewHostReportRequest $input): AdminHostReportResponse
    {
        return DB::transaction(function () use ($admin, $id, $input): AdminHostReportResponse {
            $report = HostReport::query()->lockForUpdate()->findOrFail($id);
            if ($report->status !== HostReport::SUBMITTED) {
                throw new ConflictHttpException('Báo cáo này đã được xử lý.');
            }
            $report->forceFill([
                'status' => $input->decision,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now()->startOfSecond(),
                'review_note' => $input->note,
            ])->save();
            DB::afterCommit(static fn () => Log::info('Host report reviewed.', ['report_id' => $id]));

            return AdminHostReportResponse::detail($report->load(self::DETAIL_RELATIONS));
        }, 3);
    }
}
