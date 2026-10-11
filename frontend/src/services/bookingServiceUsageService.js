import api from '../api/axios';

/**
 * Get services in a booking (Host view).
 *
 * @param {number|string} bookingId
 * @returns {Promise<{ success: boolean, message: string, data: any[] }>}
 */
export async function getBookingServices(bookingId) {
    const response = await api.get(`/api/host/bookings/${bookingId}/services`);
    return response.data;
}

/**
 * Add a service to a checked-in booking.
 *
 * @param {number|string} bookingId
 * @param {{ service_id: number, quantity: number }} data
 * @returns {Promise<{ success: boolean, message: string, data: any }>}
 */
export async function addBookingService(bookingId, data) {
    const response = await api.post(`/api/host/bookings/${bookingId}/services`, data);
    return response.data;
}

/**
 * Update quantity of a service in a booking.
 *
 * @param {number|string} bookingId
 * @param {number|string} bookingServiceId
 * @param {{ quantity: number }} data
 * @returns {Promise<{ success: boolean, message: string, data: any }>}
 */
export async function updateBookingService(bookingId, bookingServiceId, data) {
    const response = await api.put(`/api/host/bookings/${bookingId}/services/${bookingServiceId}`, data);
    return response.data;
}

/**
 * Remove a service from a booking.
 *
 * @param {number|string} bookingId
 * @param {number|string} bookingServiceId
 * @returns {Promise<void>}
 */
export async function deleteBookingService(bookingId, bookingServiceId) {
    await api.delete(`/api/host/bookings/${bookingId}/services/${bookingServiceId}`);
}
