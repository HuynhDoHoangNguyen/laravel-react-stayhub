import { requireApiId } from '../api/apiId.js';

export function mapRelatedPropertyRooms(result, propertyId, currentRoomId) {
    const id = requireApiId(propertyId, 'Mã cơ sở');
    const current = requireApiId(currentRoomId, 'Mã phòng');
    const property = result?.data;
    if (!['api', 'mock'].includes(result?.source) || !property || String(property.id) !== String(id)
        || typeof property.name !== 'string' || !Array.isArray(property.rooms)) {
        throw new Error('Không thể xác nhận danh sách phòng cùng cơ sở.');
    }
    const seen = new Set([current]);
    const rooms = [];
    for (const room of property.rooms) {
        const roomId = requireApiId(room?.id, 'Mã phòng');
        if (seen.has(roomId)) continue;
        seen.add(roomId);
        if (typeof room.name !== 'string' || !Array.isArray(room.photos) || !Array.isArray(room.amenities)) {
            throw new Error('Dữ liệu phòng gợi ý không hợp lệ.');
        }
        rooms.push({ ...room, photos: [...room.photos], amenities: [...room.amenities] });
        if (rooms.length === 4) break;
    }
    return { propertyName: property.name, rooms, source: result.source };
}
