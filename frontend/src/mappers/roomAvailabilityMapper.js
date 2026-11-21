export function mapRoomAvailability(body) {
    const data = body?.data;
    if (body?.success !== true || !data || typeof data.available !== 'boolean'
        || !Array.isArray(data.reasons) || !data.reasons.every(reason => typeof reason === 'string')
        || !Array.isArray(data.blocked_ranges) || !data.blocked_ranges.every(range => range && typeof range === 'object'
            && !Array.isArray(range) && Object.values(range).every(value => typeof value === 'string'))) {
        throw new Error('Dữ liệu kiểm tra phòng trống không hợp lệ.');
    }
    return { available: data.available, reasons: [...data.reasons], blockedRanges: data.blocked_ranges.map(range => ({ ...range })) };
}
