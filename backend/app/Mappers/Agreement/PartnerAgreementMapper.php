<?php

namespace App\Mappers\Agreement;

use App\Models\HostAgreementAcceptance;
use Illuminate\Database\Eloquent\Collection;

class PartnerAgreementMapper
{
    /**
     * Chuẩn hóa dữ liệu hợp đồng đối tác cho API response.
     */
    public function toAgreementResponse(array $agreement): array
    {
        return [
            'id' => (int) $agreement['id'],
            'version' => (string) $agreement['version'],
            'title' => (string) $agreement['title'],
            'terms_body' => (string) $agreement['terms_body'],
            'content_sha256' => (string) $agreement['content_sha256'],
            'commission_rate_bps' => (int) $agreement['commission_rate_bps'],
            'is_accepted' => (bool) $agreement['is_accepted'],
            'accepted_at' => $agreement['accepted_at'] ?? null,
        ];
    }

    /**
     * Chuyển đổi dữ liệu chấp thuận hợp đồng sang dạng response.
     */
    public function toAcceptanceResponse(HostAgreementAcceptance $acceptance): array
    {
        return [
            'acceptance_id' => $acceptance->id,
            'accepted_at' => $acceptance->accepted_at?->toIso8601String(),
        ];
    }

    /**
     * Chuyển đổi danh sách lịch sử chấp thuận hợp đồng của Host.
     *
     * @param Collection<int, HostAgreementAcceptance> $acceptances
     */
    public function toAcceptancesListResponse(Collection $acceptances): array
    {
        return $acceptances->map(fn (HostAgreementAcceptance $item) => [
            'acceptance_id' => $item->id,
            'partner_agreement_id' => $item->partner_agreement_id,
            'version' => $item->agreement?->version,
            'title' => $item->agreement?->title,
            'commission_rate_bps' => $item->agreement?->commission_rate_bps,
            'accepted_content_sha256' => $item->accepted_content_sha256,
            'method' => $item->method,
            'accepted_at' => $item->accepted_at?->toIso8601String(),
        ])->values()->all();
    }
}
