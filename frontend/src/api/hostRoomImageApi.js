import { requireApiId } from './apiId.js';

export function createHostRoomImageApi(client) {
    return {
        list(roomId, { signal } = {}) {
            const id = requireApiId(roomId, 'Mã phòng');
            return client.get('/api/host/rooms/' + id + '/images', { signal, timeout: 10000 });
        },
        async upload(roomId, file, { signal } = {}) {
            const id = requireApiId(roomId, 'Mã phòng');
            const options = { signal, timeout: 30000 };
            const body = new FormData();
            body.append('image', file);
            await client.get('/sanctum/csrf-cookie', { signal, timeout: 10000 });
            try {
                // Axios/browser owns the multipart boundary.
                return await client.post('/api/host/rooms/' + id + '/images', body, options);
            } catch (error) {
                const status = error.response?.status;
                error.outcomeUnknown = !status || status >= 500;
                throw error;
            }
        },
    };
}
