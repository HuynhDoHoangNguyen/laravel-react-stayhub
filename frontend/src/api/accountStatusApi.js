export function createAccountStatusApi(client) {
  return {
    async update(id, payload, { signal } = {}) {
      const options = { signal, timeout: 10000 };
      // A failed CSRF request has not attempted the mutation.
      await client.get('/sanctum/csrf-cookie', options);
      try {
        return await client.patch(`/api/admin/users/${encodeURIComponent(id)}/status`, payload, options);
      } catch (error) {
        error.outcomeUnknown = !error.response?.status || error.response.status >= 500;
        throw error;
      }
    },
  };
}
