import test from 'node:test';
import assert from 'node:assert/strict';
import { AxiosError, CanceledError } from 'axios';
import { normalizeApiError, localValidationError } from '../src/api/apiError.js';

function http(status, data = {}, headers = {}) {
  return new AxiosError('private transport details', '', { password: 'secret' }, {}, { status, data, headers });
}

test('HTTP status, safe codes and validation fields survive old/new response envelopes', () => {
  for (const status of [401, 403, 404, 409, 419, 422, 429]) {
    const result = normalizeApiError(http(status, { message: 'Public message', code: 'DOMAIN_REJECTED', errors: { rating: ['Invalid'] } }));
    assert.equal(result.status, status);
    assert.equal(result.message, 'Public message');
    assert.equal(result.code, 'DOMAIN_REJECTED');
    assert.equal(result.kind, 'http');
    assert.deepEqual(result.fields, status === 422 ? { rating: ['Invalid'] } : {});
    assert.equal(result.isNotFound, status === 404);
    assert.equal(result.isConflict, status === 409);
    assert.equal(result.isValidation, status === 422);
    assert.equal(result.isNetworkError, false);
    assert.equal(normalizeApiError(result), result);
    assert.equal('config' in result, false);
    assert.equal('request' in result, false);
  }
  assert.equal(normalizeApiError(http(409, { message: 'Legacy conflict' })).message, 'Legacy conflict');
  assert.equal(normalizeApiError(http(500, { message: 'SQL password secret' })).message.includes('secret'), false);
});

test('malformed errors do not leak transport messages or crash normalization', () => {
  for (const data of [null, 'HTML response', [], { message: {}, code: [], errors: [] }]) {
    const result = normalizeApiError(http(422, data));
    assert.equal(typeof result.message, 'string');
    assert.equal(result.code, null);
    assert.deepEqual(result.fields, {});
  }
  const result = normalizeApiError(http(422, { errors: JSON.parse('{"__proto__":["bad"],"rating":["Invalid"],"comment":"bad shape"}') }));
  assert.deepEqual(result.fields, { rating: ['Invalid'] });
  assert.equal(normalizeApiError(new Error('private error')).message.includes('private'), false);
});

test('cancellation, timeout, offline and local validation are distinct', () => {
  for (const error of [new CanceledError(), { name: 'AbortError' }, { code: 'ERR_CANCELED' }]) {
    const result = normalizeApiError(error);
    assert.equal(result.kind, 'cancelled');
    assert.equal(result.isCancelled, true);
    assert.equal(result.isNetworkError, false);
  }
  for (const code of ['ECONNABORTED', 'ETIMEDOUT']) {
    const result = normalizeApiError(new AxiosError('private', code));
    assert.equal(result.kind, 'timeout');
    assert.equal(result.isTimeout, true);
    assert.equal(result.isNetworkError, false);
  }
  assert.equal(normalizeApiError(new AxiosError('offline', 'ERR_NETWORK')).kind, 'network');
  const local = normalizeApiError(localValidationError('Điểm không hợp lệ.', { rating: ['Invalid'] }));
  assert.equal(local.kind, 'validation');
  assert.equal(local.status, 422);
  assert.equal(local.isNetworkError, false);
  assert.equal(local.message, 'Điểm không hợp lệ.');
  assert.deepEqual(local.fields, { rating: ['Invalid'] });
});

test('Retry-After supports seconds/date and rejects malformed values without retrying', () => {
  const now = Date.parse('2026-11-14T00:00:00Z');
  for (const [header, seconds] of [['30', 30], [0, 0], ['Sat, 14 Nov 2026 00:00:45 GMT', 45], ['Fri, 13 Nov 2026 00:00:00 GMT', 0], ['invalid', 0], ['-1', 0], ['1.5', 0], [undefined, 0]]) {
    assert.equal(normalizeApiError(http(429, {}, { 'retry-after': header }), { now }).retryAfter, seconds);
  }
});
