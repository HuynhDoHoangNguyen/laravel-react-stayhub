import api from '../api/axios.js';
import { createHostRoomApi } from '../api/hostRoomApi.js';
import { mapHostRoomList, mapHostRoomDetail, mapCreatedHostRoom, mapUpdatedHostRoom, mapChangedHostRoomStatus } from '../mappers/hostRoomMapper.js';

export function createHostRoomService(client) {
    const roomApi = createHostRoomApi(client);

    return {
        async changeStatus(roomId, values, options) {
            const payload = { status: values.status };
            const response = await roomApi.changeStatus(roomId, payload, options);
            try {
                if (response.status !== 200) throw new Error('Phản hồi đổi trạng thái phòng không hợp lệ.');
                return mapChangedHostRoomStatus(response.data, roomId, payload.status);
            } catch (error) {
                error.outcomeUnknown = true;
                throw error;
            }
        },

        async update(roomId, values, options) {
            const allowed = ['room_type_id', 'room_number', 'name', 'description', 'capacity', 'price_per_night'];
            const payload = Object.fromEntries(allowed.filter((field) => Object.hasOwn(values, field))
                .map((field) => [field, typeof values[field] === 'string' ? values[field].trim() : values[field]]));
            if (payload.description === '' || payload.description === undefined) payload.description = null;
            const response = await roomApi.update(roomId, payload, options);
            try {
                if (response.status !== 200) throw new Error('Phản hồi cập nhật phòng không hợp lệ.');
                return mapUpdatedHostRoom(response.data, roomId, payload.room_type_id);
            } catch (error) {
                error.outcomeUnknown = true;
                throw error;
            }
        },

        async create(propertyId, values, options) {
            const allowed = ['room_type_id', 'room_number', 'name', 'description', 'capacity', 'price_per_night'];
            const payload = Object.fromEntries(allowed.filter((field) => Object.hasOwn(values, field))
                .map((field) => [field, typeof values[field] === 'string' ? values[field].trim() : values[field]]));
            if (payload.description === '' || payload.description === undefined) {
                payload.description = null;
            }
            const response = await roomApi.create(propertyId, payload, options);
            try {
                if (response.status !== 201) {
                    throw new Error('Phản hồi thêm phòng không hợp lệ.');
                }
                return mapCreatedHostRoom(response.data, payload.room_type_id);
            } catch (error) {
                error.outcomeUnknown = true;
                throw error;
            }
        },

        async detail(roomId, options) {
            return mapHostRoomDetail(await roomApi.detail(roomId, options), roomId);
        },

        async list(propertyId, options) {
            return mapHostRoomList(await roomApi.list(propertyId, options));
        },
    };
}

export const hostRoomService = createHostRoomService(api);
