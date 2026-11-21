import test, { mock } from 'node:test';
import assert from 'node:assert/strict';
import axios, { AxiosError, CanceledError } from 'axios';
import { createHostDashboardApi } from '../src/api/hostDashboardApi.js';
import { mapHostDashboardResponse } from '../src/mappers/hostDashboardMapper.js';
import { formatVnd } from '../src/utils/formatVnd.js';
import { subscribeToAuthFailures } from '../src/services/authSession.js';

let adapter;
const shared = axios.create({ adapter: (config) => adapter(config) });
mock.module(new URL('../src/api/axios.js', import.meta.url).href, { defaultExport: shared });
const { createHostDashboardService, getHostDashboard, createHostDashboardLoader, hostDashboardPeriodErrors, hostDashboardBarWidths } = await import('../src/services/hostDashboardService.js');

test('UI month input supports server default, leap month, year extremes and 12 inclusive months', () => {
  for (const pair of [['', ''], ['0001-01', '0001-12'], ['9999-12', '9999-12'], ['2024-02', '2024-02']]) {
    assert.deepEqual(hostDashboardPeriodErrors(...pair), {});
  }
  for (const pair of [['', '2026-01'], ['2026-01', ''], ['0000-01', '0001-01'], ['2026-13', '2026-12'],
    ['2026-02', '2026-01'], ['2025-11', '2026-11'], ['2026-01\n', '2026-02']]) {
    assert.ok(Object.keys(hostDashboardPeriodErrors(...pair)).length > 0);
  }
});

test('chart ratios preserve huge decimals and raw fractional nonzero values', () => {
  assert.deepEqual(hostDashboardBarWidths(['0.00', '0.00']), [0, 0]);
  assert.deepEqual(hostDashboardBarWidths(['0.00', '0.49']), [0, 100]);
  assert.deepEqual(hostDashboardBarWidths(['9007199254740993.00', '9007199254740993.01']), [99.99, 100]);
  assert.deepEqual(hostDashboardBarWidths(['1.00', '3.00']), [33.33, 100]);
  for (const value of [null, 1, '-1.00', '01.00', '1.001', '0.00\n']) assert.throws(() => hostDashboardBarWidths([value]));
});

test('actual chart SSR includes equivalent table, escaped text and distinct zero/nonzero states', async () => {
  const { createServer } = await import('vite');
  const { createElement } = await import('react');
  const { renderToStaticMarkup } = await import('react-dom/server');
  const { fileURLToPath } = await import('node:url');
  const server = await createServer({ root: fileURLToPath(new URL('..', import.meta.url)), logLevel: 'error',
    server: { middlewareMode: true, hmr: false, watch: null } });
  try {
    const { default: Chart } = await server.ssrLoadModule('/src/components/dashboard/HostDashboardRevenueChart.jsx');
    for (const amount of ['0.00', '0.49', '19999999999.98']) {
      const html = renderToStaticMarkup(createElement(Chart, { rows: [{ month: '2026-11', revenue_total: amount }] }));
      assert.ok(html.includes('<table>')); assert.ok(html.includes('scope="col"')); assert.ok(html.includes('scope="row"'));
      assert.equal(html.includes('Chưa có doanh thu được ghi nhận'), amount === '0.00');
      assert.ok(html.includes('2026-11')); assert.ok(html.includes('role="img"'));
    }
  } finally { await server.close(); }
});
test.after(() => mock.restoreAll());

function body() {
  return { data: {
    scope: { properties_count: 2 }, snapshot: { rooms_count: 3, pending_bookings_count: 2, in_progress_maintenances_count: 2 },
    period: { from_month: '2026-10', to_month: '2026-11', start_at: '2026-09-30T17:00:00.000000Z', end_at_exclusive: '2026-11-30T17:00:00.000000Z' },
    summary: { revenue_total: '300.76' }, report_timezone: 'Asia/Ho_Chi_Minh', generated_at: '2026-11-21T05:00:00.000000Z',
    revenue_by_month: [{ month: '2026-10', revenue_total: '100.25' }, { month: '2026-11', revenue_total: '200.51' }],
    warnings: { paid_invoices_missing_paid_at_scope_count: 1 },
    recent_bookings: [{ id: '9007199254740994', booking_code: '<script>text only</script>', status: 'PENDING',
      created_at: '2026-11-20T00:00:00.000000Z', check_in_date: '2026-12-01', check_out_date: '2026-12-02',
      property: { id: '18446744073709551615', name: 'Cơ sở' }, room: { id: '201', name: 'Phòng' } }],
  } };
}

test('public service uses shared GET transport with query and AbortSignal, without GET body', async () => {
  const calls = [];
  adapter = async (config) => { calls.push(config); return { status: 200, data: body(), config, headers: {} }; };
  const signal = new AbortController().signal;
  assert.deepEqual(await getHostDashboard({ fromMonth: '2026-10', toMonth: '2026-11', signal }), body().data);
  assert.equal(calls[0].url, '/api/host/dashboard');
  assert.equal(calls[0].method, 'get');
  assert.equal(calls[0].signal, signal);
  assert.deepEqual(calls[0].params, { from_month: '2026-10', to_month: '2026-11' });
  assert.equal(calls[0].data, undefined);
  await getHostDashboard();
  assert.deepEqual(calls[1].params, {}); // The server owns the default period.
});

test('allowlist excludes private extras and preserves canonical string IDs and dates', () => {
  const b = body();
  b.data.customer_name = 'PRIVATE'; b.data.recent_bookings[0].customer_email = 'PRIVATE';
  b.data.recent_bookings[0].room.note = 'PRIVATE';
  assert.deepEqual(mapHostDashboardResponse(b), body().data);
  assert.equal(b.data.recent_bookings[0].room.note, 'PRIVATE'); // Mapper never mutates source.
});

test('valid empty response remains data, and fractional nonzero revenue never becomes empty', () => {
  const b = body();
  b.data.scope.properties_count = 0;
  for (const key of Object.keys(b.data.snapshot)) b.data.snapshot[key] = 0;
  b.data.summary.revenue_total = '0.00';
  b.data.revenue_by_month.forEach((row) => { row.revenue_total = '0.00'; });
  b.data.recent_bookings = []; b.data.warnings.paid_invoices_missing_paid_at_scope_count = 0;
  assert.deepEqual(mapHostDashboardResponse(b), b.data);
  b.data.summary.revenue_total = '0.49'; b.data.revenue_by_month[0].revenue_total = '0.49';
  assert.equal(mapHostDashboardResponse(b).summary.revenue_total, '0.49');
  assert.equal(formatVnd('0.49'), '0\u00a0₫');
});

test('huge revenue stays exact and VND formatting rounds half-up independently', () => {
  const b = body();
  b.data.summary.revenue_total = '18014398509481986.02';
  b.data.revenue_by_month.forEach((row) => { row.revenue_total = '9007199254740993.01'; });
  assert.equal(mapHostDashboardResponse(b).summary.revenue_total, '18014398509481986.02');
  assert.equal(formatVnd('9007199254740993.50'), '9.007.199.254.740.994\u00a0₫');
  assert.equal(formatVnd('300.76'), '301\u00a0₫');
});

const malformed = [
  ['missing scope', (b) => { delete b.data.scope; }],
  ['missing snapshot', (b) => { delete b.data.snapshot; }],
  ['missing summary', (b) => { delete b.data.summary; }],
  ['missing period', (b) => { delete b.data.period; }],
  ['missing warning', (b) => { delete b.data.warnings; }],
  ['unsafe count', (b) => { b.data.snapshot.rooms_count = 9007199254740992; }],
  ['string count', (b) => { b.data.scope.properties_count = '2'; }],
  ['negative warning', (b) => { b.data.warnings.paid_invoices_missing_paid_at_scope_count = -1; }],
  ['wrong timezone', (b) => { b.data.report_timezone = 'UTC'; }],
  ['impossible generated date', (b) => { b.data.generated_at = '2026-02-30T00:00:00Z'; }],
  ['invalid hour', (b) => { b.data.generated_at = '2026-11-21T24:00:00Z'; }],
  ['untyped generated time', (b) => { b.data.generated_at = null; }],
  ['period reversed', (b) => { b.data.period.from_month = '2026-12'; }],
  ['period too long', (b) => { b.data.period.from_month = '2025-10'; }],
  ['period year zero', (b) => { b.data.period.from_month = '0000-01'; }],
  ['period invalid month', (b) => { b.data.period.to_month = '2026-13'; }],
  ['boundary reversed', (b) => { b.data.period.start_at = b.data.period.end_at_exclusive; }],
  ['boundary wrong month', (b) => { b.data.period.start_at = '2026-08-31T17:00:00Z'; }],
  ['boundary wrong timezone', (b) => { b.data.period.end_at_exclusive = '2026-12-01T00:00:00Z'; }],
  ['missing month', (b) => { b.data.revenue_by_month.pop(); }],
  ['duplicate month', (b) => { b.data.revenue_by_month[1].month = '2026-10'; }],
  ['unsorted months', (b) => { b.data.revenue_by_month.reverse(); }],
  ['summary mismatch', (b) => { b.data.summary.revenue_total = '300.75'; }],
  ['wrong monthly type', (b) => { b.data.revenue_by_month[0].revenue_total = 100.25; }],
  ['null monthly row', (b) => { b.data.revenue_by_month[0] = null; }],
  ['recent not array', (b) => { b.data.recent_bookings = {}; }],
  ['duplicate recent ID', (b) => { b.data.recent_bookings.push(b.data.recent_bookings[0]); }],
  ['too many recent', (b) => { b.data.recent_bookings = Array(6).fill(b.data.recent_bookings[0]); }],
  ['numeric ID', (b) => { b.data.recent_bookings[0].id = 9007199254740994; }],
  ['noncanonical ID', (b) => { b.data.recent_bookings[0].room.id = '0201'; }],
  ['invalid status', (b) => { b.data.recent_bookings[0].status = 'UNKNOWN'; }],
  ['future recent', (b) => { b.data.recent_bookings[0].created_at = '2026-11-21T05:00:00.000001Z'; }],
  ['impossible stay date', (b) => { b.data.recent_bookings[0].check_in_date = '2026-02-30'; }],
  ['date must stay DATE', (b) => { b.data.recent_bookings[0].check_in_date += 'T00:00:00Z'; }],
  ['invalid property', (b) => { b.data.recent_bookings[0].property = null; }],
];
for (const value of [null, 0, '0.00\n', '01.00', '-1.00', '1e3', '0.001', '0.00 ']) {
  malformed.push([`bad decimal ${JSON.stringify(value)}`, (b) => { b.data.summary.revenue_total = value; }]);
}
for (const [name, change] of malformed) test(`mapper rejects ${name}`, () => {
  const b = body(); change(b);
  assert.throws(() => mapHostDashboardResponse(b), (error) => error.code === 'INVALID_HOST_DASHBOARD_RESPONSE');
});

test('recent tie-breaker compares IDs exactly above JS safe range', () => {
  const b = body();
  const row = structuredClone(b.data.recent_bookings[0]); row.id = '9007199254740993';
  b.data.recent_bookings.push(row);
  assert.equal(mapHostDashboardResponse(b).recent_bookings[1].id, row.id);
  b.data.recent_bookings.reverse();
  assert.throws(() => mapHostDashboardResponse(b));
});

test('extreme period boundary years are valid without browser Date.parse normalization', () => {
  for (const [month, start, end] of [
    ['0001-01', '0000-12-31T16:53:30.000000Z', '0001-01-31T16:53:30.000000Z'],
    ['9999-12', '9999-11-30T17:00:00.000000Z', '9999-12-31T17:00:00.000000Z'],
  ]) {
    const b = body(); b.data.period = { from_month: month, to_month: month, start_at: start, end_at_exclusive: end };
    b.data.revenue_by_month = [{ month, revenue_total: '0.00' }]; b.data.summary.revenue_total = '0.00';
    assert.equal(mapHostDashboardResponse(b).period.to_month, month);
  }
});

test('HTTP/network/timeout/cancellation errors retain identity with no retry or empty fallback', async () => {
  for (const error of [new CanceledError(), new AxiosError('timeout', 'ECONNABORTED'), new AxiosError('network', 'ERR_NETWORK'),
    ...[401, 403, 404, 409, 422, 500].map((status) => new AxiosError('HTTP', undefined, undefined, undefined, { status, data: {}, headers: {} }))]) {
    let calls = 0;
    const api = createHostDashboardApi(axios.create({ adapter: async () => { calls++; throw error; } }));
    await assert.rejects(createHostDashboardService(api).getOverview(), (actual) => actual === error);
    assert.equal(calls, 1);
  }
});

test('pre-aborted request never reaches transport', async () => {
  let calls = 0;
  const controller = new AbortController(); controller.abort();
  const api = createHostDashboardApi(axios.create({ adapter: async () => { calls++; } }));
  await assert.rejects(createHostDashboardService(api).getOverview({ signal: controller.signal }), (e) => e.code === 'ERR_CANCELED');
  assert.equal(calls, 0);
});

function deferred() {
  let resolve, reject;
  const promise = new Promise((ok, fail) => { resolve = ok; reject = fail; });
  return { promise, resolve, reject };
}
function loaderHarness() {
  const requests = [], views = [];
  let version = 1;
  const loader = createHostDashboardLoader({
    captureAuth: () => version, isAuthCurrent: (snapshot) => version === snapshot,
    getOverview: (options) => { const d = deferred(); requests.push({ ...d, options }); return d.promise; },
    onChange: (view) => views.push(view),
  });
  return { loader, requests, views, switchAuth: () => { version++; } };
}

test('reset requests server default and rejects late filtered results; retry keeps attempted query', async () => {
  const h = loaderHarness();
  const query = { fromMonth: '2026-10', toMonth: '2026-11' };
  const filtered = h.loader.load(query);
  const reset = h.loader.load({});
  assert.equal(h.requests[1].options.fromMonth, undefined);
  assert.equal(h.requests[1].options.toMonth, undefined);
  h.requests[1].resolve('SERVER DEFAULT'); await reset;
  h.requests[0].resolve('OLD FILTER'); await filtered;
  assert.equal(h.views.at(-1).result, 'SERVER DEFAULT');
  const failed = h.loader.load(query); h.requests[2].reject(new Error('offline')); await failed;
  const retried = h.loader.load(query);
  assert.equal(h.requests[3].options.fromMonth, query.fromMonth);
  assert.equal(h.requests[3].options.toMonth, query.toMonth);
  h.requests[3].resolve('RETRY'); await retried;
  assert.equal(h.views.at(-1).result, 'RETRY');
});

test('reload/period change immediately invalidates old results even if transport ignores abort', async () => {
  const h = loaderHarness();
  const old = h.loader.load({ fromMonth: '2026-10', toMonth: '2026-10' });
  const fresh = h.loader.load({ fromMonth: '2026-11', toMonth: '2026-11' });
  assert.equal(h.requests[0].options.signal.aborted, true);
  h.requests[1].resolve('NEW'); await fresh;
  h.requests[0].resolve('OLD'); await old;
  assert.deepEqual(h.views.map((v) => v.result), [null, null, 'NEW']);
});

test('unmount/cancel ignores late errors and results', async () => {
  const h = loaderHarness(); const pending = h.loader.load(); h.loader.cancel();
  h.requests[0].reject(new Error('late failure')); await pending;
  assert.equal(h.views.length, 1); assert.equal(h.requests[0].options.signal.aborted, true);
});

test('Auth version change excludes stale success and stale failure', async () => {
  for (const fail of [false, true]) {
    const h = loaderHarness(); const pending = h.loader.load(); h.switchAuth();
    if (fail) h.requests[0].reject(new Error('OLD HOST')); else h.requests[0].resolve('OLD HOST');
    await pending; assert.equal(h.views.length, 1);
  }
});

test('loader clears prior data on retry, keeps 422 fields and never renders denied data', async () => {
  const h = loaderHarness(); let pending = h.loader.load(); h.requests[0].resolve('OLD'); await pending;
  pending = h.loader.load(); assert.equal(h.views.at(-1).result, null);
  h.requests[1].reject(new AxiosError('query', undefined, undefined, undefined,
    { status: 422, data: { code: 'HOST_DASHBOARD_INVALID_QUERY', errors: { to_month: ['Kỳ không hợp lệ.'] } }, headers: {} }));
  await pending; assert.deepEqual(h.views.at(-1).error.fields, { to_month: ['Kỳ không hợp lệ.'] });
  pending = h.loader.load(); h.requests[2].reject(new AxiosError('denied', undefined, undefined, undefined,
    { status: 403, data: { code: 'HOST_DASHBOARD_FORBIDDEN' }, headers: {} }));
  await pending; assert.equal(h.views.at(-1).result, null); assert.equal(h.views.at(-1).error.status, 403);
});

test('existing Auth pipeline distinguishes domain 403, account 403 and 401', async () => {
  const failures = [];
  const client = axios.create({ adapter: async (config) => { throw new AxiosError('HTTP', undefined, config, undefined,
    { status: config.testStatus, data: { code: config.testCode }, headers: {} }); } });
  const unsubscribe = subscribeToAuthFailures(client, (error, version) => failures.push([error.status, version]), () => 7);
  for (const [status, code] of [[403, 'HOST_DASHBOARD_FORBIDDEN'], [403, 'ACCOUNT_INACTIVE'], [401, undefined]]) {
    await assert.rejects(client.get('/api/host/dashboard', { testStatus: status, testCode: code }));
  }
  assert.deepEqual(failures, [[403, 7], [401, 7]]); unsubscribe();
});
