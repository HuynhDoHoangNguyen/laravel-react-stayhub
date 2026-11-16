import api from '../api/axios.js';

function detailData(body, expectedId) {
    const item = body?.data;
    if (body?.success !== true || typeof body.message !== 'string' || !Number.isSafeInteger(item?.id) || item.id < 1
        || (expectedId !== undefined && item.id !== Number(expectedId)) || typeof item.name !== 'string' || typeof item.address !== 'string'
        || !['HOTEL', 'HOMESTAY'].includes(item.type)
        || !['ACTIVE', 'INACTIVE', 'PENDING', 'BLOCKED'].includes(item.status)
        || ![item.description, item.phone].every((value) => value === null || typeof value === 'string')
        || typeof item.check_in_time !== 'string' || typeof item.check_out_time !== 'string'
        || !Array.isArray(item.images) || !item.images.every((image) => typeof image === 'string')) {
        throw new Error('Phản hồi chi tiết cơ sở lưu trú không hợp lệ. Vui lòng thử lại.');
    }
    return {
        id: item.id, name: item.name, type: item.type, address: item.address,
        description: item.description, phone: item.phone, check_in_time: item.check_in_time,
        check_out_time: item.check_out_time, status: item.status, images: [...item.images],
    };
}

function imageData(item) {
    if (!Number.isSafeInteger(item?.id) || item.id < 1 || typeof item.url !== 'string'
        || !/^https?:\/\//.test(item.url) || typeof item.is_primary !== 'boolean') {
        throw new Error('Phản hồi hình ảnh cơ sở không hợp lệ.');
    }
    return { id: item.id, url: item.url, is_primary: item.is_primary };
}

function roomTypeData(item) {
    if (!Number.isSafeInteger(item?.id) || item.id < 1 || typeof item.name !== 'string'
        || (item.description !== null && typeof item.description !== 'string')) {
        throw new Error('Phản hồi danh sách loại phòng không hợp lệ.');
    }
    return { id: item.id, name: item.name, description: item.description };
}

function propertyId(id) {
    if (!/^[1-9]\d*$/.test(String(id)) || !Number.isSafeInteger(Number(id))) {
        const error = new Error('Không tìm thấy cơ sở lưu trú.');
        error.status = 404;
        throw error;
    }
    return encodeURIComponent(id);
}

async function mutateImage(client, id, imageId, kind, { signal } = {}) {
    const url = '/api/host/properties/' + propertyId(id) + '/images/' + propertyId(imageId);
    let submitted = false;
    try {
        await client.get('/sanctum/csrf-cookie', { signal, timeout: 10000 });
        submitted = true;
        const response = kind === 'primary'
            ? await client.patch(url + '/primary', undefined, { signal, timeout: 10000 })
            : await client.delete(url, { signal, timeout: 10000 });
        const body = response.data;
        if (response.status !== 200 || body?.success !== true || typeof body.message !== 'string') {
            throw new Error('Phản hồi thao tác ảnh không hợp lệ.');
        }
        if (kind === 'primary') {
            if (!Array.isArray(body.data)) throw new Error('Danh sách ảnh không hợp lệ.');
            const images = body.data.map(imageData);
            if (!images.some((image) => image.id === Number(imageId) && image.is_primary)
                || images.filter((image) => image.is_primary).length !== 1) throw new Error('Ảnh đại diện không hợp lệ.');
            return images;
        }
        const data = body.data;
        if (data?.deleted_image_id !== Number(imageId) || !Array.isArray(data.images)
            || !['deleted', 'missing', 'not_managed', 'shared', 'failed'].includes(data.cleanup_status)) {
            throw new Error('Phản hồi xóa ảnh không hợp lệ.');
        }
        const images = data.images.map(imageData);
        if (images.some((image) => image.id === Number(imageId))) throw new Error('Ảnh đã xóa còn trong danh sách.');
        return { deleted_image_id: data.deleted_image_id, images, cleanup_status: data.cleanup_status };
    } catch (error) {
        const status = error.response?.status;
        error.outcomeUnknown = submitted && (!status || status >= 500);
        throw error;
    }
}

export function createHostPropertyService(client) {
    return {
        async listRoomTypes(id, { signal } = {}) {
            const url = '/api/host/properties/' + propertyId(id) + '/room-types';
            const { data: body } = await client.get(url, { signal, timeout: 10000 });
            if (body?.success !== true || typeof body.message !== 'string' || !Array.isArray(body.data)) {
                throw new Error('Phản hồi danh sách loại phòng không hợp lệ.');
            }

            return body.data.map(roomTypeData);
        },
        async createRoomType(id, values, { signal } = {}) {
            const url = '/api/host/properties/' + propertyId(id) + '/room-types';
            const payload = { name: values.name, description: values.description };
            const options = { signal, timeout: 10000 };
            let submitted = false;
            try {
                await client.get('/sanctum/csrf-cookie', options);
                submitted = true;
                const { status, data: body } = await client.post(url, payload, options);
                if (status !== 201 || body?.success !== true || typeof body.message !== 'string') {
                    throw new Error('Phản hồi thêm loại phòng không hợp lệ.');
                }
                return roomTypeData(body.data);
            } catch (error) {
                const status = error.response?.status;
                error.outcomeUnknown = submitted && (!status || status >= 500);
                throw error;
            }
        },
        async deleteRoomType(id, { signal } = {}) {
            if (!/^[1-9]\d*$/.test(String(id)) || !Number.isSafeInteger(Number(id))) {
                const error = new Error('Không tìm thấy loại phòng.');
                error.status = 404;
                throw error;
            }
            const options = { signal, timeout: 10000 };
            let submitted = false;
            try {
                await client.get('/sanctum/csrf-cookie', options);
                submitted = true;
                const { status, data: body } = await client.delete('/api/host/room-types/' + encodeURIComponent(id), options);
                if (status !== 200 || body?.success !== true || typeof body.message !== 'string'
                    || body.data?.deleted_room_type_id !== Number(id)) {
                    throw new Error('Phản hồi xóa loại phòng không hợp lệ.');
                }
                return { deleted_room_type_id: body.data.deleted_room_type_id };
            } catch (error) {
                const status = error.response?.status;
                error.outcomeUnknown = submitted && (!status || status >= 500);
                throw error;
            }
        },
        async updateRoomType(id, values, { signal } = {}) {
            if (!/^[1-9]\d*$/.test(String(id)) || !Number.isSafeInteger(Number(id))) {
                const error = new Error('Không tìm thấy loại phòng.');
                error.status = 404;
                throw error;
            }
            const url = '/api/host/room-types/' + encodeURIComponent(id);
            const payload = { name: values.name, description: values.description ?? null };
            const options = { signal, timeout: 10000 };
            let submitted = false;
            try {
                await client.get('/sanctum/csrf-cookie', options);
                submitted = true;
                const { status, data: body } = await client.put(url, payload, options);
                if (status !== 200 || body?.success !== true || typeof body.message !== 'string' || body.data?.id !== Number(id)) {
                    throw new Error('Phản hồi cập nhật loại phòng không hợp lệ.');
                }
                return roomTypeData(body.data);
            } catch (error) {
                const status = error.response?.status;
                error.outcomeUnknown = submitted && (!status || status >= 500);
                throw error;
            }
        },
        async create(values, { signal } = {}) {
            const payload = Object.fromEntries(
                ['name', 'type', 'address', 'description', 'phone', 'check_in_time', 'check_out_time']
                    .filter((field) => values[field] !== undefined).map((field) => [field, values[field]]),
            );
            let submitted = false;
            try {
                await client.get('/sanctum/csrf-cookie', { signal, timeout: 10000 });
                submitted = true;
                const response = await client.post('/api/host/properties', payload, { signal, timeout: 10000 });
                if (response.status !== 201) throw new Error('Phản hồi tạo cơ sở chưa hợp lệ.');
                return detailData(response.data);
            } catch (error) {
                const status = error.response?.status;
                error.outcomeUnknown = submitted && (!status || status >= 500);
                throw error;
            }
        },
        async update(id, values, { signal } = {}) {
            if (!/^[1-9]\d*$/.test(String(id)) || !Number.isSafeInteger(Number(id))) {
                const error = new Error('Không tìm thấy cơ sở lưu trú.');
                error.status = 404;
                throw error;
            }
            const payload = Object.fromEntries(
                ['name', 'type', 'address', 'description', 'phone', 'check_in_time', 'check_out_time']
                    .map((field) => [field, values[field]]),
            );
            let submitted = false;
            try {
                await client.get('/sanctum/csrf-cookie', { signal, timeout: 10000 });
                submitted = true;
                const response = await client.put('/api/host/properties/' + encodeURIComponent(id), payload, { signal, timeout: 10000 });
                if (response.status !== 200) throw new Error('Phản hồi cập nhật cơ sở chưa hợp lệ.');
                return detailData(response.data, id);
            } catch (error) {
                const status = error.response?.status;
                error.outcomeUnknown = submitted && (!status || status >= 500);
                throw error;
            }
        },
        async changeStatus(id, values, { signal } = {}) {
            if (!/^[1-9]\d*$/.test(String(id)) || !Number.isSafeInteger(Number(id))) {
                const error = new Error('Không tìm thấy cơ sở lưu trú.');
                error.status = 404;
                throw error;
            }
            let submitted = false;
            try {
                await client.get('/sanctum/csrf-cookie', { signal, timeout: 10000 });
                submitted = true;
                const response = await client.patch('/api/host/properties/' + encodeURIComponent(id) + '/status',
                    { status: values.status, expected_status: values.expected_status }, { signal, timeout: 10000 });
                if (response.status !== 200) throw new Error('Phản hồi thay đổi trạng thái chưa hợp lệ.');
                return detailData(response.data, id);
            } catch (error) {
                const status = error.response?.status;
                error.outcomeUnknown = submitted && (!status || status >= 500);
                throw error;
            }
        },
        makePrimaryImage(id, imageId, options) {
            return mutateImage(client, id, imageId, 'primary', options);
        },
        deleteImage(id, imageId, options) {
            return mutateImage(client, id, imageId, 'delete', options);
        },
        async images(id, { signal } = {}) {
            const response = await client.get('/api/host/properties/' + propertyId(id) + '/images', { signal, timeout: 10000 });
            const body = response.data;
            if (body?.success !== true || typeof body.message !== 'string' || !Array.isArray(body.data)) {
                throw new Error('Phản hồi danh sách hình ảnh không hợp lệ.');
            }
            return body.data.map(imageData);
        },
        async uploadImage(id, file, { signal } = {}) {
            const url = '/api/host/properties/' + propertyId(id) + '/images';
            const payload = new FormData();
            payload.append('image', file);
            let submitted = false;
            try {
                await client.get('/sanctum/csrf-cookie', { signal, timeout: 10000 });
                submitted = true;
                const response = await client.post(url, payload, { signal, timeout: 30000 });
                if (response.status !== 201 || response.data?.success !== true || typeof response.data.message !== 'string') {
                    throw new Error('Phản hồi upload ảnh không hợp lệ.');
                }
                return imageData(response.data.data);
            } catch (error) {
                const status = error.response?.status;
                error.outcomeUnknown = submitted && (!status || status >= 500);
                throw error;
            }
        },
        async detail(id, { signal } = {}) {
            if (!/^[1-9]\d*$/.test(String(id)) || !Number.isSafeInteger(Number(id))) {
                const error = new Error('Không tìm thấy cơ sở lưu trú.');
                error.status = 404;
                throw error;
            }
            const response = await client.get('/api/host/properties/' + encodeURIComponent(id), { signal, timeout: 10000 });
            return detailData(response.data, id);
        },
        async list({ signal } = {}) {
            const response = await client.get('/api/host/properties', { signal, timeout: 10000 });
            const body = response.data;
            if (body?.success !== true || typeof body.message !== 'string' || !Array.isArray(body.data)
                || !body.data.every((item) => Number.isSafeInteger(item?.id) && item.id > 0 && typeof item.name === 'string')) {
                throw new Error('Phản hồi danh sách cơ sở lưu trú không hợp lệ. Vui lòng thử lại.');
            }
            return body.data.map(({ id, name }) => ({ id, name }));
        },
    };
}

export const hostPropertyService = createHostPropertyService(api);
