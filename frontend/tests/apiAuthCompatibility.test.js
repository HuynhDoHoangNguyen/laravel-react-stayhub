import test from 'node:test';
import assert from 'node:assert/strict';
import axios, { AxiosError } from 'axios';
import { createInvoiceApi } from '../src/api/invoiceApi.js';
import { createReviewApi } from '../src/api/reviewApi.js';
import { subscribeToAuthFailures } from '../src/services/authSession.js';
import { normalizeApiError } from '../src/api/apiError.js';

test('domain API rejections pass through existing Auth interceptors with original identity/version', async () => {
  let status = 401, code;
  let original;
  const calls = [], failures = [];
  const client = axios.create({ adapter: async (config) => {
    calls.push(config);
    original = new AxiosError('Fixture', '', config, null, { status, data: { message: 'Denied', code }, headers: {}, config });
    throw original;
  } });
  let version = 3;
  const cleanup = subscribeToAuthFailures(client, (error, capturedVersion) => failures.push({ error, capturedVersion }), () => version);
  const invoice = createInvoiceApi(client), review = createReviewApi(client);
  await assert.rejects(invoice.getHostInvoices({}), (error) => error === original);
  assert.equal(failures.length, 1); assert.equal(failures[0].capturedVersion, 3);
  version = 4;
  await assert.rejects(review.getPublicPropertyReviews(1, {}));
  assert.equal(failures.length, 1);
  for (const accountCode of ['ACCOUNT_BLOCKED', 'ACCOUNT_INACTIVE']) {
    status = 403; code = accountCode;
    await assert.rejects(review.getCustomerReviews({}), (error) => {
      assert.equal(error, original);
      assert.equal(normalizeApiError(error).code, accountCode);
      return true;
    });
    assert.equal(failures.at(-1).error.code, accountCode);
    assert.equal(failures.at(-1).capturedVersion, 4);
  }
  status = 403; code = 'ROLE_DENIED';
  await assert.rejects(invoice.createHostInvoice(1));
  assert.equal(failures.length, 3);
  cleanup();
  status = 401;
  await assert.rejects(invoice.getHostInvoices({}));
  assert.equal(failures.length, 3);
  assert.equal(calls.at(-1).stayhubAuthVersion, undefined);
  // Re-subscription after cleanup installs exactly one pair, not accumulated callbacks.
  const cleanupAgain = subscribeToAuthFailures(client, () => failures.push({}), () => version);
  await assert.rejects(invoice.getHostInvoices({}));
  assert.equal(failures.length, 4);
  cleanupAgain();
  assert.equal(calls.length, 7);
});

test('late failure retains request snapshot instead of substituting current Auth version', async () => {
  let release, version = 11;
  const captured = [];
  const client = axios.create({ adapter: (config) => new Promise((resolve, reject) => {
    release = () => reject(new AxiosError('Fixture', '', config, null, { status: 401, data: {}, headers: {}, config }));
  }) });
  const cleanup = subscribeToAuthFailures(client, (error, snapshot) => captured.push(snapshot), () => version);
  const pending = createInvoiceApi(client).getHostInvoices({});
  // Let Axios enter adapter before identity changes.
  await new Promise((resolve) => setImmediate(resolve));
  version = 12; release();
  await assert.rejects(pending);
  assert.deepEqual(captured, [11]);
  cleanup();
});
