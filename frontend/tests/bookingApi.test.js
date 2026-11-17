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

test('customer list, detail, and cancel adapters use the existing paths and query shape', async () => {
  const params = { page: 2, per_page: 5, status: 'CONFIRMED' };

  await bookingApi.getCustomerBookings(params);
  await bookingApi.getCustomerBookingDetail(17);
  await bookingApi.cancelCustomerBooking(17);

  assert.deepEqual(requests.slice(-3).map(({ method, url, params: query, data }) => ({ method, url, params: query, data })), [
    { method: 'get', url: '/api/customer/bookings', params, data: undefined },
    { method: 'get', url: '/api/customer/bookings/17', params: undefined, data: undefined },
    { method: 'post', url: '/api/customer/bookings/17/cancel', params: undefined, data: undefined },
  ]);
});
