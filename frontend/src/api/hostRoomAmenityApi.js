import { requireApiId } from './apiId.js';

function amenitiesUrl(roomId) {
    return '/api/host/rooms/' + requireApiId(roomId, 'Mã phòng') + '/amenities';
}

export function createHostRoomAmenityApi(client) {
    async function mutate(method, roomId, amenityId, { signal } = {}) {
        const url = amenitiesUrl(roomId) + '/' + requireApiId(amenityId, 'Mã tiện nghi');
        const options = { signal, timeout: 10000 };
        await client.get('/sanctum/csrf-cookie', options);
        try {
            return method === 'put' ? await client.put(url, undefined, options) : await client.delete(url, options);
        } catch (error) {
            const status = error.response?.status;
            error.outcomeUnknown = !status || status >= 500;
            throw error;
        }
    }

    return {
        async list(roomId, { signal } = {}) {
            return client.get(amenitiesUrl(roomId), { signal, timeout: 10000 });
        },
        assign: (roomId, amenityId, options) => mutate('put', roomId, amenityId, options),
        remove: (roomId, amenityId, options) => mutate('delete', roomId, amenityId, options),
    };
}
