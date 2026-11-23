export function createAdminBookingApi(client) {
  return {
    async options(params, { signal } = {}) {
      return (await client.get('/api/admin/bookings/filter-options', { params, signal })).data;
    },
    async list(params, { signal } = {}) {
      return (await client.get('/api/admin/bookings', { params, signal })).data;
    },
  };
}
