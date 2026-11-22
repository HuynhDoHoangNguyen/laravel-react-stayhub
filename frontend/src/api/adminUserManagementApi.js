export function createAdminUserManagementApi(client) {
  return {
    async listUsers(params, { signal } = {}) {
      return (await client.get('/api/admin/user-management', { params, signal, timeout: 10000 })).data;
    },
    async approveHost(id, { signal } = {}) {
      const options = { signal, timeout: 10000 };
      await client.get('/sanctum/csrf-cookie', options);
      return (await client.post(`/api/admin/users/${encodeURIComponent(id)}/approve-host`, {}, options)).data;
    },
  };
}
