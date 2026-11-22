export function createAdminHostApi(client) {
  return {
    async getHosts(params, { signal } = {}) {
      return (await client.get('/api/admin/hosts', { params, signal })).data;
    },
  };
}
