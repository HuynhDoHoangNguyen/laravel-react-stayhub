import api from './axios.js';

export const roomSearchApi = {
  search(params, options = {}) {
    return api.get('/api/public/rooms/search', { params, signal: options?.signal });
  },
};
