export function createAdminHostApi(client) {
  return {
    async getHost(hostId, { signal } = {}) {
      return (await client.get(`/api/admin/hosts/${encodeURIComponent(hostId)}`, { signal })).data;
    },
    async getHosts(params, { signal } = {}) {
      return (await client.get('/api/admin/hosts', { params, signal })).data;
    },
  };
}
