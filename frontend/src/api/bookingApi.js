import api from './axios.js';

export function createBookingApi(client) {
  return {
    async previewBooking(payload) {
      return (await client.post('/api/public/bookings/preview', payload)).data;
    },
    async createBooking(payload) {
      return (await client.post('/api/customer/bookings', payload)).data;
    },
  };
}

export const bookingApi = createBookingApi(api);
