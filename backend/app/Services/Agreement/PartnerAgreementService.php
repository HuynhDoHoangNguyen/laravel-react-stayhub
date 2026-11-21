<?php

namespace App\Services\Agreement;

use App\Models\HostAgreementAcceptance;
use App\Models\PartnerAgreement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PartnerAgreementService
{
    /**
     * Lấy phiên bản hợp đồng đối tác đang có hiệu lực.
     */
    public function getCurrentAgreement(?int $hostId = null): ?array
    {
        $agreement = PartnerAgreement::query()
            ->where('status', 'PUBLISHED')
            ->where('effective_at', '<=', now())
            ->where(function ($query) {
                $query->whereNull('retired_at')
                    ->orWhere('retired_at', '>', now());
            })
            ->orderByDesc('effective_at')
            ->first();

        if ($agreement === null) {
            return null;
        }

        $acceptance = null;
        if ($hostId !== null) {
            $acceptance = HostAgreementAcceptance::query()
                ->where('host_id', $hostId)
                ->where('partner_agreement_id', $agreement->id)
                ->first();
        }

        return [
            'id' => $agreement->id,
            'version' => $agreement->version,
            'title' => $agreement->title,
            'terms_body' => $agreement->terms_body,
            'content_sha256' => $agreement->content_sha256,
            'commission_rate_bps' => $agreement->commission_rate_bps,
            'is_accepted' => $acceptance !== null,
            'accepted_at' => $acceptance?->accepted_at?->toIso8601String(),
        ];
    }

    /**
     * Kiểm tra xem Host đã chấp thuận phiên bản hợp đồng hiện hành hay chưa.
     */
    public function hasAcceptedCurrentAgreement(int $hostId): bool
    {
        $current = $this->getCurrentAgreement($hostId);
        if ($current === null) {
            // Nếu hệ thống chưa cấu hình hợp đồng nào thì không chặn
            return true;
        }

        return (bool) $current['is_accepted'];
    }

    /**
     * Lấy bản ghi chấp thuận hợp đồng đang có hiệu lực của Host (nếu có).
     */
    public function getHostActiveAcceptance(int $hostId): ?HostAgreementAcceptance
    {
        $agreement = PartnerAgreement::query()
            ->where('status', 'PUBLISHED')
            ->where('effective_at', '<=', now())
            ->where(function ($query) {
                $query->whereNull('retired_at')
                    ->orWhere('retired_at', '>', now());
            })
            ->orderByDesc('effective_at')
            ->first();

        if ($agreement === null) {
            return null;
        }

        return HostAgreementAcceptance::query()
            ->with('agreement')
            ->where('host_id', $hostId)
            ->where('partner_agreement_id', $agreement->id)
            ->first();
    }

    /**
     * Ghi nhận việc Host chấp thuận hợp đồng (Clickwrap).
     */
    public function acceptAgreement(int $hostId, int $agreementId, string $contentSha256): HostAgreementAcceptance
    {
        $agreement = PartnerAgreement::find($agreementId);
        if ($agreement === null || $agreement->status !== 'PUBLISHED') {
            throw new NotFoundHttpException('Phiên bản hợp đồng không tồn tại hoặc chưa được phát hành.');
        }

        if (strtolower($agreement->content_sha256) !== strtolower($contentSha256)) {
            throw new ConflictHttpException('Nội dung hợp đồng đã được cập nhật phiên bản mới. Vui lòng tải lại và xem nội dung mới nhất.');
        }

        return DB::transaction(function () use ($hostId, $agreement): HostAgreementAcceptance {
            $acceptance = HostAgreementAcceptance::firstOrCreate(
                [
                    'host_id' => $hostId,
                    'partner_agreement_id' => $agreement->id,
                ],
                [
                    'accepted_content_sha256' => $agreement->content_sha256,
                    'accepted_at' => now(),
                    'method' => 'CHECKBOX_CLICKWRAP',
                ]
            );

            DB::afterCommit(fn () => Log::info('Host đã chấp thuận hợp đồng đối tác StayHub.', [
                'host_id' => $hostId,
                'agreement_id' => $agreement->id,
                'version' => $agreement->version,
            ]));

            return $acceptance;
        });
    }
}
