/** Transport only. Production uses the shared cookie/session Axios client. */
export function createAdminDashboardApi(client) {
  return {
    async getOverview({ signal } = {}) {
      return (await client.get('/api/admin/dashboard', { signal })).data;
    },
  };
}
