import test from 'node:test';
import assert from 'node:assert/strict';
import axios, { AxiosError, CanceledError } from 'axios';
import { requireApiId } from '../src/api/apiId.js';
import { createInvoiceApi } from '../src/api/invoiceApi.js';
import { createReviewApi } from '../src/api/reviewApi.js';

test('canonical IDs reject coercion and invalid inputs issue no requests', async () => {
  let calls = 0;
  const client = axios.create({ adapter: async () => { ++calls; throw new Error('Unexpected request'); } });
  const invoice = createInvoiceApi(client), review = createReviewApi(client);
  for (const value of [1, '1', Number.MAX_SAFE_INTEGER]) assert.equal(requireApiId(value), Number(value));
  for (const value of [0, -1, 1.9, '1.9', '1e2', '01', '', ' 1 ', true, false, null, undefined, NaN, Infinity, Number.MAX_SAFE_INTEGER + 1, '9007199254740992', {}, []]) {
    for (const invoke of [() => invoice.createHostInvoice(value), () => invoice.getHostInvoiceDetail(value), () => invoice.getCustomerInvoiceDetail(value), () => review.getPublicPropertyReviews(value), () => review.createBookingReview(value, {})]) {
      await assert.rejects(invoke, (error) => error.isLocalValidation === true && error.status === 400);
    }
  }
  assert.equal(calls, 0);
});

test('all nine endpoint operations preserve verb, path, params, body, signal and envelope', async () => {
  const calls = [];
  const envelope = { data: [{ id: 7, total_amount: '1234567.89', paid_at: null }], meta: { total: 21 }, links: { next: '?page=2' }, summary: { total_reviews: 21 } };
  const client = axios.create({ adapter: async (config) => { calls.push(config); return { status: 200, data: envelope, headers: {}, config }; } });
  const invoice = createInvoiceApi(client), review = createReviewApi(client);
  const options = { signal: new AbortController().signal };
  const params = { page: 2, per_page: 10, status: 'PAID', search: 'abc' };
  const payload = { rating: 5, comment: 'Kỳ nghỉ tuyệt vời' };
  const cases = [
    [() => invoice.createHostInvoice('7', options), 'post', '/api/host/bookings/7/invoice'],
    [() => invoice.getHostInvoices(params, options), 'get', '/api/host/invoices', params],
    [() => invoice.getHostInvoiceDetail(7, options), 'get', '/api/host/invoices/7'],
    [() => invoice.getCustomerInvoices(params, options), 'get', '/api/customer/invoices', params],
    [() => invoice.getCustomerInvoiceDetail(7, options), 'get', '/api/customer/invoices/7'],
    [() => review.getPublicPropertyReviews(7, params, options), 'get', '/api/public/properties/7/reviews', params],
    [() => review.createBookingReview(7, payload, options), 'post', '/api/customer/bookings/7/review', undefined, payload],
    [() => review.getCustomerReviews(params, options), 'get', '/api/customer/reviews', params],
    [() => review.getHostReviews(params, options), 'get', '/api/host/reviews', params],
  ];
  for (const [invoke, method, path, query, body] of cases) {
    assert.equal(await invoke(), envelope);
    const config = calls.at(-1);
    assert.equal(config.method, method);
    assert.equal(config.url, path);
    assert.deepEqual(config.params, query);
    assert.equal(config.signal, options.signal);
    assert.deepEqual(config.data ? JSON.parse(config.data) : undefined, body);
  }
  assert.equal(calls.length, 9);
});

test('mutations preserve rejection identity and never replay on HTTP or ambiguous network failure', async () => {
  for (const status of [401, 419, 409, 500, 0]) {
    let calls = 0;
    const error = new AxiosError('Fixture failure', status ? '' : 'ERR_NETWORK');
    const client = axios.create({ adapter: async () => { ++calls; throw error; } });
    await assert.rejects(createInvoiceApi(client).createHostInvoice(7), (received) => received === error);
    await assert.rejects(createReviewApi(client).createBookingReview(7, { rating: 5, comment: 'Valid review' }), (received) => received === error);
    assert.equal(calls, 2);
  }
});

test('pre-aborted requests never reach adapter and preserve Axios cancellation', async () => {
  let calls = 0;
  const client = axios.create({ adapter: async () => { ++calls; throw new Error('Unexpected'); } });
  const controller = new AbortController(); controller.abort();
  await assert.rejects(createReviewApi(client).getCustomerReviews({}, { signal: controller.signal }), CanceledError);
  assert.equal(calls, 0);
});
