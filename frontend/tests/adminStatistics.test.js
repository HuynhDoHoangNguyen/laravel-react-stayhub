import test, { mock } from 'node:test';
import assert from 'node:assert/strict';
import axios, { AxiosError, CanceledError } from 'axios';
import { createStatisticsApi } from '../src/api/statisticsApi.js';
import { mapStatisticsResponse, statisticsStatuses } from '../src/mappers/statisticsMapper.js';
import { subscribeToAuthFailures } from '../src/services/authSession.js';

let adapter;
const sharedClient = axios.create({ adapter: (config) => adapter(config) });
mock.module(new URL('../src/api/axios.js', import.meta.url).href, { defaultExport: sharedClient });
const { createStatisticsService, getStatistics, formatStatisticsRevenue, parseStatisticsError, createStatisticsLoader } = await import('../src/services/statisticsService.js');
test.after(() => mock.restoreAll());
const statuses = (values) => statisticsStatuses.map((status, index) => ({ status, count: values[index] }));
const body = () => ({ data: {
  period: { from_month: '2026-10', to_month: '2026-11', start_at: '2026-09-30T17:00:00.000000Z', end_at_exclusive: '2026-11-30T17:00:00.000000Z' },
  generated_at: '2026-11-19T05:00:00.000000Z', report_timezone: 'Asia/Ho_Chi_Minh',
  summary: { bookings_count: 4, revenue_total: '300.76' }, booking_status_counts: statuses([1, 1, 0, 1, 1, 0]),
  monthly: [{ month: '2026-10', bookings_count: 2, revenue_total: '100.25', booking_status_counts: statuses([0, 0, 0, 1, 1, 0]) },
    { month: '2026-11', bookings_count: 2, revenue_total: '200.51', booking_status_counts: statuses([1, 1, 0, 0, 0, 0]) }],
  warnings: { paid_invoices_missing_paid_at_global_count: 1 },
} });
function zero() {
  const result = body();
  for (const row of [result.data.summary, ...result.data.monthly]) { row.bookings_count = 0; row.revenue_total = '0.00'; }
  for (const rows of [result.data.booking_status_counts, ...result.data.monthly.map((row) => row.booking_status_counts)]) rows.forEach((row) => { row.count = 0; });
  return result;
}

test('public statistics service uses shared transport, exact GET query, signal and projection', async () => {
  const calls = [];
  const value = body(); value.data.private = 'hidden'; value.data.monthly[0].email = 'hidden';
  adapter = async (config) => { calls.push(config); return { status: 200, data: value, config, headers: {} }; };
  const params = { from_month: '2026-10', to_month: '2026-11' };
  const signal = new AbortController().signal;
  assert.deepEqual(await getStatistics(params, { signal }), body().data);
  assert.equal(calls[0].url, '/api/admin/statistics'); assert.equal(calls[0].method, 'get');
  assert.deepEqual(calls[0].params, params); assert.equal(calls[0].signal, signal); assert.equal(calls[0].data, undefined);
  await getStatistics(); assert.deepEqual(calls[1].params, {});
});

test('zero period and global missing-date count remain independent', () => {
  assert.deepEqual(mapStatisticsResponse(zero()), zero().data);
});

test('full twelve-month period crosses the year boundary without losing empty buckets', () => {
  const value = zero();
  const months = ['2025-12', '2026-01', '2026-02', '2026-03', '2026-04', '2026-05', '2026-06', '2026-07', '2026-08', '2026-09', '2026-10', '2026-11'];
  value.data.period.from_month = '2025-12'; value.data.period.start_at = '2025-11-30T17:00:00.000000Z';
  value.data.monthly = months.map((month) => ({ month, bookings_count: 0, revenue_total: '0.00', booking_status_counts: statuses([0, 0, 0, 0, 0, 0]) }));
  assert.deepEqual(mapStatisticsResponse(value).monthly.map((row) => row.month), months);
});

test('malformed structure, totals, counts, money, timestamps and periods fail closed', () => {
  const mutations = [
    (b) => { delete b.data.summary; }, (b) => { b.data.monthly = []; },
    (b) => { b.data.period.to_month = '2027-11'; }, (b) => { b.data.period.from_month = '0000-01'; },
    (b) => { b.data.period.from_month = '2026-12'; }, (b) => { b.data.period.start_at = '2026-10-01T00:00:00Z'; },
    (b) => { b.data.period.start_at = '2026-09-30T17:00:00.000001Z'; },
    (b) => { b.data.period.end_at_exclusive = '2026-11-30T17:00:01Z'; },
    (b) => { b.data.report_timezone = 'UTC'; }, (b) => { b.data.generated_at = '2026-02-30T00:00:00Z'; },
    (b) => { b.data.generated_at = '2026-11-19T05:00:00+00:00'; },
    (b) => { b.data.summary.bookings_count = '4'; }, (b) => { b.data.summary.bookings_count = Number.MAX_SAFE_INTEGER + 1; },
    (b) => { b.data.monthly[0].bookings_count = -1; }, (b) => { b.data.monthly[0].month = '2026-11'; },
    (b) => { b.data.monthly[0].booking_status_counts.reverse(); },
    (b) => { b.data.booking_status_counts[0].count = 3; },
    (b) => { b.data.monthly[0].booking_status_counts[0].count = 1; b.data.monthly[0].booking_status_counts[3].count = 0; },
    (b) => { b.data.monthly[0].revenue_total = '100.26'; },
    (b) => { b.data.warnings.paid_invoices_missing_paid_at_global_count = null; },
  ];
  for (const value of [null, 300.76, '1e3', '-1.00', '1.001', '01.00', '0.00 ']) mutations.push((b) => { b.data.summary.revenue_total = value; });
  for (const mutation of mutations) {
    const value = body(); mutation(value);
    assert.throws(() => mapStatisticsResponse(value), (error) => error.code === 'INVALID_ADMIN_STATISTICS_RESPONSE');
  }
  for (const value of [null, [], {}, { data: null }, { data: { data: body().data } }]) assert.throws(() => mapStatisticsResponse(value));
});

test('year 0001 historical offset, year 9999 and leap month are valid', () => {
  for (const [month, start, end] of [
    ['0001-01', '0000-12-31T16:53:30.000000Z', '0001-01-31T16:53:30.000000Z'],
    ['9999-12', '9999-11-30T17:00:00.000000Z', '9999-12-31T17:00:00.000000Z'],
    ['2024-02', '2024-01-31T17:00:00.000000Z', '2024-02-29T17:00:00.000000Z'],
  ]) {
    const value = zero();
    value.data.period = { from_month: month, to_month: month, start_at: start, end_at_exclusive: end };
    value.data.monthly = [{ ...value.data.monthly[0], month }];
    assert.deepEqual(mapStatisticsResponse(value), value.data);
  }
});

test('raw financial totals retain decimals while whole VND display rounds exactly', () => {
  const value = zero(); value.data.summary.revenue_total = '9007199254740993.01'; value.data.monthly[0].revenue_total = '9007199254740993.01';
  assert.equal(mapStatisticsResponse(value).summary.revenue_total, '9007199254740993.01');
  assert.equal(formatStatisticsRevenue('9007199254740993.01'), '9.007.199.254.740.993\u00a0₫');
  assert.equal(formatStatisticsRevenue('9007199254740993.50'), '9.007.199.254.740.994\u00a0₫');
  assert.equal(formatStatisticsRevenue('19999999999.98'), '20.000.000.000\u00a0₫');
  assert.equal(formatStatisticsRevenue('0.00'), '0\u00a0₫');
  assert.equal(formatStatisticsRevenue('300.76'), '301\u00a0₫');
  assert.equal(value.data.summary.revenue_total, '9007199254740993.01');
  assert.throws(() => formatStatisticsRevenue(1));
});

test('HTTP errors, network, timeout and cancellation preserve identity without replay', async () => {
  for (const error of [new CanceledError(), new AxiosError('timeout', 'ECONNABORTED'), new AxiosError('network', 'ERR_NETWORK'),
    ...[401, 403, 404, 409, 422, 500].map((status) => new AxiosError('HTTP', undefined, undefined, undefined,
      { status, data: { errors: { from_month: ['Sai tháng.'] } }, headers: {} }))]) {
    let calls = 0;
    const client = axios.create({ adapter: async () => { calls++; throw error; } });
    await assert.rejects(createStatisticsService(createStatisticsApi(client)).getStatistics(), (actual) => actual === error);
    assert.equal(calls, 1);
    const parsed = parseStatisticsError(error);
    assert.equal(parsed.status, error.response?.status ?? 0);
    assert.equal(parsed.isCancelled, error.code === 'ERR_CANCELED');
    assert.equal(parsed.isTimeout, error.code === 'ECONNABORTED');
    if (parsed.status === 422) assert.deepEqual(parsed.fields, { from_month: ['Sai tháng.'] });
  }
});

test('pre-aborted statistics GET never calls transport', async () => {
  let calls = 0;
  const client = axios.create({ adapter: async () => { calls++; throw new Error('unexpected'); } });
  const controller = new AbortController(); controller.abort();
  await assert.rejects(createStatisticsService(createStatisticsApi(client)).getStatistics({}, { signal: controller.signal }), (error) => error.code === 'ERR_CANCELED');
  assert.equal(calls, 0);
});

const tick = () => new Promise((resolve) => setImmediate(resolve));
function harness() {
  const pending = [], shown = [], errors = [];
  let version = 1;
  const loader = createStatisticsLoader((params, { signal }) => new Promise((resolve, reject) => pending.push({ params, signal, resolve, reject })));
  const options = { captureAuth: () => ({ version }), isAuthCurrent: (auth) => auth.version === version,
    success: (result) => shown.push(result), failure: (error) => errors.push(error) };
  return { loader, options, pending, shown, errors, logout: () => { version++; } };
}

test('new period replaces pending response even when old transport ignores abort', async () => {
  const h = harness();
  h.loader.start({ from_month: '2026-10' }, h.options); await tick();
  h.loader.start({ from_month: '2026-11' }, h.options); await tick();
  assert.equal(h.pending[0].signal.aborted, true);
  h.pending[1].resolve('new'); await tick(); h.pending[0].resolve('old'); await tick();
  assert.deepEqual(h.shown, ['new']);
});

test('immediate invalidation, unmount and auth version changes suppress late outcomes', async () => {
  for (const action of ['invalidate', 'unmount', 'logout']) {
    for (const rejected of [false, true]) {
      const h = harness(); const cleanup = h.loader.start({}, h.options); await tick();
      if (action === 'invalidate') h.loader.invalidate(); else if (action === 'unmount') cleanup(); else h.logout();
      if (rejected) h.pending[0].reject(new AxiosError('HTTP', undefined, undefined, undefined, { status: 401, data: {}, headers: {} }));
      else h.pending[0].resolve('stale');
      await tick(); assert.deepEqual(h.shown, []); assert.deepEqual(h.errors, []);
    }
  }
});

test('failure then retry and default reset use distinct requests', async () => {
  const h = harness(); h.loader.start({ from_month: 'bad' }, h.options); await tick();
  h.pending[0].reject(new AxiosError('offline', 'ERR_NETWORK')); await tick(); assert.equal(h.errors[0].isNetworkError, true);
  h.loader.start({}, h.options); await tick(); assert.deepEqual(h.pending[1].params, {});
  h.pending[1].resolve('recovered'); await tick(); assert.deepEqual(h.shown, ['recovered']);
});

test('shared Auth pipeline distinguishes statistics role denial, account failures and stale 401', async () => {
  let version = 1, release;
  const failures = [], handled = [];
  const client = axios.create({ adapter: (config) => new Promise((resolve, reject) => { release = (status, code) => reject(new AxiosError('HTTP', undefined, config, undefined, { status, data: { code }, headers: {} })); }) });
  const unsubscribe = subscribeToAuthFailures(client, (error, captured) => {
    failures.push([error.status, captured]);
    // AuthContext owns the version check; the interceptor only forwards it.
    if (captured === version) handled.push([error.status, captured]);
  }, () => version);
  const service = createStatisticsService(createStatisticsApi(client));
  for (const [status, code, stale] of [[403, 'ADMIN_STATISTICS_FORBIDDEN', false], [403, 'ACCOUNT_BLOCKED', false], [401, undefined, false], [401, undefined, true]]) {
    const request = service.getStatistics(); const rejected = assert.rejects(request); await tick();
    if (stale) version++; release(status, code); await rejected;
  }
  unsubscribe();
  assert.deepEqual(failures, [[403, 1], [401, 1], [401, 1]]);
  assert.deepEqual(handled, [[403, 1], [401, 1]]);
});
