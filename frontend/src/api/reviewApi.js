import { requireApiId } from './apiId.js';

/** HTTP endpoint/payload/signal boundary; validation and scanning remain in service. */
export function createReviewApi(client) {
  return {
    async getPublicPropertyReviews(id, params, { signal } = {}) {
      return (await client.get(`/api/public/properties/${requireApiId(id, 'Mã cơ sở lưu trú')}/reviews`, { params, signal })).data;
    },
    async createBookingReview(id, payload, { signal } = {}) {
      return (await client.post(`/api/customer/bookings/${requireApiId(id, 'Mã đơn đặt phòng')}/review`, payload, { signal })).data;
    },
    async getCustomerReviews(params, { signal } = {}) {
      return (await client.get('/api/customer/reviews', { params, signal })).data;
    },
    async getHostReviews(params, { signal } = {}) {
      return (await client.get('/api/host/reviews', { params, signal })).data;
    },
  };
}
