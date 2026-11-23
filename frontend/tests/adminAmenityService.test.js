import test, { mock } from 'node:test';
import assert from 'node:assert/strict';
import { AxiosError } from 'axios';
import { getAuthDestination } from '../src/utils/authNavigation.js';
import { createAdminAmenityApi } from '../src/api/adminAmenityApi.js';
import { mapAdminAmenity, mapAdminAmenities } from '../src/mappers/adminAmenityMapper.js';

mock.module(new URL('../src/api/axios.js', import.meta.url).href, { defaultExport: {} });
const { createAdminAmenityService, parseAdminAmenityError, readAmenitySearch, amenitySearchParams } =
  await import('../src/services/adminAmenityService.js');
test.after(() => mock.restoreAll());

const version = 'a'.repeat(64);
const row = () => ({
  id: 17, name: 'Hồ bơi', icon: null, description: null, status: false, rooms_count: 2,
  created_at: '2026-11-23T00:00:00.000000Z', updated_at: null, version,
});
const detail = () => ({ data: row() });
const listing = () => ({ data: [row()], links: { first: '/?page=1', last: '/?page=1', next: null, prev: null },
  meta: { current_page: 1, last_page: 1, per_page: 10, total: 1 } });

test('query roundtrip supports Vietnamese literal search, status and pagination', async () => {
  const query = { q: 'Hồ %_!', status: 'inactive', page: 2, per_page: 20 };
  assert.deepEqual(readAmenitySearch(amenitySearchParams(query)), query);
  assert.equal(amenitySearchParams({}).toString(), '');
  for (const value of ['q=x&q=y', 'page=01', 'status=1', 'status[]=active', 'wrong=1']) {
    assert.throws(() => readAmenitySearch(value), (error) => error.isLocalValidation === true);
  }
  let call;
  const adapter = createAdminAmenityApi({ get: async (...args) => {
    call = args;
    return { data: listing() };
  } });
  const signal = new AbortController().signal;
  const service = createAdminAmenityService(adapter);
  assert.deepEqual((await service.list({ q: ' Hồ %_! ', status: 'inactive' }, { signal })).data, [row()]);
  assert.deepEqual(call, ['/api/admin/amenities', { params: { q: 'Hồ %_!', status: 'inactive', page: 1, per_page: 10 }, signal }]);
});

test('projections preserve false, nullable fields and counts and reject malformed success', () => {
  assert.deepEqual(mapAdminAmenity(detail(), 17), row());
  assert.deepEqual(mapAdminAmenity({ data: { ...row(), private: 'omit' } }), row());
  assert.throws(() => mapAdminAmenity(detail(), 18), /Dữ liệu tiện nghi/);
  for (const invalid of [{ status: 0 }, { rooms_count: '2' }, { version: '17' }, { id: 0 }, { icon: undefined }]) {
    assert.throws(() => mapAdminAmenity({ data: { ...row(), ...invalid } }), /Dữ liệu tiện nghi/);
  }
  const body = listing();
  assert.throws(() => mapAdminAmenities({ ...body, data: [] }), /Dữ liệu tiện nghi/);
  assert.deepEqual(mapAdminAmenities({ ...body, data: [], meta: { ...body.meta, total: 0 } }).data, []);
});

test('writes send one request after CSRF and preserve the version and strict boolean', async () => {
  const calls = [];
  const client = {
    get: async (...args) => { calls.push(['get', ...args]); },
    post: async (...args) => { calls.push(['post', ...args]); return { status: 201, data: { data: { ...row(), status: true } } }; },
    put: async (...args) => { calls.push(['put', ...args]); return { status: 200, data: detail() }; },
  };
  const service = createAdminAmenityService(createAdminAmenityApi(client));
  const signal = new AbortController().signal;
  await service.create({ name: ' Hồ bơi ', description: ' ' }, { signal });
  await service.update('17', { name: 'Hồ bơi', icon: null, description: null, status: false, version }, { signal });
  assert.deepEqual(calls, [
    ['get', '/sanctum/csrf-cookie', { signal }],
    ['post', '/api/admin/amenities', { name: 'Hồ bơi', description: null, icon: null, status: true }, { signal }],
    ['get', '/sanctum/csrf-cookie', { signal }],
    ['put', '/api/admin/amenities/17', { name: 'Hồ bơi', description: null, icon: null, status: false, version }, { signal }],
  ]);
  await assert.rejects(service.create({ name: 'Hồ bơi', status: 'false' }), (e) => e.isLocalValidation);
  await assert.rejects(service.update('017', {}), (e) => e.fields.amenity.length === 1);
  assert.equal(calls.length, 4);
});

test('known conflicts preserve error identity and failed CSRF never sends the mutation', async () => {
  const stale = new AxiosError('stale', '', {}, undefined, { status: 409, data: { code: 'ADMIN_AMENITY_STALE' } });
  const network = new AxiosError('private transport message', 'ERR_NETWORK');
  let writes = 0;
  let csrfError = network;
  const transport = createAdminAmenityApi({
    get: async () => { if (csrfError) throw csrfError; },
    put: async () => { writes++; throw stale; },
  });
  const service = createAdminAmenityService(transport);
  const values = { name: 'Hồ bơi', icon: null, description: null, status: false, version };
  await assert.rejects(service.update(17, values), (failure) => failure === network && !parseAdminAmenityError(failure).outcomeUnknown);
  assert.equal(writes, 0);
  csrfError = null;
  await assert.rejects(service.update(17, values), (failure) => failure === stale && parseAdminAmenityError(failure).code === 'ADMIN_AMENITY_STALE');
  assert.equal(writes, 1);
});

test('lost or invalid mutation responses are uncertain and are never replayed', async () => {
  const lost = new AxiosError('not shown', 'ECONNABORTED');
  const server = new AxiosError('not shown', '', {}, undefined, { status: 500, data: {} });
  let calls = 0;
  const errors = [lost, server, null];
  const transport = createAdminAmenityApi({
    get: async () => {},
    post: async () => {
      const failure = errors[calls++];
      if (failure) throw failure;
      return { status: 201, data: { data: { ...row(), status: 'true' } } };
    },
  });
  const service = createAdminAmenityService(transport);
  for (let n = 1; n <= 3; n++) {
    await assert.rejects(service.create({ name: 'Hồ bơi' }), (error) => {
      const parsed = parseAdminAmenityError(error);
      return parsed.outcomeUnknown && !parsed.message.includes('not shown');
    });
    assert.equal(calls, n);
  }
});

test('only Admin can return to the amenity page after login', () => {
  const path = '/admin/amenities?status=inactive&q=H%E1%BB%93';
  assert.equal(getAuthDestination('ADMIN', path), path);
  assert.equal(getAuthDestination('HOST', path), '/host');
  assert.equal(getAuthDestination('CUSTOMER', path), '/customer');
  assert.equal(getAuthDestination('ADMIN', '/admin/amenities/unknown'), '/admin');
});
