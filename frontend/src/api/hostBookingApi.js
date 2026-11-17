import api from './axios.js';

const BASE_URL = '/api/host/bookings';

export const hostBookingApi = {
  list(params, options = {}) {
    return api.get(BASE_URL, { params, signal: options.signal });
  },

  detail(id, options = {}) {
    return api.get(`${BASE_URL}/${id}`, { signal: options.signal });
  },

  transition(id, action, payload = {}) {
    return api.post(`${BASE_URL}/${id}/${action}`, payload);
  },
};
