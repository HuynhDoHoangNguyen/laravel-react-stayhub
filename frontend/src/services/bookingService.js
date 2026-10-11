import api from '../api/axios';

/**
 * Preview room price and calculate number of nights.
 *
 * @param {{ room_id: number, check_in_date: string, check_out_date: string, guest_count: number }} payload
 * @returns {Promise<{ success: boolean, message: string, data: any }>}
 */
export async function previewBooking(payload) {
    const response = await api.post('/api/public/bookings/preview', payload);
    return response.data;
}

/**
 * Create a new booking for customer.
 *
 * @param {{ room_id: number, check_in_date: string, check_out_date: string, guest_count: number, note?: string }} payload
 * @returns {Promise<{ success: boolean, message: string, data: any }>}
 */
export async function createBooking(payload) {
    const response = await api.post('/api/customer/bookings', payload);
    return response.data;
}

/**
 * Get customer booking history.
 *
 * @param {Record<string, any>} [params]
 * @returns {Promise<{ success: boolean, message: string, data: any[], meta: any }>}
 */
export async function getCustomerBookings(params) {
    const response = await api.get('/api/customer/bookings', { params });
    return response.data;
}

/**
 * Get customer booking details by ID.
 *
 * @param {number|string} bookingId
 * @returns {Promise<{ success: boolean, message: string, data: any }>}
 */
export async function getCustomerBookingDetail(bookingId) {
    const response = await api.get(`/api/customer/bookings/${bookingId}`);
    return response.data;
}

/**
 * Cancel a booking for customer.
 *
 * @param {number|string} bookingId
 * @returns {Promise<{ success: boolean, message: string, data: any }>}
 */
export async function cancelBooking(bookingId) {
    const response = await api.post(`/api/customer/bookings/${bookingId}/cancel`);
    return response.data;
}

/**
 * Get services used in a customer's booking.
 *
 * @param {number|string} bookingId
 * @returns {Promise<{ success: boolean, message: string, data: any[] }>}
 */
export async function getCustomerBookingServices(bookingId) {
    const response = await api.get(`/api/customer/bookings/${bookingId}/services`);
    return response.data;
}
