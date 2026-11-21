import api from '../api/axios.js';
import { requireApiId } from '../api/apiId.js';
import { createPublicRoomApi } from '../api/publicRoomApi.js';
import { mapPublicRoomDetail } from '../mappers/publicRoomMapper.js';
import { mapRoomAvailability } from '../mappers/roomAvailabilityMapper.js';

export function createRoomService(roomApi) {
    return {
        async checkAvailability(roomId, context, { signal } = {}) {
            if (context.status !== 'valid') throw new Error('Vui lòng kiểm tra ngày lưu trú và số khách.');
            const response = await roomApi.availability(requireApiId(roomId, 'Mã phòng'), {
                check_in_date: context.checkIn, check_out_date: context.checkOut,
                guest_count: context.adults + context.children,
            }, { signal });
            return mapRoomAvailability(response.data);
        },
        async getRoomById(roomId, { signal } = {}) {
            const id = requireApiId(roomId, 'Mã phòng');
            const response = await roomApi.detail(id, { signal });
            return mapPublicRoomDetail(response.data, id);
        },
    };
}

export const roomService = createRoomService(createPublicRoomApi(api));
