import api from './axios.js';

export const roomSearchApi = {
  search(params) {
    return api.get('/api/public/rooms/search', { params });
  },
};
