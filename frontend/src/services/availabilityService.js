import api from '../api/axios';

/**
 * Check room availability for given dates and guest count.
 *
 * @param {number|string} roomId
 * @param {{ check_in_date: string, check_out_date: string, guest_count: number }} params
 * @returns {Promise<{ success: boolean, message: string, data: { available: boolean, reasons: string[], blocked_ranges: any[] } }>}
 */
export async function checkRoomAvailability(roomId, params) {
    const response = await api.get(`/api/public/rooms/${roomId}/availability`, { params });
    return response.data;
}
