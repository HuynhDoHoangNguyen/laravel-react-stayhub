import client from '../api/axios.js';
import { createHostRoomAmenityApi } from '../api/hostRoomAmenityApi.js';
import { mapHostRoomAmenities } from '../mappers/hostRoomAmenityMapper.js';

export function createHostRoomAmenityService(transport) {
    const api = createHostRoomAmenityApi(transport);

    async function mutate(action, roomId, amenityId, options) {
        const response = await api[action](roomId, amenityId, options);
        try {
            if (response.status !== 200) throw new Error('Phản hồi thao tác tiện nghi phòng không hợp lệ.');
            const result = mapHostRoomAmenities(response.data);
            const item = result.assigned.find((entry) => entry.id === Number(amenityId));
            if (action === 'assign' ? !item?.status : Boolean(item)) {
                throw new Error('Danh sách tiện nghi đã gán không khớp yêu cầu.');
            }
            return result;
        } catch (error) {
            error.outcomeUnknown = true;
            throw error;
        }
    }

    return {
        async list(roomId, options) {
            const response = await api.list(roomId, options);
            if (response.status !== 200) throw new Error('Phản hồi danh sách tiện nghi phòng không hợp lệ.');
            return mapHostRoomAmenities(response.data);
        },
        assign: (roomId, amenityId, options) => mutate('assign', roomId, amenityId, options),
        remove: (roomId, amenityId, options) => mutate('remove', roomId, amenityId, options),
    };
}

export const hostRoomAmenityService = createHostRoomAmenityService(client);
