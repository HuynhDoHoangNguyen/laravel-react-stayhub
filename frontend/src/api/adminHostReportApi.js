import api from './axios.js';
import { requireApiId } from './apiId.js';

const base = '/api/admin/host-reports';
const detailPath = (id) => `${base}/${requireApiId(id, 'Mã báo cáo')}`;

export const adminHostReportApi = {
  async list(params, { signal } = {}) {
    return (await api.get(base, { params, signal, timeout: 10000 })).data;
  },
  async detail(id, { signal } = {}) {
    return (await api.get(detailPath(id), { signal, timeout: 10000 })).data;
  },
  async download(id, { signal } = {}) {
    return (await api.get(`${detailPath(id)}/evidence`, { signal, timeout: 15000, responseType: 'blob' })).data;
  },
  async decide(id, payload, { signal } = {}) {
    const path = detailPath(id);
    await api.get('/sanctum/csrf-cookie', { signal, timeout: 10000 });
    return (await api.patch(`${path}/decision`, payload, { signal, timeout: 15000 })).data;
  },
};
