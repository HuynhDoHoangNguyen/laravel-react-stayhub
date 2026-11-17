import { requireApiId } from './apiId.js';

export function createHostRoomApi(client) {
    return {
        async changeStatus(roomId, payload, { signal } = {}) {
            const id = requireApiId(roomId, 'Mã phòng');
            const options = { signal, timeout: 10000 };
            await client.get('/sanctum/csrf-cookie', options);
            try {
                return await client.patch('/api/host/rooms/' + id + '/status', payload, options);
            } catch (error) {
                const status = error.response?.status;
                error.outcomeUnknown = !status || status >= 500;
                throw error;
            }
        },

        async update(roomId, payload, { signal } = {}) {
            const id = requireApiId(roomId, 'Mã phòng');
            const options = { signal, timeout: 10000 };
            await client.get('/sanctum/csrf-cookie', options);
            try {
                return await client.put('/api/host/rooms/' + id, payload, options);
            } catch (error) {
                const status = error.response?.status;
                error.outcomeUnknown = !status || status >= 500;
                throw error;
            }
        },

        async create(propertyId, payload, { signal } = {}) {
            const id = requireApiId(propertyId, 'Mã cơ sở lưu trú');
            const options = { signal, timeout: 10000 };
            await client.get('/sanctum/csrf-cookie', options);
            try {
                return await client.post('/api/host/properties/' + id + '/rooms', payload, options);
            } catch (error) {
                const status = error.response?.status;
                error.outcomeUnknown = !status || status >= 500;
                throw error;
            }
        },

        async detail(roomId, { signal } = {}) {
            const id = requireApiId(roomId, 'Mã phòng');
            return (await client.get('/api/host/rooms/' + id, { signal, timeout: 10000 })).data;
        },

        async list(propertyId, { signal } = {}) {
            const id = requireApiId(propertyId, 'Mã cơ sở lưu trú');
            return (await client.get('/api/host/properties/' + id + '/rooms', { signal, timeout: 10000 })).data;
        },
    };
}
