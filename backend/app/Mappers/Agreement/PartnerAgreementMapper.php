<?php

namespace App\Mappers\Agreement;

use App\Models\HostAgreementAcceptance;

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
}
