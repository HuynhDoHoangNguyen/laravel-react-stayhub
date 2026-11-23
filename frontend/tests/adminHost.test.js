import test, { mock } from 'node:test';
import assert from 'node:assert/strict';
import axios, { AxiosError, CanceledError } from 'axios';
import { createAdminHostApi } from '../src/api/adminHostApi.js';
import { mapAdminHosts, mapAdminHostDetail } from '../src/mappers/adminHostMapper.js';
import { subscribeToAuthFailures } from '../src/services/authSession.js';

let adapter;
const sharedClient = axios.create({ adapter: (config) => adapter(config) });
mock.module(new URL('../src/api/axios.js', import.meta.url).href, { defaultExport: sharedClient });
const { getAdminHosts, getAdminHost, normalizeHostId, createAdminHostService, normalizeHostQuery, readHostSearch,
  hostSearchParams, parseAdminHostError } = await import('../src/services/adminHostService.js');
test.after(() => mock.restoreAll());

const body = () => ({
  data: [{ id: 14, name: 'Nguyễn An', email: 'host@example.test', phone: '0901234567', properties_count: 0, status: 'ACTIVE', created_at: '2026-11-22T07:00:00.000000Z' }],
  links: { first: '/api/admin/hosts?page=1', last: '/api/admin/hosts?page=1', prev: null, next: null },
  meta: { current_page: 1, last_page: 1, per_page: 10, total: 1 },
});

test('defaults, trim and URL roundtrip preserve applied filters for reload and Back/Forward', () => {
  assert.deepEqual(normalizeHostQuery(), { q: '', status: '', page: 1, per_page: 10 });
  const query = { q: ' Nguyễn + %_! ', status: 'BLOCKED', page: '2', per_page: '20' };
  const params = hostSearchParams(query);
  assert.deepEqual(readHostSearch(params.toString()), { q: 'Nguyễn + %_!', status: 'BLOCKED', page: 2, per_page: 20 });
  assert.equal(hostSearchParams({ q: '  ' }).toString(), '');
  assert.deepEqual(readHostSearch('page=1&per_page=50'), { q: '', status: '', page: 1, per_page: 50 });
});

test('invalid query types and unsupported fields fail before transport', async () => {
  const cases = [
    [{ q: ['a'] }, 'q'], [{ q: 'á'.repeat(101) }, 'q'], [{ status: ['ACTIVE'] }, 'status'],
    [{ status: 'HOST' }, 'status'], [{ status: null }, 'status'], [{ page: true }, 'page'],
    [{ page: ['1'] }, 'page'], [{ page: '01' }, 'page'], [{ page: 0 }, 'page'], [{ page: -1 }, 'page'],
    [{ page: '1e2' }, 'page'], [{ page: 1.5 }, 'page'], [{ page: 2147483648 }, 'page'],
    [{ per_page: 11 }, 'per_page'], [{ per_page: ['10'] }, 'per_page'], [{ per_page: '010' }, 'per_page'],
    [{ role: 'ADMIN' }, 'role'], [{ sort: 'email' }, 'sort'],
  ];
  let calls = 0;
  const service = createAdminHostService({ getHosts: async () => { calls++; return body(); } });
  for (const [query, field] of cases) {
    await assert.rejects(service.getHosts(query), (error) => error.isLocalValidation && error.fields[field].length > 0);
  }
  for (const query of [null, [], 'q=abc']) await assert.rejects(service.getHosts(query));
  assert.equal(calls, 0);
});

test('ambiguous and unknown URL queries show validation, including prototype-shaped names', () => {
  for (const search of ['page=1&page=2', 'q=a&q=b', 'role=HOST', 'q[]=a', '__proto__=x', 'constructor=x']) {
    assert.throws(() => readHostSearch(search), (error) => error.isLocalValidation && Object.keys(error.fields).length > 0);
  }
});

test('public service uses shared GET transport, normalized params, signal and output allowlist', async () => {
  const calls = [];
  const value = body();
  value.data[0].password = 'must be discarded';
  value.meta.internal = 'discard';
  adapter = async (config) => { calls.push(config); return { status: 200, data: value, config, headers: {} }; };
  const signal = new AbortController().signal;
  const result = await getAdminHosts({ q: ' Nguyễn ', status: 'ACTIVE' }, { signal });
  assert.equal(calls[0].url, '/api/admin/hosts');
  assert.equal(calls[0].method, 'get');
  assert.equal(calls[0].signal, signal);
  assert.deepEqual(calls[0].params, { page: 1, per_page: 10, q: 'Nguyễn', status: 'ACTIVE' });
  assert.equal(result.data[0].phone, '0901234567');
  assert.deepEqual(Object.keys(result.data[0]), ['id', 'name', 'email', 'phone', 'properties_count', 'status', 'created_at']);
  assert.equal(result.meta.internal, undefined);
  assert.equal(value.data[0].password, 'must be discarded');
});

test('empty default filters are omitted from the HTTP query', async () => {
  let params;
  const service = createAdminHostService({ getHosts: async (query) => { params = query; return body(); } });
  await service.getHosts();
  assert.deepEqual(params, { page: 1, per_page: 10 });
});

test('mapper preserves nullable fields, inactive/blocked status and real empty pages', () => {
  const value = body();
  value.data[0].phone = null; value.data[0].created_at = null; value.data[0].status = 'BLOCKED';
  assert.deepEqual(mapAdminHosts(value).data[0], value.data[0]);
  value.data[0].status = 'INACTIVE';
  assert.equal(mapAdminHosts(value).data[0].status, 'INACTIVE');
  value.data = []; value.meta.total = 0;
  assert.deepEqual(mapAdminHosts(value).data, []);
  value.meta.total = 12; value.meta.last_page = 2; value.meta.current_page = 3;
  assert.equal(mapAdminHosts(value).meta.total, 12);
});

test('malformed success cannot become an empty or partially valid list', () => {
  const changes = [
    (b) => { delete b.data; }, (b) => { b.data = {}; }, (b) => { b.meta = null; },
    (b) => { b.meta.total = '1'; }, (b) => { b.meta.total = -1; }, (b) => { b.meta.current_page = 0; },
    (b) => { b.meta.last_page = 2; }, (b) => { b.meta.per_page = 11; }, (b) => { b.data = []; },
    (b) => { delete b.links.prev; }, (b) => { b.links.next = 17; },
    (b) => { b.data[0].id = '14'; }, (b) => { b.data[0].phone = 901234567; },
    (b) => { b.data[0].properties_count = '0'; }, (b) => { b.data[0].properties_count = -1; },
    (b) => { delete b.data[0].properties_count; },
    (b) => { b.data[0].status = 'DELETED'; }, (b) => { b.data[0].status = ['ACTIVE']; }, (b) => { b.data[0].email = null; },
    (b) => { b.data[0].created_at = 'not-a-date'; }, (b) => { delete b.data[0].phone; },
    (b) => { b.data.push({ ...b.data[0] }); b.meta.total = 2; },
  ];
  for (const change of changes) {
    const value = body(); change(value);
    assert.throws(() => mapAdminHosts(value), (error) => error.kind === 'contract' && error.code === 'INVALID_ADMIN_HOST_RESPONSE');
  }
  for (const value of [null, [], {}]) assert.throws(() => mapAdminHosts(value));
});

test('API preserves rejection identity and Auth distinguishes session/account from role denial', async () => {
  let status = 401, code;
  const failures = [];
  let original;
  const client = axios.create({ adapter: async (config) => {
    original = new AxiosError('Fixture', '', config, null, { status, data: { code }, config, headers: {} });
    throw original;
  } });
  const cleanup = subscribeToAuthFailures(client, (error, version) => failures.push({ error, version }), () => 9);
  try {
    const service = createAdminHostService(createAdminHostApi(client));
    await assert.rejects(service.getHosts(), (error) => error === original);
    assert.equal(failures.length, 1);
    assert.equal(failures[0].version, 9);
    status = 403;
    await assert.rejects(service.getHosts());
    assert.equal(failures.length, 1);
    for (code of ['ACCOUNT_BLOCKED', 'ACCOUNT_INACTIVE']) {
      await assert.rejects(service.getHosts(), (error) => parseAdminHostError(error).code === code);
    }
    assert.equal(failures.length, 3);
  } finally { cleanup(); }
});

test('validation, server, network, timeout and cancellation errors remain distinct', async () => {
  const errors = [
    new AxiosError('Fixture', '', {}, null, { status: 422, data: { errors: { q: ['Từ khóa sai.'] } }, headers: {} }),
    new AxiosError('Private server detail', '', {}, null, { status: 500, data: {}, headers: {} }),
    new AxiosError('Offline', 'ERR_NETWORK'), new AxiosError('Timeout', 'ECONNABORTED'), new CanceledError(),
  ];
  const kinds = ['http', 'http', 'network', 'timeout', 'cancelled'];
  for (const [index, error] of errors.entries()) {
    const service = createAdminHostService({ getHosts: async () => { throw error; } });
    await assert.rejects(service.getHosts(), (failure) => failure === error);
    assert.equal(parseAdminHostError(error).kind, kinds[index]);
  }
  assert.deepEqual(parseAdminHostError(errors[0]).fields, { q: ['Từ khóa sai.'] });
  assert.equal(parseAdminHostError(errors[1]).message.includes('Private'), false);
});

test('aborting the real transport cancels the request and keeps cancellation recognizable', async () => {
  const controller = new AbortController();
  controller.abort();
  await assert.rejects(createAdminHostApi(axios.create()).getHosts({}, { signal: controller.signal }),
    (error) => parseAdminHostError(error).isCancelled);
});


const detailBody = () => ({ data: { ...body().data[0], properties_count: 4, address: 'Địa chỉ fixture', gender: 'FEMALE',
  date_of_birth: '2000-02-29', updated_at: '2026-11-22T08:15:00.123456Z' } });

test('detail service uses the selected ID, shared transport and signal without list query', async () => {
  let call;
  adapter = async (config) => { call = config; return { status: 200, data: detailBody(), config, headers: {} }; };
  const signal = new AbortController().signal;
  const detail = await getAdminHost('14', { signal });
  assert.equal(call.method, 'get'); assert.equal(call.url, '/api/admin/hosts/14');
  assert.equal(call.params, undefined); assert.equal(call.signal, signal);
  assert.equal(detail.phone, '0901234567'); assert.equal(detail.date_of_birth, '2000-02-29');
});

test('invalid detail IDs fail before transport', async () => {
  let calls = 0;
  const service = createAdminHostService({ getHost: async () => { calls++; return detailBody(); } });
  for (const id of [0, -1, 1.5, true, false, null, undefined, [], ['14'], {}, '01', '+14', ' 14 ', '1e2', '../14', 9007199254740992]) {
    await assert.rejects(service.getHost(id), (error) => error.isLocalValidation && error.fields.host.length > 0);
  }
  assert.equal(calls, 0); assert.equal(normalizeHostId('14'), 14);
});

test('detail mapper allowlists fields and preserves nullable values and all genders/statuses', () => {
  const value = detailBody();
  value.data.password = 'discard'; value.data.avatar = 'private-path'; value.data.role = 'CUSTOMER';
  const result = mapAdminHostDetail(value, 14);
  assert.deepEqual(Object.keys(result), ['id', 'name', 'email', 'phone', 'properties_count', 'address', 'gender', 'date_of_birth', 'status', 'created_at', 'updated_at']);
  assert.equal(value.data.password, 'discard');
  for (const gender of [null, 'MALE', 'FEMALE', 'OTHER']) {
    for (const status of ['ACTIVE', 'INACTIVE', 'BLOCKED']) {
      assert.equal(mapAdminHostDetail({ data: { ...result, gender, status } }, 14).gender, gender);
    }
  }
  for (const field of ['phone', 'address', 'gender', 'date_of_birth', 'created_at', 'updated_at']) value.data[field] = null;
  const nullable = mapAdminHostDetail(value, 14);
  for (const field of ['phone', 'address', 'gender', 'date_of_birth', 'created_at', 'updated_at']) assert.equal(nullable[field], null);
});

test('detail mapper rejects wrong host and malformed fields instead of showing another account', () => {
  const changes = [
    (b) => { b.data.properties_count = '4'; }, (b) => { b.data.properties_count = -1; },
    (b) => { b.data.id = 15; }, (b) => { b.data.id = '14'; }, (b) => { b.data.name = null; },
    (b) => { b.data.email = null; }, (b) => { b.data.phone = 901234567; }, (b) => { b.data.address = {}; },
    (b) => { b.data.gender = 'UNKNOWN'; }, (b) => { b.data.gender = ['MALE']; },
    (b) => { b.data.status = 'DELETED'; }, (b) => { b.data.date_of_birth = '2026-02-29'; },
    (b) => { b.data.date_of_birth = '2000-02-30'; }, (b) => { b.data.date_of_birth = '0000-01-01'; },
    (b) => { b.data.created_at = '2026-02-30T00:00:00Z'; }, (b) => { b.data.updated_at = 'invalid'; },
  ];
  for (const change of changes) {
    const value = detailBody(); change(value);
    assert.throws(() => mapAdminHostDetail(value, 14), (error) => error.kind === 'contract');
  }
  for (const field of Object.keys(detailBody().data)) {
    const value = detailBody(); delete value.data[field];
    assert.throws(() => mapAdminHostDetail(value, 14));
  }
  for (const value of [null, {}, { data: [] }]) assert.throws(() => mapAdminHostDetail(value, 14));
});

test('detail errors keep identity and distinct HTTP/network/cancellation states without replay', async () => {
  for (const status of [401, 403, 404, 422, 500]) {
    const original = new AxiosError('Fixture', '', {}, null, { status, data: { message: 'Fixture denied' }, headers: {} });
    let calls = 0;
    const service = createAdminHostService({ getHost: async () => { calls++; throw original; } });
    await assert.rejects(service.getHost(14), (error) => error === original);
    assert.equal(calls, 1); assert.equal(parseAdminHostError(original).status, status);
  }
  const controller = new AbortController(); controller.abort();
  await assert.rejects(createAdminHostApi(axios.create()).getHost(14, { signal: controller.signal }),
    (error) => parseAdminHostError(error).isCancelled);
});
