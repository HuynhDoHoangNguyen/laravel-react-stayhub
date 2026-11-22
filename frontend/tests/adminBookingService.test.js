import test, { mock } from 'node:test';
import assert from 'node:assert/strict';
import axios, { AxiosError, CanceledError } from 'axios';
import { normalizeApiError } from '../src/api/apiError.js';

let adapter;
const client = axios.create({ adapter: (config) => adapter(config) });
mock.module(new URL('../src/api/axios.js', import.meta.url).href, { defaultExport: client });
const { getAdminBookings } = await import('../src/services/adminBookingService.js');
test.after(() => mock.restoreAll());

const row = {
  id: 101, booking_code: 'BK-0', customer_id: 31, customer_name: '0',
  room_id: 71, room_name: null, room_number: null, property_id: null, property_name: null,
  host_id: null, host_name: null, check_in_date: '2026-11-21', check_out_date: '2026-11-22',
  guest_count: 2, number_of_nights: 1, price_per_night: '100.10', room_total: '777.77', status: 'PENDING',
  created_at: '2026-11-20T17:30:00.000000Z',
};
const links = { first: '?page=1', last: '?page=2', prev: null, next: '?page=2' };
const meta = { current_page: 1, last_page: 2, per_page: 20, total: 21, from: 1, to: 20, path: '/api/admin/bookings' };

test('list sends canonical params and signal, preserves snapshots and strips private fields', async () => {
  const { signal } = new AbortController();
  adapter = async (config) => {
    assert.equal(config.url, '/api/admin/bookings');
    assert.equal(config.method, 'get');
    assert.equal(config.signal, signal);
    assert.deepEqual(config.params, { page: 1, per_page: 20, status: 'PENDING', search: '0' });
    return { data: { data: [{ ...row, customer_email: 'private@example.test' }], links, meta } };
  };
  assert.deepEqual(await getAdminBookings({ search: ' 0 ', status: 'PENDING', perPage: 20 }, { signal }), { data: [row], links, meta });
});

test('empty past-end page preserves requested page and omits blank filters', async () => {
  const body = { data: [], links: { ...links, next: null }, meta: { ...meta, current_page: 5, from: null, to: null } };
  adapter = async ({ params }) => {
    assert.deepEqual(params, { page: 5, per_page: 20 });
    return { data: body };
  };
  assert.deepEqual(await getAdminBookings({ page: 5, perPage: 20, search: ' ' }), body);
});

test('malformed success is a contract error', async () => {
  for (const body of [
    { data: [] },
    { data: [row], links, meta: { ...meta, total: '21' } },
    ...[{ room_total: 777.77 }, { status: 'PAID' }, { check_in_date: '2026-02-30' }, { host_name: undefined }]
      .map((change) => ({ data: [{ ...row, ...change }], links, meta })),
  ]) {
    adapter = async () => ({ data: body });
    await assert.rejects(getAdminBookings(), { code: 'INVALID_ADMIN_BOOKING_RESPONSE' });
  }
});

test('transport failures retain their identity and shared error classification', async () => {
  const validation = new AxiosError('Invalid query', undefined, undefined, undefined, {
    status: 422, data: { code: 'ADMIN_BOOKING_INVALID_QUERY', errors: { search: ['Từ khóa quá dài.'] } },
  });
  adapter = async () => { throw validation; };
  await assert.rejects(getAdminBookings(), (error) => {
    assert.equal(error, validation);
    assert.equal(normalizeApiError(error).code, 'ADMIN_BOOKING_INVALID_QUERY');
    assert.deepEqual(normalizeApiError(error).fields, { search: ['Từ khóa quá dài.'] });
    return true;
  });
  adapter = async () => { throw new CanceledError(); };
  await assert.rejects(getAdminBookings(), (error) => normalizeApiError(error).isCancelled);
});
