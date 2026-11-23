import test, { mock } from 'node:test';
import assert from 'node:assert/strict';
import axios, { AxiosError, CanceledError } from 'axios';
import { normalizeApiError } from '../src/api/apiError.js';

let adapter;
const client = axios.create({ adapter: (config) => adapter(config) });
mock.module(new URL('../src/api/axios.js', import.meta.url).href, { defaultExport: client });
const { getAdminProperties, getAdminPropertyVerification, reviewAdminPropertyVerification, downloadAdminPropertyVerification }
  = await import('../src/services/adminPropertyService.js');
test.after(() => mock.restoreAll());

const row = { id: 1, name: 'Nhà Vườn', address: 'Tây Hồ', status: 'PENDING', type: 'HOMESTAY', host_name: 'Chủ cơ sở' };
const links = { first: '?page=1', last: '?page=2', prev: '?page=1', next: null };
const meta = { current_page: 2, last_page: 2, per_page: 10, total: 11, from: 11, to: 11 };

test('list normalizes filters, sends an abort signal and maps only list fields', async () => {
  const { signal } = new AbortController();
  adapter = async (config) => {
    assert.equal(config.url, '/api/admin/properties');
    assert.equal(config.method, 'get');
    assert.equal(config.signal, signal);
    assert.deepEqual(config.params, { search: 'Tây Hồ', status: 'PENDING', page: 2, per_page: 10 });
    return { data: { data: [{ ...row, phone: 'private' }], links, meta } };
  };

  assert.deepEqual(await getAdminProperties({ search: ' Tây Hồ ', status: 'PENDING', page: 2 }, { signal }), {
    data: [row], links, meta,
  });
});

test('blank filters are omitted and an empty page is a valid result', async () => {
  const body = { data: [], links, meta: { ...meta, current_page: 3, from: null, to: null } };
  adapter = async ({ params }) => {
    assert.deepEqual(params, { page: 3, per_page: 10 });
    return { data: body };
  };

  assert.deepEqual(await getAdminProperties({ search: ' ', page: 3 }), body);
});

test('malformed pagination, list fields and codes are rejected', async () => {
  for (const body of [
    { data: [] },
    { data: [row], links, meta: { ...meta, current_page: '2' } },
    ...[{ status: 'VERIFIED' }, { type: 'OTHER' }, { host_name: undefined }]
      .map((change) => ({ data: [{ ...row, ...change }], links, meta })),
  ]) {
    adapter = async () => ({ data: body });
    await assert.rejects(getAdminProperties(), { code: 'INVALID_ADMIN_PROPERTY_RESPONSE' });
  }
});

test('validation and cancellation retain their transport classification', async () => {
  const failure = new AxiosError('Invalid query', undefined, undefined, undefined, {
    status: 422, data: { errors: { status: ['Trạng thái không hợp lệ.'] } },
  });
  adapter = async () => { throw failure; };
  await assert.rejects(getAdminProperties(), (error) => {
    assert.equal(error, failure);
    assert.deepEqual(normalizeApiError(error).fields, { status: ['Trạng thái không hợp lệ.'] });
    return true;
  });
  adapter = async () => { throw new CanceledError(); };
  await assert.rejects(getAdminProperties(), (error) => normalizeApiError(error).isCancelled);
});

const verificationUrl = '/api/admin/properties/1/verification';
const entry = {
  id: 9, status: 'SUBMITTED', address_snapshot: '12 Bạch Đằng', address_current: '12 Bạch Đằng',
  submitted_at: '2026-11-23T05:00:00Z', reviewed_at: null, rejection_reason: null,
};

test('verification reads null or the allowlisted summary and rejects malformed success', async () => {
  let data = null;
  adapter = async ({ url, method }) => {
    assert.equal(url, verificationUrl);
    assert.equal(method, 'get');
    return { data: { data } };
  };
  assert.equal(await getAdminPropertyVerification(1), null);
  data = { ...entry, document_path: 'private.pdf' };
  assert.deepEqual(await getAdminPropertyVerification('1'), entry);
  data = { ...entry, status: 'INVALIDATED', reviewed_at: '2026-11-23T06:00:00Z' };
  assert.deepEqual(await getAdminPropertyVerification(1), data);
  data = { ...entry, status: 'REJECTED', rejection_reason: null };
  await assert.rejects(getAdminPropertyVerification(1), { kind: 'contract' });
});

test('review initializes CSRF and sends the trimmed reason with the same signal', async () => {
  const { signal } = new AbortController();
  const requests = [];
  const result = { ...entry, status: 'REJECTED', reviewed_at: '2026-11-23T06:00:00Z', rejection_reason: 'Thiếu địa chỉ.' };
  adapter = async (config) => {
    assert.equal(config.signal, signal);
    requests.push([config.method, config.url]);
    if (config.method === 'patch') assert.deepEqual(JSON.parse(config.data), { decision: 'REJECT', reason: 'Thiếu địa chỉ.' });
    return { data: { data: result } };
  };
  assert.deepEqual(await reviewAdminPropertyVerification(1, 'REJECT', ' Thiếu địa chỉ. ', { signal }), result);
  assert.deepEqual(requests, [['get', '/sanctum/csrf-cookie'], ['patch', verificationUrl]]);
});

test('download requests a blob, generates an attachment name and rejects an unexpected MIME', async () => {
  let blob = new Blob(['payload'], { type: 'application/pdf' });
  const { signal } = new AbortController();
  adapter = async (config) => {
    assert.equal(config.url, verificationUrl + '/document');
    assert.equal(config.method, 'get');
    assert.equal(config.responseType, 'blob');
    assert.equal(config.signal, signal);
    return { data: blob };
  };
  assert.deepEqual(await downloadAdminPropertyVerification(1, 9, { signal }), { blob, filename: 'ho-so-xac-minh-9.pdf' });
  blob = new Blob(['{"message":"error"}'], { type: 'application/json' });
  await assert.rejects(downloadAdminPropertyVerification(1, 9, { signal }), { kind: 'contract' });
});
