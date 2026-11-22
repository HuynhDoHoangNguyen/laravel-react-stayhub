import test, { mock } from 'node:test';
import assert from 'node:assert/strict';
import axios, { AxiosError, CanceledError } from 'axios';
import { normalizeApiError } from '../src/api/apiError.js';

let adapter;
const client = axios.create({ adapter: (config) => adapter(config) });
mock.module(new URL('../src/api/axios.js', import.meta.url).href, { defaultExport: client });
const { getAdminBookings, getAdminBookingOptions } = await import('../src/services/adminBookingService.js');
test.after(() => mock.restoreAll());

const links = { first: '?page=1', last: '?page=1', prev: null, next: null };
const meta = { current_page: 1, last_page: 1, per_page: 10, total: 1, from: 1, to: 1 };

test('host options preserve display names and send only options params, signal and trimmed search', async () => {
  const { signal } = new AbortController();
  adapter = async (config) => {
    assert.equal(config.url, '/api/admin/bookings/filter-options');
    assert.equal(config.method, 'get');
    assert.equal(config.signal, signal);
    assert.deepEqual(config.params, { type: 'hosts', page: 1, per_page: 10, search: '0' });
    return { data: { data: [{ id: 21, name: '0', email: 'private@example.test' }], meta, links } };
  };
  assert.deepEqual(await getAdminBookingOptions({ type: 'hosts', search: ' 0 ', hostId: 42, status: 'PENDING' }, { signal }),
    { data: [{ id: 21, name: '0' }], meta, links });
});

test('property options use the selected host ID and preserve empty past-end pages', async () => {
  const result = { data: [], links, meta: { ...meta, current_page: 4, from: null, to: null } };
  adapter = async ({ params }) => {
    assert.deepEqual(params, { type: 'properties', host_id: 21, page: 4, per_page: 10 });
    return { data: result };
  };
  assert.deepEqual(await getAdminBookingOptions({ type: 'properties', hostId: 21, page: 4 }), result);
});

test('property mapper requires a safe host ID and preserves the projection', async () => {
  const row = { id: 51, name: '  ', host_id: 21 };
  adapter = async () => ({ data: { data: [{ ...row, junk: true }], meta, links } });
  assert.deepEqual(await getAdminBookingOptions({ type: 'properties' }), { data: [row], meta, links });
  for (const data of [[{ ...row, host_id: null }], [{ ...row, id: 9007199254740992 }], [{ ...row, name: null }]]) {
    adapter = async () => ({ data: { data, meta, links } });
    await assert.rejects(getAdminBookingOptions({ type: 'properties' }), { code: 'INVALID_ADMIN_BOOKING_RESPONSE' });
  }
  adapter = async () => ({ data: { data: [] } });
  await assert.rejects(getAdminBookingOptions({ type: 'hosts' }), { code: 'INVALID_ADMIN_BOOKING_RESPONSE' });
});

test('list sends the selected IDs alongside status and text; clearing omits old IDs', async () => {
  const calls = [];
  adapter = async ({ url, params }) => {
    assert.equal(url, '/api/admin/bookings');
    calls.push(params);
    return { data: { data: [], links, meta: { ...meta, total: 0, from: null, to: null } } };
  };
  await getAdminBookings({ hostId: 21, propertyId: 51, search: 'mã', status: 'PENDING' });
  await getAdminBookings({ hostId: 22, search: 'mã', status: 'PENDING' });
  await getAdminBookings();
  assert.deepEqual(calls, [
    { host_id: 21, property_id: 51, search: 'mã', status: 'PENDING', page: 1, per_page: 10 },
    { host_id: 22, search: 'mã', status: 'PENDING', page: 1, per_page: 10 },
    { page: 1, per_page: 10 },
  ]);
});

test('customer options preserve projection, independent query and signal', async () => {
  const { signal } = new AbortController();
  const row = { id: 31, name: '0' };
  adapter = async (config) => {
    assert.equal(config.url, '/api/admin/bookings/filter-options');
    assert.equal(config.signal, signal);
    assert.deepEqual(config.params, { type: 'customers', search: '0', page: 2, per_page: 10 });
    return { data: { data: [{ ...row, email: 'private@example.test' }], meta, links } };
  };
  assert.deepEqual(await getAdminBookingOptions({ type: 'customers', hostId: 21, search: ' 0 ', page: 2 }, { signal }),
    { data: [row], links, meta });
  adapter = async () => ({ data: { data: [{ id: '31', name: '0' }], meta, links } });
  await assert.rejects(getAdminBookingOptions({ type: 'customers' }), { code: 'INVALID_ADMIN_BOOKING_RESPONSE' });
  adapter = async () => { throw new CanceledError(); };
  await assert.rejects(getAdminBookingOptions({ type: 'customers' }), (error) => normalizeApiError(error).isCancelled);
});

test('list composes customer, filters and date strings, omitting empty endpoints', async () => {
  const calls = [];
  adapter = async ({ url, params }) => {
    assert.equal(url, '/api/admin/bookings');
    calls.push(params);
    return { data: { data: [], links, meta: { ...meta, total: 0, from: null, to: null } } };
  };
  await getAdminBookings({ customerId: 31, hostId: 21, propertyId: 51, status: 'PENDING', search: ' 0 ',
    checkInFrom: '2026-11-20', checkInTo: '2026-11-22' });
  await getAdminBookings({ customerId: 31, checkInTo: '2026-11-22' });
  await getAdminBookings({ checkInFrom: '', checkInTo: '' });
  assert.deepEqual(calls, [
    { customer_id: 31, host_id: 21, property_id: 51, status: 'PENDING', search: '0',
      check_in_from: '2026-11-20', check_in_to: '2026-11-22', page: 1, per_page: 10 },
    { customer_id: 31, check_in_to: '2026-11-22', page: 1, per_page: 10 },
    { page: 1, per_page: 10 },
  ]);
});

test('options preserve HTTP field errors and transport cancellation', async () => {
  const rejected = new AxiosError('Invalid query', undefined, undefined, undefined, {
    status: 422, data: { code: 'ADMIN_BOOKING_INVALID_QUERY', errors: { search: ['Từ khóa quá dài.'] } },
  });
  adapter = async () => { throw rejected; };
  await assert.rejects(getAdminBookingOptions({ type: 'hosts' }), (error) => {
    assert.equal(error, rejected);
    assert.deepEqual(normalizeApiError(error).fields, { search: ['Từ khóa quá dài.'] });
    return true;
  });
  adapter = async () => { throw new CanceledError(); };
  await assert.rejects(getAdminBookingOptions({ type: 'hosts' }), (error) => normalizeApiError(error).isCancelled);
});
