import api from '../api/axios';

/**
 * Search available rooms with filters and pagination.
 *
 * @param {Record<string, any>} params
 * @returns {Promise<{ success: boolean, message: string, data: any[], meta: any }>}
 */
export async function searchRooms(params) {
    const response = await api.get('/api/public/rooms/search', { params });
    return response.data;
}
