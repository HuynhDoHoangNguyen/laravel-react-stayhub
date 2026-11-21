/** Transport only; the default client is the shared cookie/session Axios instance. */
export function createHostDashboardApi(client) {
  return {
    async getOverview({ fromMonth, toMonth, signal } = {}) {
      const params = {};
      if (fromMonth !== undefined) params.from_month = fromMonth;
      if (toMonth !== undefined) params.to_month = toMonth;
      return (await client.get('/api/host/dashboard', { params, signal })).data;
    },
  };
}
