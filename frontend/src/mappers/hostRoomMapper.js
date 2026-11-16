const statuses = ['ACTIVE', 'MAINTENANCE', 'INACTIVE'];
const validId = (value) => Number.isSafeInteger(value) && value > 0;

function mapRoom(room, message = 'Phản hồi danh sách phòng không hợp lệ.') {
    const type = room?.room_type;
    if (!validId(room?.id) || typeof room.room_number !== 'string'
        || typeof room.name !== 'string' || (room.description !== null && typeof room.description !== 'string')
        || !Number.isSafeInteger(room.capacity)
        || typeof room.price_per_night !== 'string' || !/^-?\d{1,10}\.\d{2}$/.test(room.price_per_night)
        || !statuses.includes(room.status)
        || (type !== null && (!validId(type?.id) || typeof type.name !== 'string'))) {
        throw new Error(message);
    }

    return {
        id: room.id,
        room_number: room.room_number,
        name: room.name,
        description: room.description,
        capacity: room.capacity,
        price_per_night: room.price_per_night,
        status: room.status,
        room_type: type === null ? null : { id: type.id, name: type.name },
    };
}

function validImageUrl(value) {
    if (typeof value !== 'string') return false;
    try {
        const url = new URL(value);
        return ['http:', 'https:'].includes(url.protocol) && !url.username && !url.password;
    } catch {
        return false;
    }
}

export function mapHostRoomDetail(body, expectedId) {
    const data = body?.data;
    const property = data?.property;
    const message = 'Phản hồi chi tiết phòng không hợp lệ.';
    if (body?.success !== true || typeof body.message !== 'string' || data?.id !== Number(expectedId)
        || !validId(property?.id) || typeof property.name !== 'string'
        || !['ACTIVE', 'INACTIVE', 'PENDING', 'BLOCKED'].includes(property.status)
        || !Array.isArray(data?.images) || !data.images.every(validImageUrl)
        || !Array.isArray(data.amenities)) {
        throw new Error(message);
    }
    const room = mapRoom(data, message);
    const ids = new Set();
    const amenities = data.amenities.map((item) => {
        if (!validId(item?.id) || ids.has(item.id) || typeof item.name !== 'string' || typeof item.status !== 'boolean') {
            throw new Error(message);
        }
        ids.add(item.id);
        return { id: item.id, name: item.name, status: item.status };
    });

    return {
        ...room,
        property: { id: property.id, name: property.name, status: property.status },
        images: [...data.images],
        amenities,
    };
}

export function mapChangedHostRoomStatus(body, expectedId, expectedStatus) {
    const message = 'Phản hồi đổi trạng thái phòng không hợp lệ.';
    if (body?.success !== true || typeof body.message !== 'string') throw new Error(message);
    const room = mapRoom(body.data, message);
    if (room.id !== Number(expectedId) || room.status !== expectedStatus) throw new Error(message);

    return room;
}

export function mapUpdatedHostRoom(body, expectedId, expectedRoomTypeId) {
    const message = 'Phản hồi cập nhật phòng không hợp lệ.';
    if (body?.success !== true || typeof body.message !== 'string') throw new Error(message);
    const room = mapRoom(body.data, message);
    if (room.id !== Number(expectedId) || room.room_type?.id !== Number(expectedRoomTypeId)
        || room.capacity < 1 || BigInt(room.price_per_night.replace('.', '')) <= 0n) {
        throw new Error(message);
    }

    return room;
}

export function mapCreatedHostRoom(body, expectedRoomTypeId) {
    const message = 'Phản hồi thêm phòng không hợp lệ.';
    if (body?.success !== true || typeof body.message !== 'string') {
        throw new Error(message);
    }
    const room = mapRoom(body.data, message);
    if (room.status !== 'ACTIVE' || room.room_type?.id !== Number(expectedRoomTypeId)
        || room.capacity < 1 || BigInt(room.price_per_night.replace('.', '')) <= 0n) {
        throw new Error(message);
    }

    return room;
}

export function mapHostRoomList(body) {
    if (body?.success !== true || typeof body.message !== 'string' || !Array.isArray(body.data)) {
        throw new Error('Phản hồi danh sách phòng không hợp lệ.');
    }

    const ids = new Set();
    return body.data.map((room) => {
        const result = mapRoom(room);
        if (ids.has(result.id)) {
            throw new Error('Phản hồi danh sách phòng không hợp lệ.');
        }
        ids.add(result.id);
        return result;
    });
}
