const object = value => value !== null && typeof value === 'object' && !Array.isArray(value);
const id = value => Number.isSafeInteger(value) && value > 0;
const text = value => typeof value === 'string';
function requireValue(condition) {
    if (!condition) throw new Error('Dữ liệu phòng từ máy chủ không đúng định dạng. Vui lòng thử lại.');
}

export function mapRoomSearchPage(body) {
    requireValue(object(body) && Array.isArray(body.data) && object(body.meta) && object(body.links));
    const meta = body.meta;
    requireValue(['current_page', 'last_page', 'per_page'].every(key => id(meta[key]))
        && meta.per_page <= 24 && Number.isSafeInteger(meta.total) && meta.total >= 0
        && ['from', 'to'].every(key => meta[key] === null || id(meta[key]))
        && body.data.length <= meta.per_page);
    const data = body.data.map(room => {
        requireValue(object(room) && id(room.id) && text(room.name) && id(room.capacity)
            && text(room.price_per_night) && /^(0|[1-9]\d*)\.\d{2}$/.test(room.price_per_night)
            && object(room.property) && id(room.property.id) && text(room.property.name)
            && object(room.room_type) && id(room.room_type.id) && text(room.room_type.name)
            && Array.isArray(room.images) && room.images.every(text)
            && Array.isArray(room.amenities) && room.amenities.every(a => object(a) && id(a.id) && text(a.name))
            && room.currency === 'VND' && room.price_unit === 'night');
        requireValue(Number.isSafeInteger(room.property.review_count) && room.property.review_count >= 0
            && (room.property.review_count === 0 ? room.property.rating === null
                : typeof room.property.rating === 'number' && room.property.rating >= 1 && room.property.rating <= 5));
        return {
            id: room.id, name: room.name, capacity: room.capacity, price_per_night: room.price_per_night,
            property: { id: room.property.id, name: room.property.name, address: room.property.address ?? '',
                rating: room.property.rating, review_count: room.property.review_count },
            room_type: { id: room.room_type.id, name: room.room_type.name },
            images: [...room.images], amenities: room.amenities.map(a => ({ id: a.id, name: a.name })),
        };
    });
    return { data, meta: { current_page: meta.current_page, last_page: meta.last_page,
        per_page: meta.per_page, total: meta.total, from: meta.from, to: meta.to }, links: { ...body.links } };
}

export function mapRoomFilterOptions(body) {
    requireValue(object(body) && object(body.data) && Array.isArray(body.data.room_types) && Array.isArray(body.data.amenities));
    const room_types = body.data.room_types.map(type => {
        requireValue(object(type) && id(type.id) && text(type.name) && object(type.property)
            && id(type.property.id) && text(type.property.name));
        return { id: type.id, name: type.name, property: { id: type.property.id, name: type.property.name } };
    });
    const amenities = body.data.amenities.map(amenity => {
        requireValue(object(amenity) && id(amenity.id) && text(amenity.name));
        return { id: amenity.id, name: amenity.name };
    });
    return { room_types, amenities };
}
