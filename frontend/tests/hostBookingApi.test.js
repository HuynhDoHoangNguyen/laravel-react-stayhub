import test, { mock } from 'node:test';
import assert from 'node:assert/strict';
import axios from 'axios';

const requests = [];
const client = axios.create({ adapter: async (config) => {
  requests.push(config);
  return { config, status: 200, data: { success: true, data: { id: 8 }, meta: { current_page: 2, total: 9 } } };
} });
mock.module(new URL('../src/api/axios.js', import.meta.url).href, { defaultExport: client });
const { hostBookingApi } = await import('../src/api/hostBookingApi.js');

test.after(() => mock.restoreAll());

test('host booking API uses existing list, detail, transition, and booking-service endpoints', async () => {
  const options = { signal: new AbortController().signal };
  await hostBookingApi.list({ page: 2, per_page: 5, status: 'PENDING' }, options);
  await hostBookingApi.detail(8, options);
  await hostBookingApi.transition(8, 'reject', { reason: 'Unavailable' });
  await hostBookingApi.listBookingServices(8, options);
  await hostBookingApi.addBookingService(8, { service_id: 4, quantity: 2.5 });
  await hostBookingApi.updateBookingService(8, 21, 3);
  await hostBookingApi.removeBookingService(8, 21);

  assert.deepEqual(requests.map(({ method, url, params, signal, data }) => ({ method, url, params, hasSignal: Boolean(signal), data: typeof data === 'string' ? JSON.parse(data) : data })), [
    { method: 'get', url: '/api/host/bookings', params: { page: 2, per_page: 5, status: 'PENDING' }, hasSignal: true, data: undefined },
    { method: 'get', url: '/api/host/bookings/8', params: undefined, hasSignal: true, data: undefined },
    { method: 'post', url: '/api/host/bookings/8/reject', params: undefined, hasSignal: false, data: { reason: 'Unavailable' } },
    { method: 'get', url: '/api/host/bookings/8/services', params: undefined, hasSignal: true, data: undefined },
    { method: 'post', url: '/api/host/bookings/8/services', params: undefined, hasSignal: false, data: { service_id: 4, quantity: 2.5 } },
    { method: 'put', url: '/api/host/bookings/8/services/21', params: undefined, hasSignal: false, data: { quantity: 3 } },
    { method: 'delete', url: '/api/host/bookings/8/services/21', params: undefined, hasSignal: false, data: undefined },
  ]);
});
