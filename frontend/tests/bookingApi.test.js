import test, { mock } from 'node:test';
import assert from 'node:assert/strict';
import axios from 'axios';

const requests = [];
const client = axios.create({ adapter: async (config) => {
  requests.push(config);
  return {
    config,
    status: config.url.endsWith('/preview') ? 200 : 201,
    data: { success: true, message: 'Fixture', data: { id: 17 } },
  };
} });
mock.module(new URL('../src/api/axios.js', import.meta.url).href, { defaultExport: client });
const { bookingApi } = await import('../src/api/bookingApi.js');

test.after(() => mock.restoreAll());

test('preview and create adapters preserve endpoint, method, and payload', async () => {
  const payload = { room_id: 9, check_in_date: '2026-12-10', check_out_date: '2026-12-12', guest_count: 2 };

  assert.deepEqual(await bookingApi.previewBooking(payload), {
    success: true, message: 'Fixture', data: { id: 17 },
  });
  assert.deepEqual(await bookingApi.createBooking(payload), {
    success: true, message: 'Fixture', data: { id: 17 },
  });
  assert.deepEqual(requests.map(({ method, url, data }) => ({ method, url, data: JSON.parse(data) })), [
    { method: 'post', url: '/api/public/bookings/preview', data: payload },
    { method: 'post', url: '/api/customer/bookings', data: payload },
  ]);
});

test('customer list, detail, services, and cancel adapters use the existing paths and query shape', async () => {
  const params = { page: 2, per_page: 5, status: 'CONFIRMED' };
  const controller = new AbortController();

  await bookingApi.getCustomerBookings(params, { signal: controller.signal });
  await bookingApi.getCustomerBookingDetail(17, { signal: controller.signal });
  await bookingApi.getCustomerBookingServices(17, { signal: controller.signal });
  await bookingApi.cancelCustomerBooking(17);

  assert.deepEqual(requests.slice(-4).map(({ method, url, params: query, data, signal }) => ({
    method, url, params: query, data, hasSignal: Boolean(signal),
  })), [
    { method: 'get', url: '/api/customer/bookings', params, data: undefined, hasSignal: true },
    { method: 'get', url: '/api/customer/bookings/17', params: undefined, data: undefined, hasSignal: true },
    { method: 'get', url: '/api/customer/bookings/17/services', params: undefined, data: undefined, hasSignal: true },
    { method: 'post', url: '/api/customer/bookings/17/cancel', params: undefined, data: undefined, hasSignal: false },
  ]);
});
