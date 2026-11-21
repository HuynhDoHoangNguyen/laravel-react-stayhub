import client from '../api/axios.js';
import { createHostRoomImageApi } from '../api/hostRoomImageApi.js';
import { localValidationError } from '../api/apiError.js';
import { mapHostRoomImages, mapUploadedHostRoomImage } from '../mappers/hostRoomImageMapper.js';

export function createHostRoomImageService(transport) {
    const api = createHostRoomImageApi(transport);
    return {
        async list(roomId, options) {
            const response = await api.list(roomId, options);
            if (response.status !== 200) throw new Error('Phản hồi danh sách ảnh phòng không hợp lệ.');
            return mapHostRoomImages(response.data);
        },
        async upload(roomId, file, options) {
            if (!(file instanceof Blob) || !['image/jpeg', 'image/png', 'image/webp'].includes(file.type)
                || file.size === 0 || file.size > 5 * 1024 * 1024) {
                throw localValidationError('Chỉ nhận ảnh JPG, PNG hoặc WebP tối đa 5 MiB.');
            }
            const response = await api.upload(roomId, file, options);
            try {
                if (response.status !== 201) throw new Error('Phản hồi upload ảnh phòng không hợp lệ.');
                return mapUploadedHostRoomImage(response.data);
            } catch (error) {
                error.outcomeUnknown = true;
                throw error;
            }
        },
    };
}

export const hostRoomImageService = createHostRoomImageService(client);
