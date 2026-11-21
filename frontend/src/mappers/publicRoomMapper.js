const object = value => value !== null && typeof value === 'object' && !Array.isArray(value);
const id = value => Number.isSafeInteger(value) && value > 0;
const text = value => typeof value === 'string';

export function mapPublicRoomDetail(body, expectedId) {
    const room = body?.data;
    if (!object(body) || !object(room) || !id(room.id) || room.id !== expectedId
        || !text(room.room_number) || !text(room.name)
        || !(room.description === null || text(room.description))
        || !id(room.capacity) || room.capacity > 2147483647
        || !text(room.price_per_night) || !/^(0|[1-9]\d{0,9})\.\d{2}$/.test(room.price_per_night)
        || !object(room.room_type) || !id(room.room_type.id) || !text(room.room_type.name)
        || !Array.isArray(room.images) || !room.images.every(text)
        || !Array.isArray(room.amenities) || !room.amenities.every(item => object(item) && id(item.id) && text(item.name))
        || !object(room.property) || !id(room.property.id) || !text(room.property.name)
        || !['HOTEL', 'HOMESTAY'].includes(room.property.type) || !text(room.property.address)
        || room.currency !== 'VND' || room.price_unit !== 'night') {
        throw Object.assign(new Error('Dữ liệu chi tiết phòng từ máy chủ không đúng định dạng. Vui lòng thử lại.'), {
            code: 'INVALID_PUBLIC_ROOM_RESPONSE',
        });
    }

    return { data: {
        id: room.id, room_number: room.room_number, name: room.name, description: room.description,
        capacity: room.capacity, price_per_night: room.price_per_night,
        room_type: { id: room.room_type.id, name: room.room_type.name },
        images: [...room.images], amenities: room.amenities.map(item => ({ id: item.id, name: item.name })),
        property: { id: room.property.id, name: room.property.name, type: room.property.type, address: room.property.address },
        currency: room.currency, price_unit: room.price_unit,
    } };
}
