import test, { mock } from 'node:test';
import assert from 'node:assert/strict';
import axios, { AxiosError, CanceledError } from 'axios';
import { createAdminPropertyApi } from '../src/api/adminPropertyApi.js';
import { mapAdminProperties, mapAdminPropertyDetail } from '../src/mappers/adminPropertyMapper.js';
import { subscribeToAuthFailures } from '../src/services/authSession.js';

let adapter;
const sharedClient = axios.create({ adapter: (config) => adapter(config) });
mock.module(new URL('../src/api/axios.js', import.meta.url).href, { defaultExport: sharedClient });
const { getAdminProperties, getAdminProperty, normalizePropertyId, createAdminPropertyService, normalizePropertyQuery, readPropertySearch,
  propertySearchParams, parseAdminPropertyError } = await import('../src/services/adminPropertyService.js');
test.after(() => mock.restoreAll());

const body = () => ({
  data: [{ id: 14, name: 'Cơ sở Đà Nẵng', type: 'HOTEL', address: '12 Bạch Đằng, Đà Nẵng',
    status: 'ACTIVE', rooms_count: 0, created_at: '2026-11-23T07:00:00.000000Z',
    host: { id: 3, name: 'Nguyễn An', email: 'host@example.test' } }],
  links: { first: '/api/admin/properties?page=1', last: '/api/admin/properties?page=1', prev: null, next: null },
  meta: { current_page: 1, last_page: 1, per_page: 10, total: 1 },
});

test('defaults and URL roundtrip preserve all filters and pagination for refresh and Back/Forward', () => {
  assert.deepEqual(normalizePropertyQuery(), { q: '', type: '', host_id: '', status: '', page: 1, per_page: 10 });
  const params = propertySearchParams({ q: ' Nguyễn + %_! ', type: 'HOMESTAY', host_id: '23', status: 'PENDING', page: '2', per_page: '20' });
  assert.deepEqual(readPropertySearch(params.toString()), { q: 'Nguyễn + %_!', type: 'HOMESTAY', host_id: 23, status: 'PENDING', page: 2, per_page: 20 });
  assert.equal(propertySearchParams({}).toString(), '');
  for (const status of ['ACTIVE', 'INACTIVE', 'BLOCKED', 'PENDING']) {
    assert.deepEqual(readPropertySearch(`status=${status}&page=1&per_page=50`), { q: '', type: '', host_id: '', status, page: 1, per_page: 50 });
  }
});

test('invalid queries fail before transport without coercing arrays or noncanonical numbers', async () => {
  const cases = [
    [{ status: ['ACTIVE'] }, 'status'], [{ status: 'DELETED' }, 'status'], [{ status: null }, 'status'],
    [{ page: true }, 'page'], [{ page: ['1'] }, 'page'], [{ page: '01' }, 'page'], [{ page: 0 }, 'page'],
    [{ page: -1 }, 'page'], [{ page: '1e2' }, 'page'], [{ page: 1.5 }, 'page'], [{ page: 2147483648 }, 'page'],
    [{ per_page: 11 }, 'per_page'], [{ per_page: ['10'] }, 'per_page'], [{ per_page: '010' }, 'per_page'],
    [{ q: ['text'] }, 'q'], [{ q: null }, 'q'], [{ q: 1 }, 'q'], [{ q: 'á'.repeat(101) }, 'q'],
    [{ type: 'APARTMENT' }, 'type'], [{ type: ['HOTEL'] }, 'type'], [{ type: null }, 'type'],
    ...[0, -1, '01', '1.5', '1e2', ['1'], true, null, ' 1 ', 9007199254740992, '9007199254740992']
      .map((host_id) => [{ host_id }, 'host_id']),
    [{ sort: 'name' }, 'sort'],
  ];
  let calls = 0;
  const service = createAdminPropertyService({ getProperties: async () => { calls++; return body(); } });
  for (const [query, field] of cases) {
    await assert.rejects(service.getProperties(query), (error) => error.isLocalValidation && error.fields[field].length > 0);
  }
  for (const query of [null, [], 'status=ACTIVE']) await assert.rejects(service.getProperties(query));
  assert.equal(calls, 0);
});

test('ambiguous URL fields and prototype-shaped names return validation errors', () => {
  for (const search of ['page=1&page=2', 'q=a&q=b', 'host_id=1&host_id=2', 'type=HOTEL&type=HOMESTAY',
    'status=ACTIVE&status=PENDING', 'status[]=ACTIVE', 'host_id[]=1', 'role=HOST', '__proto__=x', 'constructor=x']) {
    assert.throws(() => readPropertySearch(search), (error) => error.isLocalValidation && Object.keys(error.fields).length > 0);
  }
});

test('public service uses shared GET transport, normalized params, signal and output allowlist', async () => {
  const calls = [];
  const value = body();
  value.data[0].phone = 'not-in-projection';
  value.data[0].host.password = 'must-be-discarded';
  value.meta.internal = 'discard';
  adapter = async (config) => { calls.push(config); return { status: 200, data: value, config, headers: {} }; };
  const signal = new AbortController().signal;
  const result = await getAdminProperties({ q: ' Đà Nẵng ', type: 'HOTEL', host_id: '3', status: 'ACTIVE' }, { signal });
  assert.equal(calls[0].url, '/api/admin/properties');
  assert.equal(calls[0].method, 'get');
  assert.equal(calls[0].signal, signal);
  assert.deepEqual(calls[0].params, { page: 1, per_page: 10, q: 'Đà Nẵng', type: 'HOTEL', host_id: 3, status: 'ACTIVE' });
  assert.deepEqual(Object.keys(result.data[0]), ['id', 'name', 'type', 'address', 'status', 'rooms_count', 'created_at', 'host']);
  assert.deepEqual(Object.keys(result.data[0].host), ['id', 'name', 'email']);
  assert.equal(result.meta.internal, undefined);
  assert.equal(value.data[0].host.password, 'must-be-discarded');
});

test('empty filters are omitted and clearing removes every previously applied filter', async () => {
  let params;
  const service = createAdminPropertyService({ getProperties: async (query) => { params = query; return body(); } });
  await service.getProperties();
  assert.deepEqual(params, { page: 1, per_page: 10 });
  await service.getProperties({ q: 'needle', type: 'HOMESTAY', host_id: 23, status: 'PENDING' });
  assert.deepEqual(params, { page: 1, per_page: 10, q: 'needle', type: 'HOMESTAY', host_id: 23, status: 'PENDING' });
  await service.getProperties({ q: '  ', type: '', host_id: '', status: '' });
  assert.deepEqual(params, { page: 1, per_page: 10 });
});

test('canonical Host IDs and Unicode search bounds match the HTTP contract without mutating caller input', async () => {
  const input = Object.freeze({ q: ` ${'á'.repeat(100)} `, host_id: '9007199254740991', type: 'HOTEL' });
  const result = normalizePropertyQuery(input);
  assert.equal(result.q, 'á'.repeat(100));
  assert.equal(result.host_id, Number.MAX_SAFE_INTEGER);
  assert.deepEqual(readPropertySearch(propertySearchParams(input).toString()), result);
  const value = body(); value.data = []; value.meta.total = 0;
  let sent;
  const service = createAdminPropertyService({ getProperties: async (params) => { sent = params; return value; } });
  assert.deepEqual((await service.getProperties(input)).data, []);
  assert.equal(sent.host_id, Number.MAX_SAFE_INTEGER);
  assert.equal(input.q.startsWith(' '), true);
});

test('pagination through service retains search, type, host and status from the URL', async () => {
  const calls = [];
  const value = body(); value.data = []; value.meta.total = 0; value.meta.current_page = 2;
  const service = createAdminPropertyService({ getProperties: async (params) => { calls.push(params); return value; } });
  const query = readPropertySearch('q=Nguy%E1%BB%85n&type=HOTEL&host_id=3&status=BLOCKED&page=2&per_page=20');
  await service.getProperties(query);
  assert.deepEqual(calls[0], { page: 2, per_page: 20, q: 'Nguyễn', type: 'HOTEL', host_id: 3, status: 'BLOCKED' });
  assert.equal(calls.length, 1);
});

test('mapper preserves statuses, types, nullable date, zero rooms and valid empty pages', () => {
  for (const status of ['ACTIVE', 'INACTIVE', 'BLOCKED', 'PENDING']) {
    const value = body();
    value.data[0].created_at = null; value.data[0].status = status; value.data[0].type = 'HOMESTAY';
    assert.deepEqual(mapAdminProperties(value).data[0], value.data[0]);
  }
  const value = body();
  value.data = []; value.meta.total = 0;
  assert.deepEqual(mapAdminProperties(value).data, []);
  value.meta.total = 12; value.meta.last_page = 2; value.meta.current_page = 3;
  assert.deepEqual(mapAdminProperties(value).meta, value.meta);
});

test('malformed success rejects instead of silently rendering empty or partially valid data', () => {
  const changes = [
    (b) => { delete b.data; }, (b) => { b.data = {}; }, (b) => { b.meta = null; },
    (b) => { b.meta.total = '1'; }, (b) => { b.meta.total = -1; }, (b) => { b.meta.current_page = 0; },
    (b) => { b.meta.last_page = 2; }, (b) => { b.meta.per_page = 11; }, (b) => { b.data = []; },
    (b) => { delete b.links.prev; }, (b) => { b.links.next = 17; },
    (b) => { b.data[0].id = '14'; }, (b) => { b.data[0].rooms_count = '0'; },
    (b) => { b.data[0].rooms_count = -1; }, (b) => { delete b.data[0].rooms_count; },
    (b) => { b.data[0].status = 'DELETED'; }, (b) => { b.data[0].status = ['ACTIVE']; },
    (b) => { b.data[0].type = 'APARTMENT'; }, (b) => { b.data[0].address = null; },
    (b) => { b.data[0].host = null; }, (b) => { b.data[0].host.id = '3'; },
    (b) => { b.data[0].host.email = null; }, (b) => { delete b.data[0].host.name; },
    (b) => { b.data[0].created_at = 'not-a-date'; }, (b) => { delete b.data[0].created_at; },
    (b) => { b.data[0].created_at = '2026-02-30T07:00:00Z'; },
    (b) => { b.data.push({ ...b.data[0] }); b.meta.total = 2; },
  ];
  for (const change of changes) {
    const value = body(); change(value);
    assert.throws(() => mapAdminProperties(value), (error) => error.kind === 'contract' && error.code === 'INVALID_ADMIN_PROPERTY_RESPONSE');
  }
  for (const value of [null, [], {}]) assert.throws(() => mapAdminProperties(value));
});

test('Auth distinguishes expired sessions and blocked accounts from domain role denial', async () => {
  let status = 401, code, original;
  const failures = [];
  const client = axios.create({ adapter: async (config) => {
    original = new AxiosError('Fixture', '', config, null, { status, data: { code }, config, headers: {} });
    throw original;
  } });
  const cleanup = subscribeToAuthFailures(client, (error, version) => failures.push({ error, version }), () => 9);
  try {
    const service = createAdminPropertyService(createAdminPropertyApi(client));
    await assert.rejects(service.getProperties(), (error) => error === original);
    assert.equal(failures.length, 1);
    assert.equal(failures[0].version, 9);
    status = 403;
    await assert.rejects(service.getProperties(), (error) => error === original);
    assert.equal(failures.length, 1);
    for (code of ['ACCOUNT_BLOCKED', 'ACCOUNT_INACTIVE']) {
      await assert.rejects(service.getProperties(), (error) => parseAdminPropertyError(error).code === code);
    }
    assert.equal(failures.length, 3);
  } finally { cleanup(); }
});

test('HTTP, network, timeout, cancellation and contract errors keep their identity and display meaning', async () => {
  const errors = [
    ...[401, 403, 404, 409, 422].map((status) => new AxiosError('Fixture', '', {}, null,
      { status, data: status === 422 ? { errors: { status: ['Trạng thái sai.'] } } : {}, headers: {} })),
    new AxiosError('Private server detail', '', {}, null, { status: 500, data: {}, headers: {} }),
    new AxiosError('Offline', 'ERR_NETWORK'), new AxiosError('Timeout', 'ECONNABORTED'), new CanceledError(),
  ];
  const kinds = ['http', 'http', 'http', 'http', 'http', 'http', 'network', 'timeout', 'cancelled'];
  for (const [index, error] of errors.entries()) {
    const service = createAdminPropertyService({ getProperties: async () => { throw error; } });
    await assert.rejects(service.getProperties(), (failure) => failure === error);
    assert.equal(parseAdminPropertyError(error).kind, kinds[index]);
  }
  assert.deepEqual(parseAdminPropertyError(errors[4]).fields, { status: ['Trạng thái sai.'] });
  assert.equal(parseAdminPropertyError(errors[5]).message.includes('Private'), false);
  const service = createAdminPropertyService({ getProperties: async () => ({}) });
  await assert.rejects(service.getProperties(), (error) => parseAdminPropertyError(error).kind === 'contract');
});

test('aborting the real transport remains recognizable as cancellation', async () => {
  const controller = new AbortController();
  controller.abort();
  await assert.rejects(createAdminPropertyApi(axios.create()).getProperties({}, { signal: controller.signal }),
    (error) => parseAdminPropertyError(error).isCancelled);
});

const detailBody = () => ({ data: { ...body().data[0], description: 'Mô tả cơ sở\nDòng thứ hai', phone: '02363551111',
  check_in_time: '14:00:00', check_out_time: '12:00:00', room_types_count: 2, updated_at: '2026-11-23T08:15:00.123456Z' } });

test('detail uses the selected canonical ID, shared GET transport and signal without list params', async () => {
  let call;
  const value = detailBody();
  value.data.host.password = 'discard';
  value.data.internal = 'discard';
  adapter = async (config) => { call = config; return { status: 200, data: value, config, headers: {} }; };
  const signal = new AbortController().signal;
  const result = await getAdminProperty('14', { signal });
  assert.equal(call.url, '/api/admin/properties/14');
  assert.equal(call.method, 'get');
  assert.equal(call.signal, signal);
  assert.equal(call.params, undefined);
  assert.equal(call.data, undefined);
  assert.deepEqual(Object.keys(result), ['id', 'name', 'type', 'address', 'status', 'rooms_count', 'created_at', 'host',
    'description', 'phone', 'check_in_time', 'check_out_time', 'room_types_count', 'updated_at']);
  assert.deepEqual(Object.keys(result.host), ['id', 'name', 'email']);
  assert.equal(result.room_types_count, 2);
  assert.equal(result.description, value.data.description);
  assert.equal(result.internal, undefined);
  assert.equal(value.data.host.password, 'discard');
});

test('detail rejects invalid ID before transport and agrees on the safe integer limit', async () => {
  let calls = 0;
  const service = createAdminPropertyService({ getProperty: async () => { calls++; return detailBody(); } });
  for (const id of [null, undefined, true, [], ['14'], {}, '', ' 14 ', '014', 0, -1, 1.5, '1e2', '14/other',
    9007199254740992, '9007199254740992']) {
    await assert.rejects(service.getProperty(id), (error) => error.isLocalValidation && error.fields.property.length > 0);
  }
  assert.equal(calls, 0);
  assert.equal(normalizePropertyId('14'), 14);
  assert.equal(normalizePropertyId(Number.MAX_SAFE_INTEGER), Number.MAX_SAFE_INTEGER);
  assert.equal(normalizePropertyId('9007199254740991'), Number.MAX_SAFE_INTEGER);
});

test('detail preserves every property status, nulls, empty strings, zero values and wall-clock times', () => {
  for (const status of ['ACTIVE', 'INACTIVE', 'BLOCKED', 'PENDING']) {
    const value = detailBody();
    Object.assign(value.data, { status, description: null, phone: null, created_at: null, updated_at: null,
      rooms_count: 0, room_types_count: 0, check_in_time: '00:00:00', check_out_time: '23:59:59' });
    assert.deepEqual(mapAdminPropertyDetail(value, 14), value.data);
    value.data.description = ''; value.data.phone = '0';
    assert.deepEqual(mapAdminPropertyDetail(value, 14), value.data);
  }
  const value = detailBody();
  value.data.description = '<script>example</script>\nVăn bản';
  assert.equal(mapAdminPropertyDetail(value, 14).description, value.data.description);
});

test('detail rejects mismatched IDs, partial DTOs and malformed dates, times and counts', () => {
  const changes = [
    (b) => { b.data.id = 15; }, (b) => { b.data.type = 'UNKNOWN'; }, (b) => { b.data.host = null; },
    (b) => { b.data.host.email = null; }, (b) => { b.data.rooms_count = -1; },
    (b) => { b.data.room_types_count = '2'; }, (b) => { b.data.room_types_count = -1; },
    (b) => { delete b.data.room_types_count; }, (b) => { delete b.data.description; },
    (b) => { b.data.description = {}; }, (b) => { b.data.phone = 123; }, (b) => { delete b.data.phone; },
    (b) => { b.data.updated_at = '2026-02-30T00:00:00Z'; }, (b) => { delete b.data.updated_at; },
    (b) => { b.data.check_in_time = '24:00:00'; }, (b) => { b.data.check_out_time = '12:60:00'; },
    (b) => { b.data.check_in_time = '14:00'; }, (b) => { b.data.check_out_time = null; },
    (b) => { b.data.check_in_time = '2026-11-23T14:00:00Z'; },
  ];
  for (const change of changes) {
    const value = detailBody(); change(value);
    assert.throws(() => mapAdminPropertyDetail(value, 14), (error) => error.kind === 'contract' && error.code === 'INVALID_ADMIN_PROPERTY_RESPONSE');
  }
  for (const value of [null, [], {}, { data: [] }, detailBody().data, body()]) {
    assert.throws(() => mapAdminPropertyDetail(value, 14));
  }
});

test('detail HTTP, network, timeout and cancellation failures preserve identity and never fall back to a list row', async () => {
  const errors = [
    ...[401, 403, 404, 409, 422].map((status) => new AxiosError('Fixture', '', {}, null,
      { status, data: status === 422 ? { errors: { property: ['Mã cơ sở sai.'] } } : {}, headers: {} })),
    new AxiosError('Offline', 'ERR_NETWORK'), new AxiosError('Timeout', 'ECONNABORTED'), new CanceledError(),
  ];
  for (const error of errors) {
    const service = createAdminPropertyService({ getProperty: async () => { throw error; } });
    await assert.rejects(service.getProperty(14), (failure) => failure === error);
  }
  assert.equal(parseAdminPropertyError(errors[2]).isNotFound, true);
  assert.deepEqual(parseAdminPropertyError(errors[4]).fields, { property: ['Mã cơ sở sai.'] });
  assert.equal(parseAdminPropertyError(errors[5]).isNetworkError, true);
  assert.equal(parseAdminPropertyError(errors[6]).isTimeout, true);
  assert.equal(parseAdminPropertyError(errors[7]).isCancelled, true);
  const malformed = createAdminPropertyService({ getProperty: async () => ({}) });
  await assert.rejects(malformed.getProperty(14), (error) => parseAdminPropertyError(error).kind === 'contract');
});

test('detail retains Auth failure semantics for expired sessions, role denial and blocked actors', async () => {
  let status = 401, code;
  const failures = [];
  const client = axios.create({ adapter: async (config) => {
    throw new AxiosError('Fixture', '', config, null, { status, data: { code }, config, headers: {} });
  } });
  const cleanup = subscribeToAuthFailures(client, (error, version) => failures.push({ error, version }), () => 12);
  try {
    const service = createAdminPropertyService(createAdminPropertyApi(client));
    await assert.rejects(service.getProperty(14));
    assert.equal(failures.length, 1);
    assert.equal(failures[0].version, 12);
    status = 403;
    await assert.rejects(service.getProperty(14));
    assert.equal(failures.length, 1);
    for (code of ['ACCOUNT_BLOCKED', 'ACCOUNT_INACTIVE']) {
      await assert.rejects(service.getProperty(14), (error) => parseAdminPropertyError(error).code === code);
    }
    assert.equal(failures.length, 3);
  } finally { cleanup(); }
});

test('closing a detail request can abort the real transport without losing cancellation identity', async () => {
  const controller = new AbortController();
  controller.abort();
  await assert.rejects(createAdminPropertyApi(axios.create()).getProperty(14, { signal: controller.signal }),
    (error) => parseAdminPropertyError(error).isCancelled);
});
