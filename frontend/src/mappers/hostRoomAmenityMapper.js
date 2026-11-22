const invalidResponse = 'Phản hồi tiện nghi phòng không hợp lệ.';

export function mapHostRoomAmenities(body) {
    const data = body?.data;
    if (body?.success !== true || typeof body.message !== 'string'
        || !Array.isArray(data?.assigned) || !Array.isArray(data.available)) {
        throw new Error(invalidResponse);
    }

    const ids = new Set();
    const mapItem = (item, available) => {
        if (!Number.isSafeInteger(item?.id) || item.id < 1 || ids.has(item.id)
            || typeof item.name !== 'string' || typeof item.status !== 'boolean'
            || (available && !item.status)) {
            throw new Error(invalidResponse);
        }
        ids.add(item.id);
        return { id: item.id, name: item.name, status: item.status };
    };

    return {
        assigned: data.assigned.map((item) => mapItem(item, false)),
        available: data.available.map((item) => mapItem(item, true)),
    };
}
