import client from '../api/axios.js';
import { createHostRoomMaintenanceApi } from '../api/hostRoomMaintenanceApi.js';
import { mapHostRoomMaintenancePage } from '../mappers/hostRoomMaintenanceMapper.js';

export function createHostRoomMaintenanceService(transport) {
    const api = createHostRoomMaintenanceApi(transport);

    return {
        async list(roomId, page = 1, options) {
            const response = await api.list(roomId, page, options);
            if (response.status !== 200) throw new Error('Không thể đọc danh sách lịch bảo trì.');
            const result = mapHostRoomMaintenancePage(response.data);
            if (result.meta.current_page !== Number(page)) throw new Error('Trang lịch bảo trì không khớp yêu cầu.');
            return result;
        },
    };
}

export const hostRoomMaintenanceService = createHostRoomMaintenanceService(client);
