import assert from 'node:assert/strict';
import { setTimeout as delay } from 'node:timers/promises';

const { STAYHUB_UC44_FRONTEND_URL: frontend, STAYHUB_UC44_BACKEND_URL: backend,
  STAYHUB_UC44_BROWSER_PORT: debugPort, STAYHUB_UC44_BROWSER_PASSWORD: password } = process.env;
const loopback = /^http:\/\/127\.0\.0\.1:[1-9]\d{0,4}$/;
// Independent expectation for the long property name seeded by Uc44BrowserServer.
const longPropertyName = 'Cơ sở QA Alfa ' + 'Dài'.repeat(23);
const list = '/api/admin/bookings';
const options = list + '/filter-options';
const section = '[aria-labelledby="admin-bookings-title"]';
const rowsSelector = section + ' article h2';
const search = section + ' form input:not([type])';
const from = '[name="check_in_from"]';
const to = '[name="check_in_to"]';
const checkpoints = [];
const requests = new Map();
const pending = new Map();
let interception;
let sequence = 0;
let socket;
let browserVersion;

async function waitFor(description, read, timeout = 12000) {
  const until = Date.now() + timeout;
  do {
    const value = await read();
    if (value) return value;
    await delay(70);
  } while (Date.now() < until);
  throw new Error('Timed out: ' + description);
}

function send(method, params = {}, sessionId) {
  return new Promise((resolve, reject) => {
    const id = ++sequence;
    const timer = setTimeout(() => {
      pending.delete(id);
      reject(new Error('CDP timeout: ' + method));
    }, 7000);
    pending.set(id, (message) => {
      clearTimeout(timer);
      if (message.error) reject(new Error(method + ': ' + message.error.message));
      else resolve(message.result);
    });
    socket.send(JSON.stringify({ id, method, params, sessionId }));
  });
}

function onMessage({ id, result, error, method, params }) {
  if (id) {
    pending.get(id)?.({ result, error });
    pending.delete(id);
    return;
  }
  if (method === 'Fetch.requestPaused') {
    const url = new URL(params.request.url);
    if (params.request.method === 'GET' && interception && !interception.hit && interception.match(url)) {
      interception.hit = params;
      if (interception.fail) {
        interception.done = send('Fetch.failRequest', { requestId: params.requestId, errorReason: 'Failed' });
      }
    } else {
      send('Fetch.continueRequest', { requestId: params.requestId }).catch(() => {});
    }
    return;
  }
  if (method === 'Network.requestWillBeSent') {
    const url = new URL(params.request.url);
    if (url.origin === backend && params.request.method !== 'OPTIONS' &&
        [list, options, '/api/me', '/login', '/logout', '/sanctum/csrf-cookie'].includes(url.pathname)) {
      requests.set(params.requestId, { method: params.request.method, path: url.pathname, query: url.search });
    }
  }
  const request = requests.get(params?.requestId);
  if (request && method === 'Network.responseReceived') request.status = params.response.status;
  if (request && method === 'Network.loadingFailed') {
    request.cancelled = params.canceled ?? false;
    request.error = params.errorText;
  }
}

async function evaluate(fn, ...args) {
  const expression = '(' + fn.toString() + ')(...' + JSON.stringify(args) + ')';
  const result = await send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
  if (result.exceptionDetails) {
    // Avoid surfacing evaluation input or headers, which can contain session data.
    throw new Error('Browser evaluation failed: ' + (result.exceptionDetails.exception?.className ?? 'Error'));
  }
  return result.result.value;
}

async function value(selector, next, index = 0) {
  await evaluate((selector, next, index) => {
    const el = document.querySelectorAll(selector)[index];
    if (!el) throw new Error('Input missing');
    const prototype = el instanceof HTMLSelectElement ? HTMLSelectElement.prototype : HTMLInputElement.prototype;
    Object.getOwnPropertyDescriptor(prototype, 'value').set.call(el, next);
    el.dispatchEvent(new Event(el instanceof HTMLSelectElement ? 'change' : 'input', { bubbles: true }));
    if (el.type === 'date') el.dispatchEvent(new Event('change', { bubbles: true }));
  }, selector, next, index);
}

async function button(label, container = section) {
  const clicked = await evaluate((label, container) => {
    const match = [...document.querySelectorAll(container + ' button')]
      .find((el) => el.textContent.trim() === label && !el.disabled);
    if (!match) return false;
    match.click();
    return true;
  }, label, container);
  assert.ok(clicked, 'Enabled button: ' + label);
}

const codes = () => evaluate((selector) =>
  [...document.querySelectorAll(selector)].map((el) => el.textContent), rowsSelector);
const expectCodes = (expected) => waitFor('booking codes ' + expected.join(','), async () =>
  JSON.stringify(await codes()) === JSON.stringify(expected));
const textIn = (selector, text) => evaluate((selector, text) =>
  document.querySelector(selector)?.textContent.includes(text) ?? false, selector, text);
const bookingCodes = (...ids) => ids.map((id) => 'UI44-' + id);
const firstPage = bookingCodes(112, 111, 110, 109, 108, 107, 106, 105, 104, 103);

function assertRequest(path, expected) {
  const matched = [...requests.values()].findLast((request) => {
    const query = new URLSearchParams(request.query);
    return request.path === path && request.status === 200 &&
      Object.entries(expected).every(([key, value]) => query.get(key) === value);
  });
  assert.ok(matched, 'Real request: ' + path + ' ' + JSON.stringify(expected));
}

async function request(path, { method = 'GET', body, xsrf = false } = {}) {
  return evaluate(async (backend, path, method, body, xsrf) => {
    const headers = { Accept: 'application/json' };
    if (body !== null) headers['Content-Type'] = 'application/json';
    if (xsrf) {
      const token = document.cookie.split('; ').find((entry) => entry.startsWith('XSRF-TOKEN='));
      if (!token) throw new Error('XSRF cookie missing');
      headers['X-XSRF-TOKEN'] = decodeURIComponent(token.slice('XSRF-TOKEN='.length));
    }
    const response = await fetch(backend + path, {
      method, credentials: 'include', headers, body: body === null ? undefined : JSON.stringify(body),
    });
    const result = await response.json().catch(() => ({}));
    return { status: response.status, code: result.code, role: result.user?.role, id: result.user?.id,
      fields: Object.keys(result.errors ?? {}) };
  }, backend, path, method, body, xsrf);
}

function credentials(account, role) {
  return { email: account + '@example.test', password, expected_role: role };
}

async function login(account, role) {
  assert.equal((await request('/sanctum/csrf-cookie')).status, 204);
  return request('/login', { method: 'POST', body: credentials(account, role), xsrf: true });
}

async function logout() {
  assert.equal((await request('/logout', { method: 'POST', body: {}, xsrf: true })).status, 200);
}

async function verifyDenied(status, code) {
  for (const path of [list, options + '?type=hosts']) {
    const response = await request(path);
    assert.equal(response.status, status, path);
    if (code) assert.equal(response.code, code);
  }
}

async function intercept(path, params, fail = false) {
  assert.equal(interception, undefined);
  interception = { fail, match: (url) => url.origin === backend && url.pathname === path &&
    Object.entries(params).every(([name, val]) => url.searchParams.get(name) === val) };
  await send('Fetch.enable', { patterns: [{ urlPattern: backend + path + '*', requestStage: fail ? 'Request' : 'Response' }] });
  return interception;
}

async function release(expectedCancellation = false) {
  const current = interception;
  if (current.hit && !current.fail) {
    try { await send('Fetch.continueRequest', { requestId: current.hit.requestId }); }
    catch (error) { if (!expectedCancellation || !requests.get(current.hit.networkId)?.cancelled) throw error; }
  }
  await send('Fetch.disable');
  interception = undefined;
}

const selectFilter = (index, id) => value(section + ' fieldset select', id, index);
const setSearch = async (next) => {
  await value(search, next);
  await button('Tìm kiếm');
};
const latestOptions = (id) => evaluate((section, id) =>
  [...document.querySelectorAll(section + ' fieldset select')].some((el) =>
    [...el.options].some((option) => option.value === id)), section, id);
const fieldset = (index) => section + ' > div:nth-of-type(1) > fieldset:nth-child(' + index + ')';

async function layout(width, height, scale = 1) {
  await send('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: scale, mobile: false });
  await settleLayout();
  return measureLayout();
}

async function settleLayout() {
  await evaluate(() => new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve))));
  await delay(280);
}

function measureLayout() {
  return evaluate((section, expectedName) => {
    const page = document.documentElement;
    const main = document.querySelector(section);
    const overflowing = page.scrollWidth > innerWidth + 1
      ? [...document.querySelectorAll('body *')].filter((el) => {
        const box = el.getBoundingClientRect();
        return box.width && box.right > innerWidth + 1;
      }).slice(0, 12).map((el) => ({ tag: el.tagName, className: String(el.className).slice(0, 160),
        right: Math.round(el.getBoundingClientRect().right) }))
      : [];
    const cards = [...main.querySelectorAll('article')];
    const sample = cards.find((card) => card.querySelector('h2')?.textContent === 'UI44-110');
    const longName = [...(sample?.querySelectorAll('dd') ?? [])]
      .find((field) => field.previousElementSibling?.textContent === 'Cơ sở');
    const textRects = (element) => {
      const range = document.createRange();
      range.selectNodeContents(element);
      return [...range.getClientRects()];
    };
    const contentOverflow = Math.ceil(cards.reduce((maximum, card) => {
      const bounds = card.getBoundingClientRect();
      for (const text of card.querySelectorAll('h2, p, dt, dd')) {
        for (const rect of textRects(text)) {
          maximum = Math.max(maximum, rect.right - bounds.right, bounds.left - rect.left,
            rect.bottom - bounds.bottom, bounds.top - rect.top);
        }
      }
      return maximum;
    }, 0));
    const nameStyle = longName && getComputedStyle(longName);
    return {
      width: innerWidth, scrollWidth: page.scrollWidth, devicePixelRatio, overflowing,
      cardCount: cards.length,
      columns: new Set(cards.map((card) => Math.round(card.getBoundingClientRect().left))).size,
      contentOverflow,
      longNameIntact: longName?.textContent === expectedName,
      longNameLines: longName ? new Set(textRects(longName).map((rect) => Math.round(rect.top))).size : 0,
      longNameVisible: nameStyle?.visibility === 'visible' && nameStyle.textOverflow === 'clip' &&
        (nameStyle.webkitLineClamp === 'none' || !nameStyle.webkitLineClamp) &&
        longName.scrollHeight <= longName.clientHeight + 1,
      controls: [...main.querySelectorAll('form input, form button, select, nav button')].every((el) => {
        const { left, right, width } = el.getBoundingClientRect();
        return width > 0 && left >= -1 && right <= innerWidth + 1;
      }),
    };
  }, section, longPropertyName);
}

function fitsBookingLayout(measured, expectedColumns) {
  return measured.scrollWidth <= measured.width + 1 && measured.controls &&
    measured.cardCount === firstPage.length && measured.columns === expectedColumns &&
    measured.contentOverflow <= 1 && measured.longNameIntact && measured.longNameVisible &&
    measured.longNameLines >= 2;
}

async function key(name, code, windowsVirtualKeyCode) {
  const params = { key: name, code, windowsVirtualKeyCode };
  await send('Input.dispatchKeyEvent', { type: 'rawKeyDown', ...params });
  if (name === 'Enter') await send('Input.dispatchKeyEvent', { type: 'char', ...params, text: '\r', unmodifiedText: '\r' });
  await send('Input.dispatchKeyEvent', { type: 'keyUp', ...params });
}

async function checkBrowserZoom() {
  const { targetId } = await send('Target.createTarget', { url: 'edge://settings/appearance', background: true });
  const { sessionId } = await send('Target.attachToTarget', { targetId, flatten: true });
  const setting = async (expression) => (await send('Runtime.evaluate', {
    expression, awaitPromise: true, returnByValue: true,
  }, sessionId)).result.value;
  try {
    await waitFor('Edge default zoom setting', () =>
      setting('typeof chrome.settingsPrivate?.setDefaultZoom === "function"'), 3000);
    await layout(1440, 900);
    await setting('chrome.settingsPrivate.setDefaultZoom(2)');
    await waitFor('native 200% zoom reached Booking page', async () =>
      (await evaluate(() => devicePixelRatio)) >= 1.99, 3000);
    await settleLayout();
    const dimensions = await measureLayout();
    assert.ok(dimensions.width >= 719 && dimensions.width <= 721 && fitsBookingLayout(dimensions, 2),
      'Horizontal overflow at actual Edge zoom: ' + JSON.stringify(dimensions));
    return dimensions;
  } finally {
    await setting('chrome.settingsPrivate?.setDefaultZoom(1)').catch(() => {});
    await send('Target.closeTarget', { targetId });
  }
}

async function check(id, label, run) {
  try {
    const detail = await run();
    checkpoints.push({ id, label, status: 'PASS', ...(detail === undefined ? {} : { detail }) });
  } catch (error) {
    const state = await evaluate((section) => ({ path: location.pathname,
      heading: document.querySelector('h1')?.textContent,
      codes: [...document.querySelectorAll(section + ' article h2')].map(el => el.textContent),
      messages: [...document.querySelectorAll(section + ' [role="alert"], ' + section + ' [role="status"]')]
        .map(el => el.textContent.trim()) }), section).catch(() => null);
    checkpoints.push({ id, label, status: 'FAIL', error: error.message, state });
    if (interception) {
      await send('Fetch.disable').catch(() => {});
      interception = undefined;
    }
  }
}

async function main() {
  assert.ok(loopback.test(frontend) && loopback.test(backend) && /^\d+$/.test(debugPort) && password?.length >= 32);
  const targets = await (await fetch('http://127.0.0.1:' + debugPort + '/json/list')).json();
  const target = targets.find((item) => item.type === 'page');
  assert.ok(target?.webSocketDebuggerUrl.startsWith('ws://127.0.0.1:' + debugPort + '/'));
  socket = new WebSocket(target.webSocketDebuggerUrl);
  socket.addEventListener('message', ({ data }) => onMessage(JSON.parse(data)));
  await new Promise((resolve, reject) => {
    socket.addEventListener('open', resolve, { once: true });
    socket.addEventListener('error', reject, { once: true });
  });
  browserVersion = (await send('Browser.getVersion')).product;
  await send('Page.enable');
  await send('Network.enable');
  await send('Network.setCacheDisabled', { cacheDisabled: true });
  await send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 900, mobile: false, deviceScaleFactor: 1 });
  await send('Emulation.setTimezoneOverride', { timezoneId: 'Pacific/Honolulu' });
  await send('Page.navigate', { url: frontend + '/admin/bookings' });
  await waitFor('protected redirect to real login', () =>
    evaluate(() => location.pathname === '/login' && !!document.querySelector('#login-password')), 25000);

  await check('UI44-02', 'Unauthorized API, CSRF rejection and two non-admin roles', async () => {
    await verifyDenied(401);
    assert.equal((await request('/sanctum/csrf-cookie')).status, 204);
    const noToken = await request('/login', { method: 'POST', body: credentials('admin', 'ADMIN') });
    assert.equal(noToken.status, 419);
    const roles = [];
    for (const [account, role] of [['host', 'HOST'], ['customer', 'CUSTOMER']]) {
      const loggedIn = await login(account, role);
      assert.equal(loggedIn.status, 200);
      assert.equal((await request('/api/me')).role, role);
      await verifyDenied(403, 'ADMIN_BOOKING_FORBIDDEN');
      await logout();
      roles.push(role);
    }
    for (const [account, code] of [['blocked', 'ACCOUNT_BLOCKED'], ['inactive', 'ACCOUNT_INACTIVE']]) {
      const denied = await login(account, 'ADMIN');
      assert.equal(denied.status, 403);
      assert.equal(denied.code, code);
      assert.equal((await request('/api/me')).status, 401);
    }
    return { missing_csrf: noToken.status, denied_roles: roles };
  });

  await check('UI44-02', 'Admin UI login, Booking menu and reload restore the real session', async () => {
    assert.equal(await evaluate(() => new URLSearchParams(location.search).get('returnTo')), '/admin/bookings');
    await value('#login-email', 'admin@example.test');
    await value('#login-password', password);
    await evaluate(() => document.querySelector('#login-password').form.requestSubmit());
    const postLoginPath = await waitFor('successful login and Admin navigation', () =>
      evaluate(() => location.pathname.startsWith('/admin') ? location.pathname : false));
    if (postLoginPath !== '/admin/bookings') {
      assert.equal(postLoginPath, '/admin');
      await waitFor('Booking link rendered in Admin menu', () =>
        evaluate(() => !!document.querySelector('a[href="/admin/bookings"]')));
      await evaluate(() => document.querySelector('a[href="/admin/bookings"]').click());
    }
    await expectCodes(firstPage);
    assert.equal(await evaluate(() => location.pathname), '/admin/bookings');
    assert.equal((await request('/api/me')).id, 1);
    assert.ok([...requests.values()].some((r) => r.path === '/login' && r.status === 200));
    await send('Page.reload', { ignoreCache: true });
    await expectCodes(firstPage);
    assert.equal((await request('/api/me')).role, 'ADMIN');
    assertRequest(list, {});
    assertRequest(options, { type: 'hosts' });
    return { post_login_path: postLoginPath, booking_menu_used: postLoginPath !== '/admin/bookings' };
  });

  await check('UI44-03', 'Snapshot, Vietnamese date/money, all statuses and pagination', async () => {
    assert.ok(await textIn(section, '777.777'));
    assert.ok(await textIn(section, '123.456'));
    assert.ok(await evaluate((selector) =>
      [...document.querySelectorAll(selector + ' article dd')].some((el) => el.textContent.includes('00:30') && el.textContent.includes('21/11')), section));
    assert.ok(await textIn(section, '21/11/2026'));
    const labels = ['Chờ xác nhận', 'Đã xác nhận', 'Đang lưu trú', 'Đã hoàn thành lưu trú', 'Đã hủy', 'Bị từ chối'];
    for (const label of labels) assert.ok(await textIn(section + ' article:has(h2)', label) ||
      await evaluate((section, label) => [...document.querySelectorAll(section + ' article p')]
        .some((el) => el.textContent.includes(label)), section, label), label);
    await button('Trang sau', section + ' nav[aria-label="Phân trang đơn đặt phòng"]');
    await expectCodes(bookingCodes(102, 101));
    assertRequest(list, { page: '2' });
    assert.equal(await evaluate((selector) => [...document.querySelectorAll(selector + ' article')]
      .find((card) => card.querySelector('h2').textContent === 'UI44-101').querySelector('dl > div:last-child dd').textContent,
    section), '—');
    await button('Đặt lại');
    await expectCodes(firstPage);
  });

  await check('UI44-03', 'Actual filter URLs, dependent options, inclusive date and reset', async () => {
    await setSearch('UI44-110');
    await expectCodes(bookingCodes(110));
    assertRequest(list, { search: 'UI44-110', page: '1' });
    await button('Đặt lại');
    await expectCodes(firstPage);
    await value(section + ' form select', 'COMPLETED');
    await expectCodes(bookingCodes(110, 104));
    assertRequest(list, { status: 'COMPLETED', page: '1' });
    await button('Đặt lại');
    await waitFor('host, property and customer options', async () =>
      await latestOptions('21') && await latestOptions('51') && await latestOptions('31'));
    await selectFilter(2, '31');
    await selectFilter(0, '21');
    await waitFor('host-scoped property response', () => evaluate((section) =>
      [...document.querySelectorAll(section + ' fieldset select')[1].options].map((el) => el.value).join(',') === ',51,53', section));
    assertRequest(options, { type: 'properties', host_id: '21' });
    await selectFilter(1, '51');
    await expectCodes(bookingCodes(110, 107, 104, 101));
    assertRequest(list, { host_id: '21', property_id: '51', customer_id: '31' });
    await selectFilter(0, '22');
    await waitFor('empty combined filter', () => textIn(section, 'Không có đơn đặt phòng trên trang này.'));
    const selected = await evaluate((section) => [...document.querySelectorAll(section + ' fieldset select')].map((el) => el.value), section);
    assert.deepEqual(selected, ['22', '', '31']);
    await button('Đặt lại');
    await expectCodes(firstPage);
    await value(from, '2026-11-22');
    await value(to, '2026-11-22');
    assert.deepEqual(await codes(), firstPage);
    await button('Tìm kiếm');
    await expectCodes(bookingCodes(111, 108, 105, 102));
    assertRequest(list, { check_in_from: '2026-11-22', check_in_to: '2026-11-22', page: '1' });
    await button('Đặt lại');
    await expectCodes(firstPage);
  });

  await check('UI44-04', 'Actual HTTP validation and focused invalid date field', async () => {
    const invalid = await request(list + '?page=0');
    assert.equal(invalid.status, 422);
    assert.equal(invalid.code, 'ADMIN_BOOKING_INVALID_QUERY');
    assert.ok(invalid.fields.includes('page'));
    await value(from, '2026-11-23');
    await value(to, '2026-11-21');
    const before = requests.size;
    await button('Tìm kiếm');
    assert.equal(await evaluate(() => document.activeElement.name), 'check_in_to');
    assert.ok(await evaluate((selector) => {
      const field = document.querySelector(selector);
      return field.getAttribute('aria-invalid') === 'true' &&
        document.getElementById(field.getAttribute('aria-describedby'))?.getAttribute('role') === 'alert';
    }, to));
    assert.equal(requests.size, before);
    await button('Đặt lại');
    await expectCodes(firstPage);
  });

  await check('UI44-04', 'One injected list/options network failure, independent retry and empty', async () => {
    const held = await intercept(list, { search: 'UI44-111' });
    await setSearch('UI44-111');
    await waitFor('held Laravel list response', () => held.hit);
    assert.equal(held.hit.responseStatusCode, 200);
    assert.ok(await textIn(section + ' [aria-busy="true"]', 'Đang tải danh sách'));
    await release();
    await expectCodes(bookingCodes(111));

    const listFault = await intercept(list, { search: 'UI44-112' }, true);
    await setSearch('UI44-112');
    await waitFor('injected list failure', () => listFault.done);
    await waitFor('list retry', () => textIn(section + ' > div:last-child [role="alert"]', 'Thử lại'));
    await release();
    await button('Thử lại');
    await expectCodes(bookingCodes(112));
    assertRequest(list, { search: 'UI44-112' });

    const optionFault = await intercept(options, { type: 'hosts', search: 'QA' }, true);
    await value(fieldset(1) + ' input', 'QA');
    await button('Tìm Host');
    await waitFor('injected options failure', () => optionFault.done);
    await waitFor('host options retry', () => textIn(fieldset(1) + ' [role="alert"]', 'Thử lại Host'));
    assert.deepEqual(await codes(), bookingCodes(112));
    await release();
    await button('Thử lại Host');
    await waitFor('retry loads real host options', () => latestOptions('21'));
    assertRequest(options, { type: 'hosts', search: 'QA' });
    await setSearch('no-uc44-booking');
    await waitFor('server-backed empty list', () => textIn(section, 'Không có đơn đặt phòng trên trang này.'));
    assert.deepEqual(await codes(), []);
    assertRequest(list, { search: 'no-uc44-booking' });
    await button('Đặt lại');
    await expectCodes(firstPage);
  });

  await check('UI44-05', 'Cancel held Laravel list query and Property option when selection changes', async () => {
    const oldList = await intercept(list, { search: 'UI44-101' });
    await setSearch('UI44-101');
    await waitFor('held old list response', () => oldList.hit);
    assert.equal(oldList.hit.responseStatusCode, 200);
    await setSearch('UI44-112');
    await expectCodes(bookingCodes(112));
    await waitFor('old list network cancellation', () => requests.get(oldList.hit.networkId)?.cancelled);
    await release(true);
    assert.deepEqual(await codes(), bookingCodes(112));

    await button('Đặt lại');
    await waitFor('host selectors before race', () => latestOptions('21'));
    const oldOptions = await intercept(options, { type: 'properties', host_id: '21' });
    await selectFilter(0, '21');
    await waitFor('held old properties response', () => oldOptions.hit);
    assert.equal(oldOptions.hit.responseStatusCode, 200);
    await selectFilter(0, '22');
    await waitFor('new host property options', () => evaluate((section) =>
      [...document.querySelectorAll(section + ' fieldset select')[1].options].map((el) => el.value).join(',') === ',52', section));
    await waitFor('old options network cancellation', () => requests.get(oldOptions.hit.networkId)?.cancelled);
    await release(true);
    assertRequest(options, { type: 'properties', host_id: '22' });
    await button('Đặt lại');
    await expectCodes(firstPage);
  });

  await check('UI44-06', 'Responsive viewports with populated lists and filter options', async () => {
    await waitFor('populated options before resizing', async () =>
      await latestOptions('21') && await latestOptions('51') && await latestOptions('31'));
    const displays = [];
    for (const [width, height, columns] of [[360, 800, 1], [768, 900, 2], [1440, 900, 3]]) {
      const measured = await layout(width, height);
      displays.push({ ...measured, passed: measured.width === width && fitsBookingLayout(measured, columns) });
    }
    assert.ok(displays.every(({ passed }) => passed),
      'Horizontal overflow or controls clipped: ' + JSON.stringify(displays));
    return displays;
  });

  await check('UI44-06', 'Actual Edge 200% default zoom (isolated profile)', checkBrowserZoom);

  await check('UI44-06', 'Keyboard changes focus visibly and Enter submits UI search', async () => {
    await layout(1440, 900);
    await value(search, 'UI44-110');
    await evaluate((selector) => document.querySelector(selector).focus(), search);
    await key('Tab', 'Tab', 9);
    const focus = await evaluate(() => {
      const el = document.activeElement;
      const css = getComputedStyle(el);
      return { label: el.closest('label')?.textContent.trim(), focusVisible: el.matches(':focus-visible'),
        outline: css.outlineStyle !== 'none' || css.boxShadow !== 'none' };
    });
    assert.ok(focus.label?.startsWith('Trạng thái') && focus.focusVisible && focus.outline, JSON.stringify(focus));
    await key('Tab', 'Tab', 9);
    assert.ok(await evaluate(() => document.activeElement.closest('label')?.textContent.startsWith('Số đơn mỗi trang')));
    await evaluate((selector) => document.querySelector(selector).focus(), search);
    await key('Enter', 'Enter', 13);
    await expectCodes(bookingCodes(110));
    await button('Đặt lại');
    await expectCodes(firstPage);
  });

  await check('UI44-05', 'Page change cancels held response and logout removes the prior session', async () => {
    await button('Đặt lại');
    await expectCodes(firstPage);
    const paging = await intercept(list, { page: '2' });
    await button('Trang sau', section + ' nav[aria-label="Phân trang đơn đặt phòng"]');
    await waitFor('hold pagination response', () => paging.hit);
    assert.equal(paging.hit.responseStatusCode, 200);
    await button('Đặt lại');
    await expectCodes(firstPage);
    await waitFor('old page request was cancelled', () => requests.get(paging.hit.networkId)?.cancelled);
    await release(true);

    const finishing = await intercept(list, { search: 'UI44-101' });
    await setSearch('UI44-101');
    await waitFor('held response during logout', () => finishing.hit);
    const existingRequests = new Set(requests.keys());
    await button('Đăng xuất', 'body');
    await waitFor('new UI logout response and old list cancellation', () => [...requests].some(([id, r]) =>
      !existingRequests.has(id) && r.method === 'POST' && r.path === '/logout' && r.status === 200 &&
      requests.get(finishing.hit.networkId)?.cancelled));
    await waitFor('protected route exits on logout', () => evaluate(() => location.pathname === '/login'));
    await release(true);
    await verifyDenied(401);
    assert.deepEqual(await codes(), []);
  });
}

try {
  await main();
} catch (error) {
  checkpoints.push({ id: 'HARNESS', status: 'FAIL', error: error.message });
} finally {
  socket?.close();
}

const passed = checkpoints.length > 0 && checkpoints.every((check) => check.status === 'PASS');
process.stdout.write(JSON.stringify({ passed, browser: browserVersion, checkpoints, requests: [...requests.values()] }, null, 2) + '\n');
process.exitCode = passed ? 0 : 1;

