import test, { mock } from 'node:test';
import assert from 'node:assert/strict';
import axios, { AxiosError, CanceledError } from 'axios';
import { createAccountStatusApi } from '../src/api/accountStatusApi.js';
import { mapAccountStatus } from '../src/mappers/accountStatusMapper.js';
import { subscribeToAuthFailures } from '../src/services/authSession.js';

let adapter;
const client = axios.create({ adapter: (config) => adapter(config) });
mock.module(new URL('../src/api/axios.js', import.meta.url).href, { defaultExport: client });
const { updateAccountStatus, createAccountStatusService, normalizeAccountStatusInput, parseAccountStatusError }
  = await import('../src/services/accountStatusService.js');
test.after(() => mock.restoreAll());
const payload = { status: 'BLOCKED', expected_status: 'ACTIVE' };
const body = () => ({ data: { id: 12, status: 'BLOCKED', updated_at: '2026-11-22T03:00:00.000000Z', changed: true } });
const response = (config, data = body(), status = 200) => ({ status, data, headers: {}, config });

test('shared transport gets CSRF before one PATCH with exact payload, signal and timeout', async () => {
  const calls = [];
  adapter = async (config) => { calls.push(config); return response(config, config.method === 'get' ? null : body()); };
  const signal = new AbortController().signal;
  const result = await updateAccountStatus('12', payload, { signal });
  assert.deepEqual(calls.map((config) => [config.method, config.url]), [
    ['get', '/sanctum/csrf-cookie'], ['patch', '/api/admin/users/12/status'],
  ]);
  assert.deepEqual(JSON.parse(calls[1].data), payload);
  for (const config of calls) { assert.equal(config.signal, signal); assert.equal(config.timeout, 10000); }
  assert.deepEqual(result, body().data);
});

test('bad IDs and payloads fail before CSRF or mutation transport', async () => {
  let calls = 0;
  const service = createAccountStatusService({ update: async () => { calls++; return response({}); } });
  for (const id of [0, -1, '01', '1e2', ' 12', '12 ', true, null, [12], Number.MAX_SAFE_INTEGER + 1]) {
    await assert.rejects(service.update(id, payload), (error) => error.isLocalValidation && Boolean(error.fields.user));
  }
  for (const values of [null, [], {}, { status: 'BLOCKED' }, { ...payload, status: 'INACTIVE' },
    { ...payload, expected_status: true }, { ...payload, role: 'ADMIN' }, { ...payload, actor_id: 1 }]) {
    await assert.rejects(service.update(12, values), (error) => error.isLocalValidation);
  }
  assert.equal(calls, 0);
  assert.deepEqual(normalizeAccountStatusInput(12, payload), { id: 12, payload });
});

test('CSRF failure preserves rejection and never attempts PATCH or claims an unknown mutation', async () => {
  const failure = new AxiosError('csrf offline', 'ERR_NETWORK');
  let patches = 0;
  const api = createAccountStatusApi({ get: async () => { throw failure; }, patch: async () => { patches++; } });
  await assert.rejects(api.update(12, payload), (error) => error === failure);
  assert.equal(patches, 0);
  assert.equal(parseAccountStatusError(failure).outcomeUnknown, false);
});

test('timeout, network failure and 5xx after PATCH are unknown and never automatically replayed', async () => {
  for (const failure of [new AxiosError('timeout', 'ECONNABORTED'), new AxiosError('offline', 'ERR_NETWORK'),
    new AxiosError('server', 'ERR_BAD_RESPONSE', {}, {}, { status: 500, data: {}, headers: {} })]) {
    let csrf = 0;
    let patches = 0;
    const api = createAccountStatusApi({ get: async () => { csrf++; }, patch: async () => { patches++; throw failure; } });
    await assert.rejects(createAccountStatusService(api).update(12, payload), (error) => error === failure);
    assert.equal(csrf, 1); assert.equal(patches, 1);
    assert.equal(parseAccountStatusError(failure).outcomeUnknown, true);
  }
});

test('known HTTP rejections retain identity, status, fields and do not become unknown outcomes', async () => {
  for (const status of [401, 403, 404, 409, 419, 422]) {
    const failure = new AxiosError('rejected', 'ERR_BAD_REQUEST', {}, {}, {
      status, data: { message: 'Thông báo từ máy chủ', code: status === 409 ? 'ACCOUNT_STATUS_CONFLICT' : undefined,
        errors: { expected_status: ['Trạng thái không hợp lệ.'] } }, headers: {},
    });
    const api = createAccountStatusApi({ get: async () => null, patch: async () => { throw failure; } });
    await assert.rejects(createAccountStatusService(api).update(12, payload), (error) => error === failure);
    const parsed = parseAccountStatusError(failure);
    assert.equal(parsed.status, status);
    assert.equal(parsed.outcomeUnknown, false);
    if (status === 422) assert.deepEqual(parsed.fields, { expected_status: ['Trạng thái không hợp lệ.'] });
    if (status === 409) assert.equal(parsed.code, 'ACCOUNT_STATUS_CONFLICT');
    if (status === 419) assert.match(parsed.message, /Phiên bảo mật đã hết hạn/);
    assert.equal(parsed.config, undefined); assert.equal(parsed.request, undefined);
  }
});

test('malformed successful responses require reconciliation instead of claiming success', async () => {
  const changes = [
    (b) => { delete b.data; }, (b) => { b.data = []; }, (b) => { b.data.id = 13; },
    (b) => { b.data.id = '12'; }, (b) => { b.data.status = 'ACTIVE'; }, (b) => { b.data.changed = false; },
    (b) => { b.data.changed = 1; }, (b) => { delete b.data.updated_at; }, (b) => { b.data.updated_at = null; },
    (b) => { b.data.updated_at = '2026-02-30T03:00:00Z'; }, (b) => { b.data.updated_at = 'invalid'; },
  ];
  for (const change of changes) {
    const value = body(); change(value);
    let calls = 0;
    const service = createAccountStatusService({ update: async () => { calls++; return response({}, value); } });
    await assert.rejects(service.update(12, payload), (error) => error.outcomeUnknown === true);
    assert.equal(calls, 1);
  }
  await assert.rejects(createAccountStatusService({ update: async () => response({}, body(), 201) }).update(12, payload),
    (error) => error.outcomeUnknown === true);
});

test('mapper preserves nullable noop timestamp and strips fields outside the command result', () => {
  const value = { data: { id: 12, status: 'ACTIVE', updated_at: null, changed: false, role: 'ADMIN', password: 'discard' } };
  assert.deepEqual(mapAccountStatus(value, 12, { status: 'ACTIVE', expected_status: 'ACTIVE' }), {
    id: 12, status: 'ACTIVE', updated_at: null, changed: false,
  });
  assert.equal(value.data.role, 'ADMIN');
});

test('unblock uses the same endpoint and opposite expected status', async () => {
  let actual;
  const service = createAccountStatusService({ update: async (id, values) => {
    actual = { id, values };
    return response({}, { data: { ...body().data, status: 'ACTIVE' } });
  } });
  const values = { status: 'ACTIVE', expected_status: 'BLOCKED' };
  assert.equal((await service.update(12, values)).status, 'ACTIVE');
  assert.deepEqual(actual, { id: 12, values });
});

test('cancellation identity survives and is unknown only when PATCH was attempted', async () => {
  for (const stage of ['get', 'patch']) {
    const failure = new CanceledError();
    const api = createAccountStatusApi({ get: async () => { if (stage === 'get') throw failure; }, patch: async () => { throw failure; } });
    await assert.rejects(createAccountStatusService(api).update(12, payload), (error) => error === failure);
    assert.equal(parseAccountStatusError(failure).isCancelled, true);
    assert.equal(parseAccountStatusError(failure).outcomeUnknown, stage === 'patch');
  }
});

test('only actor account errors and 401 invalidate Auth; domain conflicts do not', async () => {
  let invalidations = 0;
  const unsubscribe = subscribeToAuthFailures(client, () => { invalidations++; }, () => 1);
  try {
    for (const [status, code, expected] of [[409, 'ACCOUNT_STATUS_CONFLICT', 0], [403, undefined, 0],
      [403, 'ACCOUNT_BLOCKED', 1], [403, 'ACCOUNT_INACTIVE', 2], [401, undefined, 3]]) {
      adapter = async (config) => {
        if (config.method === 'get') return response(config, null);
        throw new AxiosError('denied', 'ERR_BAD_REQUEST', config, {}, { status, data: { code }, headers: {}, config });
      };
      await assert.rejects(updateAccountStatus(12, payload));
      assert.equal(invalidations, expected);
    }
  } finally { unsubscribe(); }
});
