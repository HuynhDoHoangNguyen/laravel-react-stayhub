import { requireApiId } from './apiId.js';

const verificationPath = (id) => `/api/admin/properties/${requireApiId(id, 'Mã cơ sở')}/verification`;

export function createAdminPropertyApi(client) {
  return {
    async list(params, { signal } = {}) {
      return (await client.get('/api/admin/properties', { params, signal })).data;
    },
    async verification(id, { signal } = {}) {
      return (await client.get(verificationPath(id), { signal })).data;
    },
    async downloadVerification(id, { signal } = {}) {
      return (await client.get(`${verificationPath(id)}/document`, { signal, responseType: 'blob' })).data;
    },
    async reviewVerification(id, input, { signal } = {}) {
      const url = verificationPath(id);
      await client.get('/sanctum/csrf-cookie', { signal });
      return (await client.patch(url, input, { signal, timeout: 15000 })).data;
    },
  };
}
