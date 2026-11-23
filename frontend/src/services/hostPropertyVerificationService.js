import api from '../api/axios.js';
import { createHostPropertyVerificationApi } from '../api/hostPropertyVerificationApi.js';

const transport = createHostPropertyVerificationApi(api);
const timestamp = (value) => typeof value === 'string' && /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/.test(value)
    && Number.isFinite(Date.parse(value));

function mapVerification(body, allowEmpty) {
    const entry = body?.data;
    if (allowEmpty && entry === null) return null;
    const status = entry?.status;
    if (!Number.isSafeInteger(entry?.id) || entry.id <= 0 || !['SUBMITTED', 'APPROVED', 'REJECTED', 'INVALIDATED'].includes(status)
        || (!allowEmpty && status !== 'SUBMITTED') || typeof entry.address_snapshot !== 'string'
        || !timestamp(entry.submitted_at) || (status === 'SUBMITTED' ? entry.reviewed_at !== null : !timestamp(entry.reviewed_at))
        || (status === 'REJECTED' ? typeof entry.rejection_reason !== 'string' || !entry.rejection_reason.trim() : entry.rejection_reason !== null)) {
        throw Object.assign(new Error('Dữ liệu hồ sơ xác minh từ máy chủ không hợp lệ.'), {
            isApiError: true, status: 0, kind: 'contract', fields: {}, isCancelled: false,
        });
    }
    const { id, submitted_at, address_snapshot, reviewed_at, rejection_reason } = entry;
    return { id, status, submitted_at, address_snapshot, reviewed_at, rejection_reason };
}

export const hostPropertyVerificationService = {
    async current(propertyId, options) {
        return mapVerification(await transport.current(propertyId, options), true);
    },
    async submit(propertyId, document, options) {
        return mapVerification(await transport.submit(propertyId, document, options), false);
    },
};
