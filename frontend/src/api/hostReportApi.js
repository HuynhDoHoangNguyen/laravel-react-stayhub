import api from './axios.js';
import { requireApiId } from './apiId.js';

const endpoint = (id) => `/api/customer/bookings/${requireApiId(id, 'Mã đơn đặt phòng')}/host-report`;

export const hostReportApi = {
  async current(id, { signal } = {}) {
    return (await api.get(endpoint(id), { signal, timeout: 10000 })).data;
  },
  async submit(id, payload, { signal } = {}) {
    const url = endpoint(id);
    await api.get('/sanctum/csrf-cookie', { signal, timeout: 10000 });
    let body = { reason: payload.reason, description: payload.description };
    if (payload.evidence) {
      body = new FormData();
      body.append('reason', payload.reason);
      body.append('description', payload.description);
      body.append('evidence', payload.evidence);
    }
    return (await api.post(url, body, { signal, timeout: 15000 })).data;
  },
};
