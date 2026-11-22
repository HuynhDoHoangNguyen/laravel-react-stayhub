const statuses = ['SCHEDULED', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED'];
const positiveInteger = (value) => Number.isSafeInteger(value) && value > 0;
const invalidResponse = 'Phản hồi lịch bảo trì không hợp lệ.';

export function mapHostRoomMaintenancePage(body) {
    const meta = body?.meta;
    if (!Array.isArray(body?.data) || !meta || !body.links || typeof body.links !== 'object' || Array.isArray(body.links)
        || !positiveInteger(meta.current_page) || !positiveInteger(meta.last_page)
        || meta.per_page !== 15 || !Number.isSafeInteger(meta.total) || meta.total < 0
        || body.data.length > meta.per_page || body.data.length > meta.total) {
        throw new Error(invalidResponse);
    }

    const ids = new Set();
    const data = body.data.map((item) => {
        if (!positiveInteger(item?.id) || ids.has(item.id)
            || typeof item.start_date !== 'string' || typeof item.end_date !== 'string'
            || (item.reason !== null && typeof item.reason !== 'string') || !statuses.includes(item.status)) {
            throw new Error(invalidResponse);
        }
        ids.add(item.id);
        return { id: item.id, start_date: item.start_date, end_date: item.end_date, reason: item.reason, status: item.status };
    });

    return { data, meta: { current_page: meta.current_page, last_page: meta.last_page, total: meta.total } };
}
