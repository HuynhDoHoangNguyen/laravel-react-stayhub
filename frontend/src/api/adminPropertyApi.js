export function createAdminPropertyApi(client) {
  return {
    async getProperty(propertyId, { signal } = {}) {
      return (await client.get(`/api/admin/properties/${encodeURIComponent(propertyId)}`, { signal })).data;
    },
    async getProperties(params, { signal } = {}) {
      return (await client.get('/api/admin/properties', { params, signal })).data;
    },
  };
}
