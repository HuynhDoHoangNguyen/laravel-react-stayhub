import { requireApiId } from './apiId.js';

export function createHostRoomMaintenanceApi(client) {
    return {
        async create(roomId, payload, { signal } = {}) {
            const room = requireApiId(roomId, 'Mã phòng');
            return client.post('/api/host/rooms/' + room + '/maintenances', payload, { signal, timeout: 10000 });
        },
        async update(maintenanceId, payload, { signal } = {}) {
            const id = requireApiId(maintenanceId, 'Mã lịch bảo trì');
            return client.put('/api/host/maintenances/' + id, payload, { signal, timeout: 10000 });
        },
        async cancel(maintenanceId, { signal } = {}) {
            const id = requireApiId(maintenanceId, 'Mã lịch bảo trì');
            return client.delete('/api/host/maintenances/' + id, { signal, timeout: 10000 });
        },
        async changeStatus(maintenanceId, status, { signal } = {}) {
            const id = requireApiId(maintenanceId, 'Mã lịch bảo trì');
            return client.patch('/api/host/maintenances/' + id + '/status', { status }, { signal, timeout: 10000 });
        },
        async list(roomId, page, { signal } = {}) {
            const room = requireApiId(roomId, 'Mã phòng');
            const pageNumber = requireApiId(page, 'Số trang');
            return client.get('/api/host/rooms/' + room + '/maintenances', {
                params: { page: pageNumber },
                signal,
                timeout: 10000,
            });
        },
    };
}
