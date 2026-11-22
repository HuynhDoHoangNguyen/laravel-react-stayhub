<?php

namespace App\Repositories\Agreement;

use App\Models\HostAgreementAcceptance;
use App\Models\PartnerAgreement;
use Illuminate\Database\Eloquent\Collection;

class PartnerAgreementRepository
{
    /**
     * Tìm phiên bản hợp đồng đối tác PUBLISHED đang có hiệu lực.
     */
    public function findActivePublishedAgreement(): ?PartnerAgreement
    {
        return PartnerAgreement::query()
            ->where('status', 'PUBLISHED')
            ->where('effective_at', '<=', now())
            ->where(function ($query) {
                $query->whereNull('retired_at')
                    ->orWhere('retired_at', '>', now());
            })
            ->orderByDesc('effective_at')
            ->first();
    }

    /**
     * Tìm hợp đồng đối tác theo ID.
     */
    public function findById(int $agreementId): ?PartnerAgreement
    {
        return PartnerAgreement::query()->find($agreementId);
    }

    /**
     * Tìm bản ghi chấp thuận hợp đồng của Host cho một phiên bản cụ thể.
     */
    public function findHostAcceptance(int $hostId, int $agreementId): ?HostAgreementAcceptance
    {
        return HostAgreementAcceptance::query()
            ->where('host_id', $hostId)
            ->where('partner_agreement_id', $agreementId)
            ->first();
    }

    /**
     * Tìm bản ghi chấp thuận kèm relation agreement của Host cho phiên bản cụ thể.
     */
    public function findHostAcceptanceWithAgreement(int $hostId, int $agreementId): ?HostAgreementAcceptance
    {
        return HostAgreementAcceptance::query()
            ->with('agreement')
            ->where('host_id', $hostId)
            ->where('partner_agreement_id', $agreementId)
            ->first();
    }

    /**
     * Tạo hoặc lấy bản ghi chấp thuận hợp đồng (Idempotent theo unique host_id + partner_agreement_id).
     */
    public function firstOrCreateAcceptance(
        int $hostId,
        int $agreementId,
        string $contentSha256,
        string $method = 'CHECKBOX_CLICKWRAP'
    ): HostAgreementAcceptance {
        return HostAgreementAcceptance::query()->firstOrCreate(
            [
                'host_id' => $hostId,
                'partner_agreement_id' => $agreementId,
            ],
            [
                'accepted_content_sha256' => $contentSha256,
                'accepted_at' => now(),
                'method' => $method,
            ]
        );
    }

    /**
     * Lấy danh sách lịch sử chấp thuận hợp đồng của Host.
     *
     * @return Collection<int, HostAgreementAcceptance>
     */
    public function listHostAcceptances(int $hostId): Collection
    {
        return HostAgreementAcceptance::query()
            ->with('agreement')
            ->where('host_id', $hostId)
            ->orderByDesc('accepted_at')
            ->orderByDesc('id')
            ->get();
    }
}
