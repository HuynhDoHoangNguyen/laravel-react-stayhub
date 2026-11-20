import api from '../api/axios.js';
import { requireApiId } from '../api/apiId.js';
import { createPublicRoomApi } from '../api/publicRoomApi.js';
import { mapPublicRoomDetail } from '../mappers/publicRoomMapper.js';

export function createRoomService(roomApi) {
    return {
        async getRoomById(roomId, { signal } = {}) {
            const id = requireApiId(roomId, 'Mã phòng');
            const response = await roomApi.detail(id, { signal });
            return mapPublicRoomDetail(response.data, id);
        },
    };
}

export const roomService = createRoomService(createPublicRoomApi(api));
