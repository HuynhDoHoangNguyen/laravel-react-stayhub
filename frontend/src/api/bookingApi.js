import api from './axios.js';

export function createBookingApi(client) {
  return {
    async getCustomerBookings(params) {
      return (await client.get('/api/customer/bookings', { params })).data;
    },
    async getCustomerBookingDetail(id) {
      return (await client.get(`/api/customer/bookings/${id}`)).data;
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
