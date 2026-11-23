export function createAdminUserApi(client) {
  return {
    async getCustomers(params, { signal } = {}) {
      return (await client.get('/api/admin/users', { params, signal })).data;
    },
    async getCustomer(customerId, { signal } = {}) {
      return (await client.get(`/api/admin/users/${encodeURIComponent(customerId)}`, { signal })).data;
    },
  };
}
