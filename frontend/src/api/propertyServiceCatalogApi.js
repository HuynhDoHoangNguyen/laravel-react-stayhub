import api from './axios.js';

const SERVICES_URL = '/api/host/services';

export function createPropertyServiceCatalogApi(client) {
  return {
    async getHostProperties(options = {}) {
      return (await client.get('/api/host/properties', { signal: options.signal })).data;
    },
    async list(params, options = {}) {
      return (await client.get(SERVICES_URL, { params, signal: options.signal })).data;
    },
    async create(payload) {
      return (await client.post(SERVICES_URL, payload)).data;
    },
    async update(serviceId, payload) {
      return (await client.put(`${SERVICES_URL}/${serviceId}`, payload)).data;
    },
    async setStatus(serviceId, status) {
      return (await client.patch(`${SERVICES_URL}/${serviceId}/status`, { status })).data;
    },
  };
}

export const propertyServiceCatalogApi = createPropertyServiceCatalogApi(api);
