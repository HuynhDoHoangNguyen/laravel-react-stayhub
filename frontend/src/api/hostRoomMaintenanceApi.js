import { requireApiId } from './apiId.js';

export function createHostRoomMaintenanceApi(client) {
    return {
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
