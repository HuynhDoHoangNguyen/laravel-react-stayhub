import { requireApiId } from './apiId.js';

export function createPublicRoomApi(client) {
    return {
        detail: (roomId, { signal } = {}) => client.get('/api/public/rooms/' + requireApiId(roomId, 'Mã phòng'), { signal }),
        list: (params, { signal } = {}) => client.get('/api/public/rooms', {
            params, signal, paramsSerializer: { indexes: false },
        }),
        filterOptions: ({ signal } = {}) => client.get('/api/public/rooms/filter-options', { signal }),
    };
}
