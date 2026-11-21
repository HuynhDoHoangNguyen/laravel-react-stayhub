/** Transport only; cookie/session handling stays in shared Axios. */
export function createStatisticsApi(client) {
  return {
    async getStatistics(params = {}, { signal } = {}) {
      return (await client.get('/api/admin/statistics', { params, signal })).data;
    },
  };
}
