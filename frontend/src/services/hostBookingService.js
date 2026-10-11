import api from '../api/axios';

/**
 * Get host bookings with filters and pagination.
 *
 * @param {Record<string, any>} [params]
 * @returns {Promise<{ success: boolean, message: string, data: any[], meta: any }>}
 */
export async function getHostBookings(params) {
    const response = await api.get('/api/host/bookings', { params });
    return response.data;
}

/**
 * Get booking details for host.
 *
 * @param {number|string} bookingId
 * @returns {Promise<{ success: boolean, message: string, data: any }>}
 */
export async function getHostBookingDetail(bookingId) {
    const response = await api.get(`/api/host/bookings/${bookingId}`);
    return response.data;
}

/**
 * Confirm a pending booking (PENDING -> CONFIRMED).
 *
 * @param {number|string} bookingId
 * @returns {Promise<{ success: boolean, message: string, data: any }>}
 */
export async function confirmBooking(bookingId) {
    const response = await api.post(`/api/host/bookings/${bookingId}/confirm`);
    return response.data;
}

/**
 * Reject a pending booking (PENDING -> REJECTED).
 *
 * @param {number|string} bookingId
 * @param {string} [reason]
 * @returns {Promise<{ success: boolean, message: string, data: any }>}
 */
export async function rejectBooking(bookingId, reason) {
    const response = await api.post(`/api/host/bookings/${bookingId}/reject`, { reason });
    return response.data;
}

/**
 * Check-in a confirmed booking (CONFIRMED -> CHECKED_IN).
 *
 * @param {number|string} bookingId
 * @returns {Promise<{ success: boolean, message: string, data: any }>}
 */
export async function checkInBooking(bookingId) {
    const response = await api.post(`/api/host/bookings/${bookingId}/check-in`);
    return response.data;
}

/**
 * Check-out a checked-in booking (CHECKED_IN -> COMPLETED).
 *
 * @param {number|string} bookingId
 * @returns {Promise<{ success: boolean, message: string, data: any }>}
 */
export async function checkOutBooking(bookingId) {
    const response = await api.post(`/api/host/bookings/${bookingId}/check-out`);
    return response.data;
}
