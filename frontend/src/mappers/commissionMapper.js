/**
 * Pure boundary mapper for Partner Agreement & Platform Commission payloads.
 * Preserves exact decimal strings, timestamps, nullable fields and pagination metadata.
 */

function requireEnvelopeObject(payload, code = 'INVALID_COMMISSION_RESPONSE') {
    if (!payload || typeof payload !== 'object' || Array.isArray(payload)) {
        throw Object.assign(new Error('Phản hồi từ máy chủ không hợp lệ.'), {
            isApiError: true,
            status: 0,
            kind: 'contract',
            code,
            fields: {},
        });
    }
    return payload;
}

function mapPaginationMeta(meta) {
    if (!meta || typeof meta !== 'object') {
        return { current_page: 1, last_page: 1, per_page: 15, total: 0 };
    }
    return {
        current_page: Number(meta.current_page) || 1,
        last_page: Number(meta.last_page) || 1,
        per_page: Number(meta.per_page) || 15,
        total: Number(meta.total) || 0,
    };
}

export function mapAgreementCurrentResponse(payload) {
    const env = requireEnvelopeObject(payload, 'INVALID_AGREEMENT_RESPONSE');
    const d = env.data;
    if (!d || typeof d !== 'object') {
        return { success: Boolean(env.success), data: null };
    }

    return {
        success: Boolean(env.success ?? true),
        data: {
            id: Number(d.id),
            version: String(d.version ?? ''),
            title: String(d.title ?? ''),
            terms_body: String(d.terms_body ?? ''),
            content_sha256: String(d.content_sha256 ?? ''),
            commission_rate_bps: Number(d.commission_rate_bps ?? 1000),
            is_accepted: Boolean(d.is_accepted),
            accepted_at: d.accepted_at ?? null,
        },
    };
}

export function mapAgreementAcceptResponse(payload) {
    const env = requireEnvelopeObject(payload, 'INVALID_AGREEMENT_ACCEPT_RESPONSE');
    const d = env.data || {};
    return {
        success: Boolean(env.success ?? true),
        message: env.message ?? '',
        data: {
            acceptance_id: Number(d.acceptance_id),
            accepted_at: d.accepted_at ?? null,
        },
    };
}

export function mapAgreementAcceptancesResponse(payload) {
    const env = requireEnvelopeObject(payload, 'INVALID_AGREEMENT_HISTORY_RESPONSE');
    const list = Array.isArray(env.data) ? env.data : [];
    return {
        success: Boolean(env.success ?? true),
        data: list.map((item) => ({
            acceptance_id: Number(item.acceptance_id),
            partner_agreement_id: Number(item.partner_agreement_id),
            version: item.version ?? null,
            title: item.title ?? null,
            commission_rate_bps: item.commission_rate_bps !== undefined && item.commission_rate_bps !== null
                ? Number(item.commission_rate_bps)
                : null,
            accepted_content_sha256: String(item.accepted_content_sha256 ?? ''),
            method: String(item.method ?? ''),
            accepted_at: item.accepted_at ?? null,
        })),
    };
}

function mapCommissionItem(item) {
    if (!item || typeof item !== 'object') return item;
    return {
        id: Number(item.id),
        reference_code: String(item.reference_code ?? ''),
        host_id: item.host_id !== undefined ? Number(item.host_id) : undefined,
        host_name: item.host_name ?? null,
        host_phone: item.host_phone ?? null,
        invoice_id: Number(item.invoice_id),
        invoice_code: item.invoice_code ?? null,
        booking_id: Number(item.booking_id),
        booking_code: item.booking_code ?? null,
        property_name: item.property_name ?? null,
        room_name: item.room_name ?? null,
        customer_name: item.customer_name ?? null,
        commission_base: String(item.commission_base ?? '0.00'),
        commission_rate_bps: Number(item.commission_rate_bps ?? 1000),
        commission_amount: String(item.commission_amount ?? '0.00'),
        currency: String(item.currency ?? 'VND'),
        status: String(item.status ?? ''),
        display_status: String(item.display_status ?? item.status ?? ''),
        due_at: item.due_at ?? null,
        settled_at: item.settled_at ?? null,
        created_at: item.created_at ?? null,
    };
}

export function mapCommissionListResponse(payload) {
    const env = requireEnvelopeObject(payload);
    const items = Array.isArray(env.data) ? env.data.map(mapCommissionItem) : [];
    return {
        success: Boolean(env.success ?? true),
        data: items,
        meta: mapPaginationMeta(env.meta),
    };
}

export function mapCommissionDetailResponse(payload) {
    const env = requireEnvelopeObject(payload);
    const d = env.data || {};
    return {
        success: Boolean(env.success ?? true),
        data: {
            commission: d.commission ? mapCommissionItem(d.commission) : null,
            payment_instructions: d.payment_instructions
                ? {
                    bank_name: String(d.payment_instructions.bank_name ?? ''),
                    account_number: String(d.payment_instructions.account_number ?? ''),
                    account_holder: String(d.payment_instructions.account_holder ?? ''),
                    amount: String(d.payment_instructions.amount ?? '0.00'),
                    reference_code: String(d.payment_instructions.reference_code ?? ''),
                }
                : null,
            remittances: Array.isArray(d.remittances)
                ? d.remittances.map((r) => ({
                    id: Number(r.id),
                    amount: String(r.amount ?? '0.00'),
                    bank_transaction_reference: r.bank_transaction_reference ?? null,
                    idempotency_key: String(r.idempotency_key ?? ''),
                    status: String(r.status ?? ''),
                    submitted_at: r.submitted_at ?? null,
                    reviewed_at: r.reviewed_at ?? null,
                    review_note: r.review_note ?? null,
                }))
                : [],
        },
    };
}

export function mapRemittanceSubmitResponse(payload) {
    const env = requireEnvelopeObject(payload);
    const d = env.data || {};
    return {
        success: Boolean(env.success ?? true),
        message: env.message ?? '',
        data: {
            id: Number(d.id),
            booking_commission_id: Number(d.booking_commission_id),
            amount: String(d.amount ?? '0.00'),
            bank_transaction_reference: d.bank_transaction_reference ?? null,
            status: String(d.status ?? ''),
            submitted_at: d.submitted_at ?? null,
        },
    };
}

export function mapRemittanceQueueResponse(payload) {
    const env = requireEnvelopeObject(payload);
    const items = Array.isArray(env.data)
        ? env.data.map((r) => ({
            id: Number(r.id),
            booking_commission_id: Number(r.booking_commission_id),
            host_id: Number(r.host_id),
            amount: String(r.amount ?? '0.00'),
            bank_transaction_reference: r.bank_transaction_reference ?? null,
            idempotency_key: String(r.idempotency_key ?? ''),
            status: String(r.status ?? ''),
            verified_bank_reference: r.verified_bank_reference ?? null,
            reviewed_by_admin_id: r.reviewed_by_admin_id ?? null,
            review_note: r.review_note ?? null,
            submitted_at: r.submitted_at ?? null,
            reviewed_at: r.reviewed_at ?? null,
            created_at: r.created_at ?? null,
            host: r.host ?? null,
            reviewed_by_admin: r.reviewed_by_admin ?? null,
            commission: r.commission ?? null,
        }))
        : [];

    return {
        success: Boolean(env.success ?? true),
        data: items,
        meta: mapPaginationMeta(env.meta),
    };
}

export function mapRemittanceActionResponse(payload) {
    const env = requireEnvelopeObject(payload);
    return {
        success: Boolean(env.success ?? true),
        message: env.message ?? '',
        data: env.data ?? null,
    };
}

export function mapHostFinancialSummaryResponse(payload) {
    const env = requireEnvelopeObject(payload);
    const d = env.data || {};
    return {
        success: Boolean(env.success ?? true),
        data: {
            gross_revenue: String(d.gross_revenue ?? '0.00'),
            paid_invoices_count: Number(d.paid_invoices_count ?? 0),
            commission_assessed: String(d.commission_assessed ?? '0.00'),
            commission_settled: String(d.commission_settled ?? '0.00'),
            settled_count: Number(d.settled_count ?? 0),
            commission_due: String(d.commission_due ?? '0.00'),
            due_count: Number(d.due_count ?? 0),
            commission_under_review: String(d.commission_under_review ?? '0.00'),
            under_review_count: Number(d.under_review_count ?? 0),
            commission_overdue: String(d.commission_overdue ?? '0.00'),
            overdue_count: Number(d.overdue_count ?? 0),
            net_revenue_estimate: String(d.net_revenue_estimate ?? '0.00'),
        },
    };
}

export function mapAdminCommissionSummaryResponse(payload) {
    const env = requireEnvelopeObject(payload);
    const d = env.data || {};
    return {
        success: Boolean(env.success ?? true),
        data: {
            total_assessed: String(d.total_assessed ?? '0.00'),
            total_settled: String(d.total_settled ?? '0.00'),
            settled_count: Number(d.settled_count ?? 0),
            total_outstanding: String(d.total_outstanding ?? '0.00'),
            total_due: String(d.total_due ?? '0.00'),
            due_count: Number(d.due_count ?? 0),
            total_under_review: String(d.total_under_review ?? '0.00'),
            under_review_count: Number(d.under_review_count ?? 0),
            total_overdue: String(d.total_overdue ?? '0.00'),
            overdue_count: Number(d.overdue_count ?? 0),
            pending_remittances_count: Number(d.pending_remittances_count ?? 0),
        },
    };
}
