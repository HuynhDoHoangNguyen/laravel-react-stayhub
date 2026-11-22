import client from '../api/axios.js';
import { createHostRoomMaintenanceApi } from '../api/hostRoomMaintenanceApi.js';
import { mapHostRoomMaintenance, mapHostRoomMaintenancePage } from '../mappers/hostRoomMaintenanceMapper.js';

function schedulePayload({ start_date, end_date, reason }) {
    return { start_date, end_date, reason: reason?.trim() || null };
}

function confirmedSchedule(response, payload, status) {
    if (response.status !== status) throw new Error('Không nhận được xác nhận lưu lịch bảo trì.');
    const maintenance = mapHostRoomMaintenance(response.data?.data);
    if (maintenance.status !== 'SCHEDULED' || maintenance.start_date !== payload.start_date || maintenance.end_date !== payload.end_date) {
        throw new Error('Lịch bảo trì trả về không khớp yêu cầu.');
    }
    return maintenance;
}

function confirmedTransition(response, id, destination) {
    if (response.status !== 200) throw new Error('Không nhận được xác nhận chuyển trạng thái lịch bảo trì.');
    const maintenance = mapHostRoomMaintenance(response.data?.data);
    if (maintenance.id !== Number(id) || maintenance.status !== destination) {
        throw new Error('Lịch bảo trì trả về không khớp trạng thái yêu cầu.');
    }
    return maintenance;
}

export function createHostRoomMaintenanceService(transport) {
    const api = createHostRoomMaintenanceApi(transport);

    return {
        async create(roomId, draft, options) {
            const payload = schedulePayload(draft);
            return confirmedSchedule(await api.create(roomId, payload, options), payload, 201);
        },
        async update(maintenanceId, draft, options) {
            const payload = schedulePayload(draft);
            const maintenance = confirmedSchedule(await api.update(maintenanceId, payload, options), payload, 200);
            if (maintenance.id !== Number(maintenanceId)) throw new Error('Lịch bảo trì trả về không khớp yêu cầu.');
            return maintenance;
        },
        async cancel(maintenanceId, options) {
            return confirmedTransition(await api.cancel(maintenanceId, options), maintenanceId, 'CANCELLED');
        },
        async complete(maintenanceId, options) {
            return confirmedTransition(await api.changeStatus(maintenanceId, 'COMPLETED', options), maintenanceId, 'COMPLETED');
        },
        async start(maintenanceId, options) {
            return confirmedTransition(await api.changeStatus(maintenanceId, 'IN_PROGRESS', options), maintenanceId, 'IN_PROGRESS');
        },
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
