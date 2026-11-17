import test, { mock } from 'node:test';
import assert from 'node:assert/strict';
import axios, { AxiosError, CanceledError } from 'axios';
import { mapReviewCard } from '../src/mappers/reviewMapper.js';

// Mock the Vite-configured transport only. Keep the real API, mapper and service.
let adapter;
const client = axios.create({ adapter: (config) => adapter(config) });
mock.module(new URL('../src/api/axios.js', import.meta.url).href, { defaultExport: client });
const { reviewService, parseReviewError } = await import('../src/services/reviewService.js');
test.after(() => mock.restoreAll());

const customerRow = {
  id: 7, booking_id: 11, booking_code: null, property_id: 2,
  property_name: null, room_name: null, comment: 'Dịch vụ tốt', rating: 4,
  created_at: null, updated_at: null,
};
const publicRow = { id: 7, rating: 4, comment: 'Dịch vụ tốt', author: 'Khách A.', created_at: null };
const breakdown = { 1: 0, 2: 0, 3: 0, 4: 1, 5: 0 };

function page(data, current = 1, last = 1) {
  return {
    data,
    meta: { current_page: current, last_page: last, per_page: 6, total: data.length, path: '/reviews' },
    links: { first: '?page=1', prev: null, next: null, last: '?page=1' },
  };
}

function response(config, data, status = 200) {
  return { config, data, status, headers: {} };
}

test('list operations preserve metadata, query, signal and audience projections', async () => {
  const calls = [];
  const publicPage = {
    ...page([{ ...publicRow, customer_id: 13, booking_code: 'PRIVATE' }]),
    summary: { review_count: 1, average_rating: 4, rating_breakdown: breakdown },
  };
  const host = {
    ...page([{ ...customerRow, customer_name: 'Khách' }], 2, 3),
    summary: { total_reviews: 1, average_rating: 4, rating_breakdown: breakdown },
  };
  const customer = page([customerRow]);
  adapter = async (config) => {
    calls.push(config);
    const body = config.url.includes('/public/') ? publicPage : config.url.includes('/host/') ? host : customer;
    return response(config, body);
  };
  const signal = new AbortController().signal;
  const pub = await reviewService.getPublicPropertyReviews('2', { perPage: 6, rating: '4', sortBy: 'rating_asc' }, { signal });
  assert.deepEqual(pub, {
    ...publicPage, data: [publicRow], pagination: { currentPage: 1, lastPage: 1, perPage: 6, total: 1 },
  });
  const own = await reviewService.getCustomerReviews({ perPage: 6 }, { signal });
  assert.deepEqual(own.data, [customerRow]);
  const managed = await reviewService.getHostReviews({ propertyId: '2', search: '  Suite ', page: 2, perPage: 6 }, { signal });
  assert.deepEqual(managed, { ...host, pagination: { currentPage: 2, lastPage: 3, perPage: 6, total: 1 } });
  assert.deepEqual(calls.map(({ url, params }) => [url, params]), [
    ['/api/public/properties/2/reviews', { page: 1, per_page: 6, rating: 4, sort_by: 'rating_asc' }],
    ['/api/customer/reviews', { page: 1, per_page: 6 }],
    ['/api/host/reviews', { page: 2, per_page: 6, property_id: 2, search: 'Suite' }],
  ]);
  assert.ok(calls.every((config) => config.signal === signal));
});

test('valid empty lists preserve null averages and zero totals', async () => {
  const empty = page([]);
  const emptyBreakdown = { ...breakdown, 4: 0 };
  adapter = async (config) => response(config, {
    ...empty,
    summary: { review_count: 0, average_rating: null, rating_breakdown: emptyBreakdown },
  });
  const result = await reviewService.getPublicPropertyReviews(2);
  assert.deepEqual(result.summary, { review_count: 0, average_rating: null, rating_breakdown: emptyBreakdown });
  assert.deepEqual(result.data, []);
  assert.equal(result.pagination.total, 0);
});

test('malformed list, summary and POST envelopes reject with mapper error', async () => {
  for (const invalid of [[], { data: [] }, { ...page([]), meta: { total: 0 } }, page([null])]) {
    adapter = async (config) => response(config, invalid);
    await assert.rejects(reviewService.getCustomerReviews(), { code: 'INVALID_REVIEW_RESPONSE' });
  }
  adapter = async (config) => response(config, {
    ...page([publicRow]), summary: { total_reviews: 1, average_rating: 4, rating_breakdown: breakdown },
  });
  await assert.rejects(reviewService.getPublicPropertyReviews(2), { code: 'INVALID_REVIEW_RESPONSE' });
  adapter = async (config) => response(config, customerRow, 201);
  await assert.rejects(reviewService.createBookingReview(11, { rating: 4, comment: 'Dịch vụ tốt' }),
    (error) => parseReviewError(error).code === 'INVALID_REVIEW_RESPONSE');
});

test('creation sends one request with normalized fields and returns the customer projection', async () => {
  let calls = 0;
  const signal = new AbortController().signal;
  adapter = async (config) => {
    calls++;
    assert.equal(config.method, 'post');
    assert.equal(config.url, '/api/customer/bookings/11/review');
    assert.equal(config.signal, signal);
    assert.deepEqual(JSON.parse(config.data), { rating: 4, comment: 'Dịch vụ tốt' });
    return response(config, { data: customerRow }, 201);
  };
  await assert.rejects(reviewService.createBookingReview(11, { rating: 0, comment: 'Dịch vụ tốt' }));
  await assert.rejects(reviewService.createBookingReview('bad', { rating: 4, comment: 'Dịch vụ tốt' }));
  assert.equal(calls, 0);
  assert.deepEqual(await reviewService.createBookingReview('11', { rating: '4', comment: '  Dịch vụ tốt  ' }, { signal }),
    { data: customerRow });
  assert.equal(calls, 1);
});

test('booking scan preserves reviewed, not_reviewed, unknown and error states', async () => {
  const seen = [];
  adapter = async (config) => {
    seen.push(config.params);
    const { page: current } = config.params;
    const item = current === 1 ? { ...customerRow, booking_id: 12 } : customerRow;
    return response(config, page([item], current, 2));
  };
  assert.deepEqual(await reviewService.getCustomerReviewForBooking('11'), { status: 'reviewed', review: customerRow });
  assert.deepEqual(seen, [{ page: 1, per_page: 50 }, { page: 2, per_page: 50 }]);
  assert.deepEqual(await reviewService.getCustomerReviewForBooking(13), { status: 'not_reviewed', review: null });
  assert.equal((await reviewService.getCustomerReviewForBooking(11, { maxPages: 1 })).status, 'unknown');
  assert.equal((await reviewService.getCustomerReviewForBooking('bad')).status, 'unknown');
  adapter = async (config) => response(config, { data: [] });
  const failed = await reviewService.getCustomerReviewForBooking(11);
  assert.equal(failed.status, 'error');
  assert.equal(failed.error.code, 'INVALID_REVIEW_RESPONSE');
});

test('booking scan propagates cancellation without requesting the next page', async () => {
  const cancelled = new CanceledError();
  let calls = 0;
  adapter = async () => {
    calls++;
    throw cancelled;
  };
  await assert.rejects(reviewService.getCustomerReviewForBooking(11, { signal: new AbortController().signal }),
    (error) => error === cancelled);
  assert.equal(calls, 1);
});

test('HTTP and transport failures preserve rejection identity and never replay POST', async () => {
  for (const failure of [401, 403, 404, 409, 422, 'ERR_NETWORK', 'ECONNABORTED']) {
    let calls = 0;
    const status = typeof failure === 'number' ? failure : 0;
    const error = new AxiosError('fixture failure', status ? 'ERR_BAD_REQUEST' : failure);
    if (status) error.response = { status, data: { message: 'Lỗi fixture', errors: { rating: ['Sai'] } } };
    adapter = async () => {
      calls++;
      throw error;
    };
    await assert.rejects(reviewService.createBookingReview(11, { rating: 4, comment: 'Dịch vụ tốt' }),
      (reason) => reason === error);
    assert.equal(calls, 1);
    assert.equal(parseReviewError(error).status, status);
  }
});

test('shared card projection keeps fallback labels, rating and date behavior', () => {
  assert.deepEqual(mapReviewCard(customerRow), {
    bookingReference: 'Tham chiếu đặt phòng #11',
    dateLabel: '',
    propertyName: 'Chỗ nghỉ chưa có thông tin tên',
    ratingStars: 4,
  });
  const withCode = mapReviewCard({ ...customerRow, booking_code: 'BK-11', created_at: 'invalid-date' });
  assert.equal(withCode.bookingReference, 'Mã đơn: BK-11');
  assert.equal(withCode.dateLabel, 'invalid-date');
});
