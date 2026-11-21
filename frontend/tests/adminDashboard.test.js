import test, { mock } from 'node:test';
import assert from 'node:assert/strict';
import axios, { AxiosError, CanceledError } from 'axios';
import { createAdminDashboardApi } from '../src/api/adminDashboardApi.js';
import { dashboardStatuses, mapAdminDashboardResponse } from '../src/mappers/adminDashboardMapper.js';
import { subscribeToAuthFailures } from '../src/services/authSession.js';

let adapter;
const sharedClient = axios.create({ adapter: (config) => adapter(config) });
mock.module(new URL('../src/api/axios.js', import.meta.url).href, { defaultExport: sharedClient });
const { createAdminDashboardService, getAdminDashboard, formatDashboardRevenue, parseDashboardError, dashboardBarWidths } = await import('../src/services/adminDashboardService.js');
test.after(() => mock.restoreAll());
const body = () => ({ data: { summary: { users_count: 7, hosts_count: 3, properties_count: 4,
  rooms_count: 4, bookings_count: 6, revenue_total: '19999999999.98' }, excluded_paid_invoices_count: 2,
  generated_at: '2026-11-18T07:00:00.000000Z', report_timezone: 'Asia/Ho_Chi_Minh',
  booking_status_counts: dashboardStatuses.map((status) => ({ status, count: 1 })),
  revenue_by_month: ['2025-12', '2026-01', '2026-02', '2026-03', '2026-04', '2026-05', '2026-06', '2026-07', '2026-08', '2026-09', '2026-10', '2026-11']
    .map((month) => ({ month, total_amount: '0.00' })),
} });

test('public service uses shared transport, GET path, signal and allowlist mapping', async () => {
  const calls = [];
  const envelope = body();
  envelope.data.private = 'must not leak';
  adapter = async (config) => { calls.push(config); return { status: 200, data: envelope, config, headers: {} }; };
  const signal = new AbortController().signal;
  const result = await getAdminDashboard({ signal });
  assert.deepEqual(result, body().data);
  assert.equal(calls.length, 1);
  assert.equal(calls[0].method, 'get');
  assert.equal(calls[0].url, '/api/admin/dashboard');
  assert.equal(calls[0].signal, signal);
  assert.equal(calls[0].data, undefined);
  assert.equal(calls[0].params, undefined);
});

test('empty data is zero, malformed data is a contract error', () => {
  const empty = body();
  for (const key of Object.keys(empty.data.summary)) empty.data.summary[key] = key === 'revenue_total' ? '0.00' : 0;
  empty.data.excluded_paid_invoices_count = 0;
  empty.data.booking_status_counts.forEach((row) => { row.count = 0; });
  assert.deepEqual(mapAdminDashboardResponse(empty), empty.data);
  const mutations = [
    (b) => { delete b.data.summary; },
    (b) => { b.data.summary.users_count = '7'; },
    (b) => { b.data.summary.hosts_count = 8; },
    (b) => { b.data.summary.rooms_count = -1; },
    (b) => { b.data.summary.bookings_count = Number.MAX_SAFE_INTEGER + 1; },
    (b) => { b.data.excluded_paid_invoices_count = null; },
    (b) => { b.data.generated_at = '2026-11-18'; },
    (b) => { b.data.generated_at = '2026-99-99T00:00:00Z'; },
  ];
  for (const value of [null, 0, '0.00 ', '1e3', '-1.00', '1.001', '01.00']) mutations.push((b) => { b.data.summary.revenue_total = value; });
  for (const change of mutations) {
    const value = body(); change(value);
    assert.throws(() => mapAdminDashboardResponse(value), (error) => error.code === 'INVALID_ADMIN_DASHBOARD_RESPONSE');
  }
  for (const value of [null, [], {}, { data: null }]) assert.throws(() => mapAdminDashboardResponse(value));
});

test('whole VND display rounds exactly while mapped decimal amounts remain unchanged', () => {
  assert.equal(formatDashboardRevenue('9007199254740993.01'), '9.007.199.254.740.993\u00a0₫');
  assert.equal(formatDashboardRevenue('9007199254740993.50'), '9.007.199.254.740.994\u00a0₫');
  assert.equal(formatDashboardRevenue('0.00'), '0\u00a0₫');
  assert.equal(formatDashboardRevenue('19999999999.98'), '20.000.000.000\u00a0₫');
  assert.equal(formatDashboardRevenue('300.76'), '301\u00a0₫');
  const value = body();
  assert.equal(mapAdminDashboardResponse(value).summary.revenue_total, '19999999999.98');
  assert.equal(value.data.summary.revenue_total, '19999999999.98');
  for (const value of [null, 10, 'invalid']) assert.throws(() => formatDashboardRevenue(value));
});

test('HTTP, network, timeout and cancellation retain rejection identity and never replay', async () => {
  for (const error of [new CanceledError(), new AxiosError('timeout', 'ECONNABORTED'), new AxiosError('network', 'ERR_NETWORK'),
    ...[401, 403, 404, 409, 422, 500].map((status) => new AxiosError('HTTP', undefined, undefined, undefined, { status, data: {}, headers: {} }))]) {
    let calls = 0;
    const client = axios.create({ adapter: async () => { ++calls; throw error; } });
    const service = createAdminDashboardService(createAdminDashboardApi(client));
    await assert.rejects(service.getOverview(), (actual) => actual === error);
    assert.equal(calls, 1);
    const parsed = parseDashboardError(error);
    assert.equal(parsed.isCancelled, error.code === 'ERR_CANCELED');
    assert.equal(parsed.isTimeout, error.code === 'ECONNABORTED');
    assert.equal(parsed.status, error.response?.status ?? 0);
  }
});

test('pre-aborted GET is cancelled without transport', async () => {
  let calls = 0;
  const client = axios.create({ adapter: async () => { ++calls; throw new Error('must not run'); } });
  const controller = new AbortController(); controller.abort();
  await assert.rejects(createAdminDashboardService(createAdminDashboardApi(client)).getOverview({ signal: controller.signal }), (error) => error.code === 'ERR_CANCELED');
  assert.equal(calls, 0);
});

test('Auth pipeline distinguishes dashboard domain 403, account failure, 401 and stale session', async () => {
  let currentVersion = 1;
  const failures = [];
  let release;
  const client = axios.create({ adapter: (config) => new Promise((resolve, reject) => { release = (status, code) => reject(new AxiosError('HTTP', undefined, config, undefined, { status, data: { code }, headers: {} })); }) });
  const unsubscribe = subscribeToAuthFailures(client, (error, version) => failures.push([error.status, version]), () => currentVersion);
  const service = createAdminDashboardService(createAdminDashboardApi(client));
  for (const [status, code, stale] of [[403, 'ADMIN_DASHBOARD_FORBIDDEN', false], [403, 'ACCOUNT_BLOCKED', false], [401, undefined, false], [401, undefined, true]]) {
    const request = service.getOverview();
    const rejected = assert.rejects(request);
    await new Promise((resolve) => setImmediate(resolve));
    if (stale) ++currentVersion;
    release(status, code);
    await rejected;
  }
  // Interceptor passes the captured request version; provider ignores stale notifications.
  assert.deepEqual(failures, [[403, 1], [401, 1], [401, 1]]);
  assert.equal(currentVersion, 2);
  unsubscribe();
});


test('chart contract rejects missing, duplicate, unsorted, mismatched and incorrectly typed series', () => {
  const changes = [
    (b) => { delete b.data.booking_status_counts; },
    (b) => { b.data.booking_status_counts.pop(); },
    (b) => { b.data.booking_status_counts[1].status = 'PENDING'; },
    (b) => { b.data.booking_status_counts.reverse(); },
    (b) => { b.data.booking_status_counts[0].count = 2; },
    (b) => { b.data.booking_status_counts[0].count = '1'; },
    (b) => { b.data.booking_status_counts[0].count = -1; },
    (b) => { b.data.revenue_by_month.pop(); },
    (b) => { b.data.revenue_by_month[1].month = '2025-12'; },
    (b) => { b.data.revenue_by_month.reverse(); },
    (b) => { b.data.revenue_by_month[0].total_amount = 0; },
    (b) => { b.data.revenue_by_month[0].total_amount = '0.001'; },
    (b) => { b.data.revenue_by_month[0].total_amount = '-1.00'; },
    (b) => { b.data.revenue_by_month[0] = null; },
    (b) => { b.data.report_timezone = 'UTC'; },
    (b) => { b.data.generated_at = '2026-11-30T17:00:00Z'; }, // December in Vietnam.
  ];
  for (const change of changes) {
    const value = body(); change(value);
    assert.throws(() => mapAdminDashboardResponse(value), (error) => error.kind === 'contract');
  }
});

test('mapper preserves exact chart money, six states and local month window at year rollover', () => {
  const value = body();
  value.data.generated_at = '2026-12-31T17:00:00Z';
  value.data.revenue_by_month = ['2026-02', '2026-03', '2026-04', '2026-05', '2026-06', '2026-07', '2026-08', '2026-09', '2026-10', '2026-11', '2026-12', '2027-01']
    .map((month) => ({ month, total_amount: '9007199254740993.01' }));
  const result = mapAdminDashboardResponse(value);
  assert.deepEqual(result.revenue_by_month, value.data.revenue_by_month);
  assert.deepEqual(result.booking_status_counts, value.data.booking_status_counts);
  assert.equal(result.summary.revenue_total, '19999999999.98'); // Not recomputed from monthly rows.
});

test('bar scaling is bounded, exact integer arithmetic with zero and extreme ratios', () => {
  assert.deepEqual(dashboardBarWidths([0, 0, 0]), [0, 0, 0]);
  assert.deepEqual(dashboardBarWidths(['0.00', '0.00']), [0, 0]);
  assert.deepEqual(dashboardBarWidths([1, 3, 0]), [33.33, 100, 0]);
  assert.deepEqual(dashboardBarWidths(['0.01', '9007199254740993.01']), [0, 100]);
  assert.deepEqual(dashboardBarWidths(['9007199254740993.00', '9007199254740993.01']), [99.99, 100]);
  assert.deepEqual(dashboardBarWidths([]), []);
  for (const value of [NaN, -1, '1.001', null, Number.MAX_SAFE_INTEGER + 1]) assert.throws(() => dashboardBarWidths([value]));
});
