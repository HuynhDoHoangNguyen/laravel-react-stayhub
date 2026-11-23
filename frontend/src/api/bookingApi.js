import api from './axios.js';

export function createBookingApi(client) {
  return {
    async getCustomerBookings(params, options = {}) {
      return (await client.get('/api/customer/bookings', { params, signal: options.signal })).data;
    },
    async getCustomerBookingDetail(id, options = {}) {
      return (await client.get(`/api/customer/bookings/${id}`, { signal: options.signal })).data;
    },
    async getCustomerBookingServices(id, options = {}) {
      return (await client.get(`/api/customer/bookings/${id}/services`, { signal: options.signal })).data;
    },
    async cancelCustomerBooking(id) {
      return (await client.post(`/api/customer/bookings/${id}/cancel`)).data;
    },
    async previewBooking(payload) {
      return (await client.post('/api/public/bookings/preview', payload)).data;
    },
    async createBooking(payload) {
      return (await client.post('/api/customer/bookings', payload)).data;
    },
  };
}

export const bookingApi = createBookingApi(api);
