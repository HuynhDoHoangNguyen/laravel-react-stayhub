import api from './axios';

export const roomSearchApi = {
  search(params) {
    return api.get('/api/public/rooms/search', { params });
  },
};
