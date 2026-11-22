import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { setTimeout as delay } from 'node:timers/promises';

const manifestPath = process.env.STAYHUB_TV3_AC_MANIFEST;
const manifest = JSON.parse(readFileSync(manifestPath, 'utf8'));
const directory = dirname(manifestPath);
const frontend = manifest.frontend_url;
const backend = manifest.backend_url;
const debug = `http://127.0.0.1:${process.env.STAYHUB_UC17_DEBUG_PORT}`;
const password = process.env.STAYHUB_UC17_BROWSER_PASSWORD;
const origin = /^http:\/\/127\.0\.0\.1:[1-9]\d{0,4}$/;
const guestBrowser = manifest.suite === 'GuestChatBrowser';
const liveMode = ['ChatLiveDryRun', 'ChatLive'].includes(manifest.suite);
assert.ok((liveMode || ['ChatBrowser', 'GuestChatBrowser'].includes(manifest.suite)) && [frontend, backend, debug].every((url) => origin.test(url)));
assert.ok(frontend !== backend && password?.length >= 32);

const chatPath = '/api/customer/chat/messages';
const chatUrl = backend + chatPath;
const publicChatPath = '/api/public/chat/messages';
const publicChatUrl = backend + publicChatPath;
const launcher = 'Trợ lý StayHub';
const dialog = '[role="dialog"]';
const input = `${dialog} textarea`;
const messages = `${dialog} [role="log"]`;
const alert = `${dialog} [role="alert"]`;
const requests = new Map();
const loginResponses = [];
const checkpoints = [];
let nextHold;
let socket;
let send;
let browserVersion;

async function waitFor(label, read, timeout = 12000) {
    const deadline = Date.now() + timeout;
    do {
        const value = await read();
        if (value) return value;
        await delay(75);
    } while (Date.now() < deadline);
    throw new Error(`Timed out waiting for ${label}`);
}

async function inspect(id, label, check) {
    try {
        const evidence = await check();
        checkpoints.push({ id, label, status: evidence?.content_pass === false ? 'FAIL' : 'PASS', evidence });
    } catch (error) {
        checkpoints.push({ id, label, status: 'FAIL', error: String(error.stack ?? error).replaceAll(password, '[REDACTED]') });
        if (nextHold?.paused && !nextHold.paused.released) {
            await release(nextHold.paused).catch(() => undefined);
        }
        nextHold = undefined;
    }
}

function audit() {
    const report = execFileSync(process.env.STAYHUB_UC17_PHP, ['tests/Support/Uc17BrowserServer.php', 'audit'], {
        cwd: new URL('../../../backend/', import.meta.url), encoding: 'utf8', timeout: 12000,
        stdio: ['ignore', 'pipe', 'ignore'], windowsHide: true,
    });
    const state = JSON.parse(report);
    assert.equal(state.outbound_attempts, 0);
    return state;
}

function rows(state) {
    return { sessions: state.sessions, messages: state.messages, turns: state.turns, fake_calls: state.fake_calls };
}

function assertStored(state, payload, owner, result, answer, attempts = 1) {
    const turns = state.turns.filter((turn) => turn.request_id === payload.request_id);
    assert.equal(turns.length, 1);
    const [turn] = turns;
    assert.equal(turn.user_id, owner);
    assert.equal(turn.status, 'SUCCEEDED');
    assert.equal(turn.attempts, attempts);
    assert.deepEqual(state.sessions.find((session) => session.id === turn.chat_session_id), { id: turn.chat_session_id, user_id: owner });
    assert.deepEqual(state.messages.find((message) => message.id === turn.user_message_id), {
        id: turn.user_message_id, chat_session_id: turn.chat_session_id, sender: 'USER', message: payload.message,
    });
    assert.deepEqual(state.messages.find((message) => message.id === turn.assistant_message_id), {
        id: turn.assistant_message_id, chat_session_id: turn.chat_session_id, sender: 'ASSISTANT', message: answer,
    });
    assert.equal(state.fake_calls.filter((question) => question === payload.message).length, attempts);
    if (result) {
        assert.equal(result.request_id, payload.request_id);
        assert.equal(result.session_id, turn.chat_session_id);
        assert.equal(result.user_message.id, turn.user_message_id);
        assert.equal(result.assistant_message.id, turn.assistant_message_id);
    }
    return { request_id: turn.request_id, session_id: turn.chat_session_id, message_ids: [turn.user_message_id, turn.assistant_message_id], attempts };
}

async function connect() {
    browserVersion = (await (await fetch(debug + '/json/version')).json()).Browser;
    const pages = await (await fetch(debug + '/json/list')).json();
    const page = pages.find((target) => target.type === 'page' && target.url === 'about:blank');
    assert.ok(page?.webSocketDebuggerUrl);
    socket = new WebSocket(page.webSocketDebuggerUrl);
    await new Promise((resolve, reject) => {
        socket.addEventListener('open', resolve, { once: true });
        socket.addEventListener('error', reject, { once: true });
    });
    const pending = new Map();
    let sequence = 0;
    socket.addEventListener('message', ({ data }) => {
        const { id, method, params, error, result } = JSON.parse(data);
        const command = pending.get(id);
        if (command) {
            pending.delete(id);
            clearTimeout(command.timer);
            if (error) command.reject(new Error(`${command.method}: ${error.message}`));
            else command.resolve(result);
        }
        if (method === 'Network.requestWillBeSent' && [chatUrl, publicChatUrl].includes(params.request.url) && params.request.method === 'POST') {
            const payload = JSON.parse(params.request.postData ?? '{}');
            const xsrf = Object.keys(params.request.headers).some((name) => name.toLowerCase() === 'x-xsrf-token');
            requests.set(params.requestId, { id: params.requestId, payload, xsrf });
        }
        if (method === 'Network.responseReceived' && params.response.url === `${backend}/login` && params.type === 'XHR') {
            loginResponses.push(params.response.status);
        }
        const request = requests.get(params?.requestId);
        if (request && method === 'Network.responseReceived') request.status = params.response.status;
        if (request && method === 'Network.loadingFinished') request.finished = true;
        if (request && method === 'Network.loadingFailed') request.failed = params.errorText;
        if (method === 'Fetch.requestPaused') {
            if (params.request.method === 'POST' && nextHold && params.responseStatusCode === nextHold.status && !nextHold.paused
                && JSON.parse(params.request.postData ?? '{}').message === nextHold.question) {
                nextHold.paused = { id: params.requestId, networkId: params.networkId, status: params.responseStatusCode };
            } else {
                send('Fetch.continueRequest', { requestId: params.requestId }).catch(() => undefined);
            }
        }
    });
    send = (method, params = {}) => new Promise((resolve, reject) => {
        const id = ++sequence;
        const timer = setTimeout(() => {
            pending.delete(id);
            reject(new Error(`CDP timeout: ${method}`));
        }, 9000);
        pending.set(id, { method, resolve, reject, timer });
        socket.send(JSON.stringify({ id, method, params }));
    });
    await send('Network.enable');
    if (liveMode) {
        // Keep QA requests on the runner's two random loopback ports, even if a dev route is misconfigured.
        await send('Network.setBlockedURLs', { urls: ['http://localhost:*/*', 'http://127.0.0.1:8000/*', 'http://127.0.0.1:5173/*'] });
    }
    await send('Page.enable');
    await send('Page.bringToFront');
    await send('Emulation.setFocusEmulationEnabled', { enabled: true });
    await send('Fetch.enable', { patterns: [chatUrl, publicChatUrl].map((urlPattern) => ({ urlPattern, requestStage: 'Response' })) });
}

async function evaluate(fn, ...args) {
    const expression = `(${fn.toString()})(...${JSON.stringify(args)})`;
    const result = await send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
    if (result.exceptionDetails) throw new Error(result.exceptionDetails.exception?.description ?? 'Browser evaluation failed');
    return result.result.value;
}

const click = (label, selector = 'button') => evaluate((label, selector) => {
    const candidate = [...document.querySelectorAll(selector)].find((element) => element.textContent.trim() === label && element.getClientRects().length);
    if (!candidate || candidate.disabled) return false;
    candidate.click();
    return true;
}, label, selector);
const hasButton = (label) => evaluate((label) => [...document.querySelectorAll('button')].some((el) => el.textContent.trim() === label), label);
const hasText = (selector, phrase) => evaluate((selector, phrase) => document.querySelector(selector)?.textContent.includes(phrase) ?? false, selector, phrase);
const value = (selector, text) => evaluate((selector, text) => {
    const element = document.querySelector(selector);
    if (!element) throw new Error(`Input not found: ${selector}`);
    const prototype = element instanceof HTMLTextAreaElement ? HTMLTextAreaElement.prototype : HTMLInputElement.prototype;
    Object.getOwnPropertyDescriptor(prototype, 'value').set.call(element, text);
    element.dispatchEvent(new Event('input', { bubbles: true }));
}, selector, text);
const navigate = (path) => send('Page.navigate', { url: frontend + path });
const width = (size) => send('Emulation.setDeviceMetricsOverride', { width: size, height: 800, deviceScaleFactor: 1, mobile: false });

async function key(code, virtual, modifiers = 0) {
    const params = { key: code, code, windowsVirtualKeyCode: virtual, nativeVirtualKeyCode: virtual, modifiers };
    await send('Input.dispatchKeyEvent', { type: 'rawKeyDown', ...params });
    if (code === 'Enter') await send('Input.dispatchKeyEvent', { type: 'char', ...params, text: '\r', unmodifiedText: '\r' });
    await send('Input.dispatchKeyEvent', { type: 'keyUp', ...params });
}

async function openWidget() {
    if (!await evaluate((selector) => Boolean(document.querySelector(selector)), input)) {
        await waitFor('widget launcher', () => click(launcher));
    }
    await waitFor('widget dialog', () => evaluate((selector) => Boolean(document.querySelector(selector)), input));
}

async function closeWidget() {
    await evaluate(() => document.querySelector('[aria-label="Đóng trợ lý"]').click());
    await waitFor('dialog closed', async () => !await evaluate((selector) => Boolean(document.querySelector(selector)), input));
}

async function login(account, role = 'customer', onLoginPage = false) {
    if (!onLoginPage) await navigate('/login?role=' + role);
    await waitFor('real login form', () => evaluate((role) => location.pathname === '/login'
        && new URLSearchParams(location.search).get('role') === role
        && Boolean(document.querySelector('#login-password')?.form.querySelector('button[type="submit"]:enabled')), role), 25000);
    const previousLogins = loginResponses.length;
    await value('#login-email', `${account}@example.test`);
    await value('#login-password', password);
    await evaluate(() => document.querySelector('#login-password').form.requestSubmit());
    const status = await waitFor(`${role} login HTTP response`, () => loginResponses[previousLogins], 15000);
    assert.equal(status, 200, `Laravel login for ${role}`);
    await waitFor(`${role} route after real login`, () => evaluate((role) => location.pathname === '/' + role
        || location.pathname.startsWith('/' + role + '/'), role), 18000);
}

function api(path, method = 'GET', body, csrf = true) {
    return evaluate(async (url, method, body, csrf) => {
        const headers = { Accept: 'application/json' };
        if (body !== undefined && body !== null) headers['Content-Type'] = 'application/json';
        const cookie = document.cookie.split('; ').find((part) => part.startsWith('XSRF-TOKEN='));
        if (csrf && cookie) headers['X-XSRF-TOKEN'] = decodeURIComponent(cookie.slice('XSRF-TOKEN='.length));
        const response = await fetch(url, { method, headers, credentials: 'include', body: body == null ? undefined : JSON.stringify(body) });
        return { status: response.status, retry_after: response.headers.get('Retry-After'), data: await response.json().catch(() => null) };
    }, backend + path, method, body ?? null, csrf);
}

const probe = (question) => ({ request_id: crypto.randomUUID(), session_id: null, message: question });
const chatRequests = (question) => [...requests.values()].filter(({ payload }) => payload.message === question);
const requestAt = (question, index = 0) => waitFor(`POST for ${question} #${index + 1}`, () => chatRequests(question)[index]);

async function reply(request, expected, code) {
    await waitFor(`response for ${request.payload.message}`, () => request.finished || request.failed);
    assert.equal(request.failed, undefined);
    assert.equal(request.status, expected);
    const body = await send('Network.getResponseBody', { requestId: request.id });
    const data = JSON.parse(body.base64Encoded ? Buffer.from(body.body, 'base64').toString('utf8') : body.body);
    if (code) assert.equal(data.code, code);
    return data.data;
}

async function submit(question) {
    await value(input, question);
    await waitFor('enabled submit', () => click('Gửi', `${dialog} button`));
    return requestAt(question);
}

async function retry(question, index) {
    await waitFor('enabled retry', () => click('Thử lại', `${dialog} button`), 70000);
    const previous = await requestAt(question);
    const request = await requestAt(question, index);
    assert.deepEqual(request.payload, previous.payload);
    return request;
}

async function assertVisiblePair(question, answer) {
    await waitFor('assistant answer in widget', () => hasText(messages, answer));
    const log = await evaluate((selector) => Array.from(document.querySelectorAll(`${selector} p`), (element) => element.textContent), messages);
    assert.equal(log.filter((line) => line === `Bạn: ${question}`).length, 1);
    assert.equal(log.filter((line) => line === `Trợ lý: ${answer}`).length, 1);
}

function hold(question, status = 201) {
    assert.equal(nextHold, undefined);
    nextHold = { question, status };
    return nextHold;
}

async function release(paused, fail = false) {
    if (fail) await send('Fetch.failRequest', { requestId: paused.id, errorReason: 'Failed' });
    else await send('Fetch.continueRequest', { requestId: paused.id });
    paused.released = true;
    nextHold = undefined;
}

async function observeCountdown(question) {
    const seconds = await waitFor('visible Retry-After countdown', () => evaluate((selector) => {
        const text = document.querySelector(selector)?.textContent ?? '';
        return Number(text.match(/Thử lại sau (\d+) giây/)?.[1]) || 0;
    }, alert));
    assert.equal(await click('Thử lại', `${dialog} button`), false);
    assert.equal(chatRequests(question).length, 1);
    return seconds;
}

async function guestChecks() {
    const mood = 'Hôm nay tôi buồn quá, bạn gợi ý tôi nên đi đâu đi?';
    const answer = 'Bạn có thể đi dạo hoặc ghé một quán cà phê yên tĩnh.';
    const safePrompt = 'Gợi ý hoạt động nhẹ nhàng gần nhà, bằng tiếng Việt.';
    const roomQuestion = 'Tìm phòng cho 2 người dưới 800 nghìn';
    async function publicPage(path) {
        await navigate(path);
        await waitFor(path + ' launcher', () => hasButton(launcher), 25000);
    }
    async function ask(question, expected = 200) {
        const index = chatRequests(question).length;
        await value(input, question);
        await waitFor('enabled guest send', () => click('Gửi', `${dialog} button`));
        return reply(await requestAt(question, index), expected);
    }
    async function follow(label, path, content) {
        await waitFor(label, () => click(label, `${dialog} a`));
        await waitFor(path, () => evaluate((path) => location.pathname === path, path));
        if (content) await waitFor(content, () => hasText('main', content));
    }

    await inspect('GUEST-EDGE-01', 'Mascot, reduced motion, speakers and viewport', async () => {
        await publicPage('/');
        await waitFor('mascot image', () => evaluate(() => document.querySelector('.stayhub-chat-launcher img')?.naturalWidth > 0));
        assert.equal(await evaluate(() => getComputedStyle(document.querySelector('.stayhub-chat-launcher img')).animationName), 'stayhub-chat-greeting');
        await send('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-reduced-motion', value: 'reduce' }] });
        assert.equal(await evaluate(() => getComputedStyle(document.querySelector('.stayhub-chat-launcher img')).animationName), 'none');
        await publicPage('/search');
        await evaluate(() => document.querySelector('.stayhub-chat-launcher').focus());
        await key('Enter', 13);
        await waitFor('keyboard opens chat', () => evaluate((selector) => Boolean(document.querySelector(selector)), input));
        assert.equal((await ask(mood)).message, answer);
        await assertVisiblePair(mood, answer);
        const sizes = [];
        for (const size of [360, 768, 1440]) {
            await width(size);
            await key('Tab', 9);
            const geometry = await evaluate((dialog) => {
                const box = document.querySelector(dialog);
                const own = box.querySelector('[data-sender="user"] .stayhub-chat-bubble');
                const bot = [...box.querySelectorAll('[data-sender="assistant"] .stayhub-chat-bubble')].at(-1);
                const rect = box.getBoundingClientRect();
                return { width: innerWidth, contained: rect.left >= 0 && rect.right <= innerWidth && rect.bottom <= innerHeight,
                    user_right: own.getBoundingClientRect().right > bot.getBoundingClientRect().right,
                    distinct: getComputedStyle(own).backgroundColor !== getComputedStyle(bot).backgroundColor,
                    focus: box.contains(document.activeElement), overflow: box.scrollWidth - box.clientWidth };
            }, dialog);
            assert.deepEqual(geometry, { width: size, contained: true, user_right: true, distinct: true, focus: true, overflow: 0 });
            sizes.push(size);
        }
        await key('Escape', 27);
        await waitFor('launcher focus restored', () => evaluate(() => document.activeElement.matches('.stayhub-chat-launcher')));
        assert.deepEqual(audit().fake_calls, [safePrompt]);
        return { widths: sizes, reduced_motion: true, keyboard: true };
    });

    await inspect('GUEST-EDGE-02', 'Guest Catalog card and both detail links', async () => {
        for (const [label, path, content] of [
            ['Xem khách sạn', '/properties/71', 'Khách sạn Giấc Mơ QA'],
            ['Xem phòng', '/rooms/81', 'Phòng Bình Minh QA'],
        ]) {
            await publicPage('/search');
            await openWidget();
            const result = await ask(roomQuestion);
            assert.deepEqual(result.rooms.map((entry) => [entry.property_id, entry.room_id]), [[71, 81]]);
            await waitFor('public catalog card', () => hasText(messages, 'Phòng Bình Minh QA'));
            assert.equal(await hasText(messages, 'Phòng Ẩn QA'), false);
            if (label === 'Xem khách sạn') {
                await width(360);
                const screenshot = await send('Page.captureScreenshot', { format: 'png' });
                writeFileSync(join(directory, 'guest-widget-360.png'), Buffer.from(screenshot.data, 'base64'));
                await width(1440);
            }
            await follow(label, path, content);
            assert.equal(await hasButton(launcher), false);
        }
        await publicPage('/');
        await openWidget();
        assert.equal((await ask('Tìm phòng cho 20 người')).action, 'search');
        await follow('Tìm chỗ nghỉ', '/search');
        assert.deepEqual(audit().fake_calls, [safePrompt]);
        return { room: 81, property: 71, hidden_excluded: true, fallback: '/search' };
    });

    await inspect('GUEST-EDGE-03', 'Request canceled on route change and guest CSRF enforced', async () => {
        await send('Network.clearBrowserCookies');
        await publicPage('/');
        assert.equal((await api('/sanctum/csrf-cookie')).status, 204);
        assert.equal((await api(publicChatPath, 'POST', probe(mood), false)).status, 419);
        await openWidget();
        const delayedQuestion = 'Tôi muốn đi dạo gần nhà';
        const delayed = hold(delayedQuestion, 200);
        const pending = await submit(delayedQuestion);
        await waitFor('guest response waiting at browser', () => delayed.paused);
        await follow('Đăng nhập để được hỗ trợ thêm', '/login');
        await waitFor('guest request canceled', () => pending.failed);
        await release(delayed.paused).catch(() => { nextHold = undefined; });
        await waitFor('login form', () => evaluate(() => Boolean(document.querySelector('#login-email'))));
        assert.equal(await hasButton(launcher), false);
        await publicPage('/');
        await openWidget();
        assert.equal(await hasText(messages, answer), false);
        await closeWidget();
        assert.deepEqual(audit().fake_calls, [safePrompt, safePrompt]);
        return { csrf: 419, canceled: pending.failed };
    });

    await inspect('GUEST-EDGE-04', 'Personal questions point to the correct account routes', async () => {
        await publicPage('/');
        await openWidget();
        assert.equal((await ask('Phòng tôi đã đặt là khi nào?')).action, 'bookings');
        const bookings = await waitFor('bookings login link', () => evaluate(() => [...document.querySelectorAll('[role="dialog"] a')]
            .find((link) => link.textContent === 'Đăng nhập để xem đơn đặt phòng')?.getAttribute('href')));
        assert.equal(new URL(bookings, frontend).searchParams.get('returnTo'), '/customer/bookings');
        assert.equal((await ask('Hóa đơn nào tôi chưa thanh toán?')).action, 'invoices');
        await follow('Đăng nhập để xem hóa đơn', '/login');
        assert.equal(await evaluate(() => new URLSearchParams(location.search).get('returnTo')), '/customer/invoices');
        await login('customer-a', 'customer', true);
        assert.equal(await evaluate(() => location.pathname), '/customer/invoices');
        await openWidget();
        assert.equal(await hasText(messages, 'Hóa đơn nào tôi chưa thanh toán?'), false);
        assert.deepEqual(audit().fake_calls, [safePrompt, safePrompt]);
        return { bookings: '/customer/bookings', invoices: '/customer/invoices', no_guest_transcript_after_login: true };
    });

    await inspect('GUEST-EDGE-05', 'Customer shares frame and catalog cards without persisting public chat', async () => {
        await openWidget();
        await waitFor('Customer recommendation mode', () => click('Gợi ý chỗ nghỉ'));
        assert.deepEqual((await ask(roomQuestion)).rooms.map(({ room_id }) => room_id), [81]);
        await waitFor('Customer public card', () => hasText(messages, 'Phòng Bình Minh QA'));
        await waitFor('back to Customer chat', () => click('Về cuộc trò chuyện'));
        assert.equal(await hasText(messages, 'Phòng Bình Minh QA'), false);
        const saved = audit();
        assert.deepEqual([saved.sessions.length, saved.turns.length, saved.messages.length], [0, 0, 0]);
        assert.deepEqual(saved.fake_calls, [safePrompt, safePrompt]);
        return { customer_card: true, chat_rows: 0, fake_calls: saved.fake_calls.length, outbound_attempts: saved.outbound_attempts };
    });
}

async function liveChecks() {
    const sample = 'Nêu 3 điều nên kiểm tra trước khi đặt phòng, bằng tiếng Việt và tối đa 60 từ.';
    const digest = (value) => createHash('sha256').update(value).digest('hex');

    function verifyAnswer(text) {
        assert.ok(typeof text === 'string' && Boolean(text.trim()));
        const vietnamese = /[ăâđêôơưàáảãạèéẻẽẹìíỉĩịòóỏõọùúủũụỳýỷỹỵ]/iu.test(text);
        const words = text.match(/\p{L}+/gu)?.length ?? 0;
        const normalized = text.toLowerCase().normalize('NFD').replace(/\p{M}/gu, '').replaceAll('đ', 'd');
        const topics = [
            ['chi phí', /\b(gia|chi phi|phu phi|thue|tong tien)\b/],
            ['đổi, hủy hoặc hoàn tiền', /\b(huy|hoan tien|doi ngay|doi lich)\b/],
            ['giờ nhận hoặc trả phòng', /\b(gio (nhan|tra)|check[ -]?(in|out))\b/],
            ['vị trí và di chuyển', /\b(vi tri|dia chi|khoang cach|di chuyen|giao thong)\b/],
            ['tiện nghi và loại phòng', /\b(tien nghi|wifi|bua sang|loai phong|loai giuong|dien tich|bai do xe|suc chua|so khach)\b/],
            ['đánh giá chỗ nghỉ', /\b(danh gia|nhan xet|phan hoi|uy tin)\b/],
            ['ngày lưu trú và phòng trống', /\b(phong trong|ngay luu tru|ngay nhan|ngay tra|tinh trang phong)\b/],
            ['thanh toán hoặc đặt cọc', /\b(thanh toan|dat coc|dat truoc|tra truoc)\b/],
            ['điều kiện lưu trú', /\b(tre em|thu cung|hut thuoc|giay to|quy dinh luu tru|chinh sach luu tru)\b/],
        ].filter(([, pattern]) => pattern.test(normalized)).map(([topic]) => topic);
        if (manifest.suite === 'ChatLiveDryRun') {
            assert.ok(words > 60, 'The dry run must exercise an answer above the preferred length.');
        }
        return { answer_words: words, answer_topics: topics, content_pass: vietnamese && topics.length >= 3 };
    }

    async function attempt(flow, expected) {
        const index = chatRequests(sample).length;
        await value(input, sample);
        await waitFor('enabled one-shot submit', () => click('Gửi', `${dialog} button`));
        const request = await requestAt(sample, index);
        await waitFor('one-shot HTTP result', () => request.finished || request.failed, 23000);
        assert.equal(chatRequests(sample).length, index + 1);
        const { gemini } = audit();
        const observed = gemini[flow];
        assert.equal(observed.attempts, 1, `${flow} Gemini attempt count`);
        if (request.status !== expected || request.failed) {
            const body = request.finished ? await send('Network.getResponseBody', { requestId: request.id }) : null;
            const code = body && JSON.parse(body.base64Encoded ? Buffer.from(body.body, 'base64').toString('utf8') : body.body)?.code;
            const safeCode = typeof code === 'string' && /^[A-Z_]{1,60}$/.test(code) ? ` ${code}` : '';
            throw new Error(`Laravel ${request.status ?? 'no response'}${safeCode}. Gemini ${observed.provider?.status ?? 'no response'}.`);
        }
        assert.equal(observed.provider?.status, 200);
        assert.equal(observed.provider?.finish_reason, 'STOP');
        const data = await reply(request, expected);
        return { data, request, provider: observed.provider };
    }

    await inspect('LIVE-GUEST', 'One provider call from anonymous widget, no chat history', async () => {
        await navigate('/');
        await openWidget();
        const before = audit();
        if (manifest.suite === 'ChatLiveDryRun') {
            assert.equal((await api('/sanctum/csrf-cookie')).status, 204);
            const rejected = await api(publicChatPath, 'POST', probe('Hôm nay tôi buồn quá, bạn gợi ý tôi nên đi đâu đi?'));
            assert.equal(rejected.status, 503);
            assert.equal(audit().gemini.guest.attempts, 0);
        }
        const { data, provider } = await attempt('guest', 200);
        await assertVisiblePair(sample, data.message);
        assert.deepEqual(rows(audit()), rows(before));
        assert.equal(audit().gemini.customer.attempts, 0);
        return { laravel_status: 200, provider, text_visible: true, chat_rows_added: 0, ...verifyAnswer(data.message) };
    });

    await inspect('LIVE-CUSTOMER', 'One provider call, one committed pair, no outbound call on replay', async () => {
        await login('customer-a');
        await openWidget();
        const { data, request, provider } = await attempt('customer', 201);
        await assertVisiblePair(sample, data.assistant_message.message);
        const saved = audit();
        assert.deepEqual(saved.sessions, [{ id: data.session_id, user_id: 31 }]);
        assert.deepEqual(saved.turns, [{ request_id: request.payload.request_id, user_id: 31, status: 'SUCCEEDED', attempts: 1,
            chat_session_id: data.session_id, user_message_id: data.user_message.id, assistant_message_id: data.assistant_message.id }]);
        assert.equal(saved.messages.length, 2);
        for (const source of [data.user_message, data.assistant_message]) {
            const row = saved.messages.find(({ id }) => id === source.id);
            assert.ok(row && row.sender === source.sender && row.chat_session_id === data.session_id);
            assert.ok(row.body_sha256 === digest(source.message));
        }
        assert.ok(data.user_message.message === sample);
        const replay = await api(chatPath, 'POST', request.payload);
        assert.equal(replay.status, 200);
        assert.ok(JSON.stringify(replay.data.data) === JSON.stringify(data));
        const after = audit();
        assert.deepEqual(rows(after), rows(saved));
        assert.equal(after.gemini.customer.attempts, 1);
        return { laravel_status: 201, provider, replay_status: 200, chat_rows_added: 2,
            session_id: data.session_id, message_ids: [data.user_message.id, data.assistant_message.id], ...verifyAnswer(data.assistant_message.message) };
    });
}

try {
    await connect();
    await width(1440);

    if (liveMode) {
        await liveChecks();
    } else if (guestBrowser) {
        await guestChecks();
    } else {
    await inspect('QUI-01', 'Customer access, session cookie and CSRF', async () => {
        const before = rows(audit());
        await navigate('/customer');
        await waitFor('guest login redirect', () => evaluate(() => location.pathname === '/login' && Boolean(document.querySelector('#login-password'))), 25000);
        assert.equal(await hasButton(launcher), false);
        await api('/sanctum/csrf-cookie');
        assert.equal((await api(chatPath, 'POST', probe('QA17 guest'))).status, 401);
        const rejected = {};
        for (const role of ['host', 'admin']) {
            await login(role, role);
            await navigate('/customer');
            await waitFor('role guard', () => hasText('body', 'Khu vực giới hạn'));
            assert.equal(await hasButton(launcher), false);
            rejected[role] = (await api(chatPath, 'POST', probe(`QA17 ${role}`))).status;
            assert.equal(rejected[role], 403);
            assert.equal((await api('/logout', 'POST', {})).status, 200);
        }
        await login('customer-a');
        await waitFor('Customer widget', () => hasButton(launcher));
        const { cookies } = await send('Network.getCookies', { urls: [backend] });
        assert.ok(cookies.some(({ name, httpOnly }) => name === `uc17_${manifest.run_id}_session` && httpOnly));
        assert.ok(cookies.some(({ name }) => name === 'XSRF-TOKEN'));
        assert.equal((await api(chatPath, 'POST', probe('QA17 thiếu CSRF'), false)).status, 419);
        assert.deepEqual(rows(audit()), before);
        return { guest: 401, ...rejected, missing_csrf: 419, database_session_cookie: true, xsrf_cookie: true, no_chat_writes: true };
    });

    await inspect('QUI-02', 'One POST while busy, two committed turns in one session', async () => {
        await openWidget();
        assert.equal(await hasText(messages, 'Bạn cần tư vấn gì'), true);
        const question = 'QA17 hỏi đầu';
        const before = audit();
        await value(input, question);
        await evaluate((selector) => {
            const form = document.querySelector(selector).form;
            form.requestSubmit();
            form.requestSubmit();
        }, input);
        const first = await requestAt(question);
        assert.equal(first.payload.session_id, null);
        assert.equal(first.xsrf, true);
        await waitFor('fake provider processing', () => readFileSync(join(directory, 'fake-provider.jsonl'), 'utf8').trim().length > 0);
        assert.equal(await hasText(`${dialog} [role="status"]`, 'đang trả lời'), true);
        assert.equal(await click('Gửi', `${dialog} button`), false);
        assert.equal(chatRequests(question).length, 1);
        writeFileSync(join(directory, 'release-first-answer'), 'release\n', { flag: 'wx' });
        const result = await reply(first, 201);
        await assertVisiblePair(question, 'QA17 trả lời đầu');
        const second = await submit('QA17 hỏi tiếp');
        assert.equal(second.payload.session_id, result.session_id);
        const next = await reply(second, 201);
        await assertVisiblePair('QA17 hỏi tiếp', 'QA17 trả lời tiếp');
        const after = audit();
        assert.equal(chatRequests(question).length, 1);
        assert.equal(after.sessions.length - before.sessions.length, 1);
        assert.equal(after.messages.length - before.messages.length, 4);
        assert.equal(after.turns.length - before.turns.length, 2);
        return { statuses: [201, 201], first_post_count: 1, installed_session_csrf_accepted: true,
            first: assertStored(after, first.payload, 31, result, 'QA17 trả lời đầu'),
            second: assertStored(after, second.payload, 31, next, 'QA17 trả lời tiếp') };
    });

    await inspect('QUI-04', 'Lost committed response, replay and explicit discard', async () => {
        const lost = hold('QA17 mất phản hồi');
        const first = await submit(lost.question);
        const paused = await waitFor('committed response at the browser', () => lost.paused);
        const committed = audit();
        const saved = assertStored(committed, first.payload, 31, null, 'QA17 trả lời đã lưu');
        await release(paused, true);
        await waitFor('unknown outcome warning', () => hasText(alert, 'Không thể xác nhận kết quả'));
        await closeWidget();
        await openWidget();
        const replay = await retry(lost.question, 1);
        const replayed = await reply(replay, 200);
        await assertVisiblePair(lost.question, 'QA17 trả lời đã lưu');
        const afterReplay = audit();
        assert.deepEqual(rows(afterReplay), rows(committed));
        assert.deepEqual(assertStored(afterReplay, first.payload, 31, replayed, 'QA17 trả lời đã lưu'), saved);

        const discard = hold('QA17 bỏ lượt');
        const original = await submit(discard.question);
        await release(await waitFor('second intentionally lost response', () => discard.paused), true);
        await waitFor('discard option', () => click('Bỏ lượt này'));
        assert.equal(await hasText(`${dialog} [role="status"]`, 'có thể đã được lưu'), true);
        assert.equal(await click('Giữ lượt gửi'), true);
        assert.equal(await evaluate((selector, question) => document.querySelector(selector).readOnly
            && document.querySelector(selector).value === question, input, discard.question), true);
        const kept = await retry(discard.question, 1);
        await reply(kept, 200);
        await assertVisiblePair(discard.question, 'QA17 trả lời lượt đã bỏ');

        // A separate unknown-result turn can be discarded without replaying it.
        const abandoned = hold('QA17 câu mới');
        const abandonedRequest = await submit(abandoned.question);
        await release(await waitFor('response for the abandoned turn', () => abandoned.paused), true);
        await waitFor('discard option for the abandoned turn', () => click('Bỏ lượt này'));
        assert.equal(await click('Xác nhận bỏ lượt'), true);
        assert.equal(await evaluate((selector) => !document.querySelector(selector).readOnly, input), true);
        const fresh = await submit('QA17 sau khi bỏ');
        assert.notEqual(fresh.payload.request_id, abandonedRequest.payload.request_id);
        assert.equal(fresh.payload.session_id, abandonedRequest.payload.session_id);
        const result = await reply(fresh, 201);
        await assertVisiblePair('QA17 sau khi bỏ', 'QA17 trả lời sau khi bỏ');
        assert.equal(await hasText(messages, abandoned.question), false);
        const state = audit();
        assertStored(state, original.payload, 31, null, 'QA17 trả lời lượt đã bỏ');
        assertStored(state, abandonedRequest.payload, 31, null, 'QA17 trả lời câu mới');
        return { committed: saved, replay: 200, stored_counts_unchanged: true, kept_request_id: kept.payload.request_id,
            discarded_request_id: abandonedRequest.payload.request_id,
            fresh: assertStored(state, fresh.payload, 31, result, 'QA17 trả lời sau khi bỏ') };
    });

    let customerB;
    await inspect('QUI-05', 'Logout and account isolation with an in-flight response, then session expiry', async () => {
        const delayed = hold('QA17 phản hồi A');
        const old = await submit(delayed.question);
        const paused = await waitFor('A response intercepted', () => delayed.paused);
        const saved = assertStored(audit(), old.payload, 31, null, 'QA17 dữ liệu riêng A');
        // A second client action can invalidate the cookie while the widget XHR is still pending.
        assert.equal((await api('/logout', 'POST', {})).status, 200);
        assert.equal((await api('/api/me')).status, 401);
        assert.equal(await hasText(`${dialog} [role="status"]`, 'đang trả lời'), true);
        assert.equal(old.finished ?? false, false);
        await closeWidget();
        await waitFor('A request canceled by browser', () => old.failed);
        // An aborted XHR may already have released the CDP interception.
        await release(paused).catch(() => { nextHold = undefined; });
        await waitFor('account menu', () => evaluate(() => {
            const button = document.querySelector('[aria-label="Mở menu tài khoản"]');
            if (!button) return false;
            button.click();
            return true;
        }));
        await waitFor('app logout', () => click('Đăng xuất'));
        await waitFor('customer layout removed', async () => !await hasButton(launcher));
        await waitFor('A session invalidated in Laravel', async () => (await api('/api/me')).status === 401);
        await login('customer-b');
        await openWidget();
        assert.equal(await hasText(messages, 'QA17 dữ liệu riêng A'), false);
        assert.equal(await evaluate((selector) => document.querySelector(selector).value, input), '');
        customerB = await submit('QA17 câu của B');
        assert.equal(customerB.payload.session_id, null);
        const result = await reply(customerB, 201);
        await assertVisiblePair('QA17 câu của B', 'QA17 trả lời riêng B');
        const beforeExpiry = audit();
        const own = assertStored(beforeExpiry, customerB.payload, 32, result, 'QA17 trả lời riêng B');
        assert.notEqual(own.session_id, saved.session_id);

        // Invalidate the session at the real backend without updating the open tab's AuthContext.
        assert.equal((await api('/logout', 'POST', {})).status, 200);
        const expired = await submit('QA17 hết phiên');
        await reply(expired, 401);
        await waitFor('login required after chat 401', () => evaluate(() => location.pathname === '/login'));
        assert.equal(await hasButton(launcher), false);
        assert.deepEqual(rows(audit()), rows(beforeExpiry));
        await login('customer-b');
        await openWidget();
        assert.equal(await hasText(messages, 'QA17 trả lời riêng B'), false);
        return { prior_response: paused.status, session_invalidated_while_pending: true, prior_request_canceled: old.failed,
            old: saved, current: own, expired_session: 401 };
    });

    await inspect('QUI-03a', 'Fake timeout and provider Retry-After', async () => {
        await openWidget();
        const results = [];
        for (const [question, status, code, warning, answer] of [
            ['QA17 timeout', 504, 'CHAT_PROVIDER_TIMEOUT', 'quá thời gian', 'QA17 đã phục hồi timeout'],
            ['QA17 upstream', 503, 'CHAT_PROVIDER_RATE_LIMITED', 'quá nhiều yêu cầu', 'QA17 đã phục hồi giới hạn'],
        ]) {
            const first = await submit(question);
            await reply(first, status, code);
            await waitFor('Vietnamese retry message', () => hasText(alert, warning));
            assert.equal(await evaluate((selector, question) => document.querySelector(selector).readOnly
                && document.querySelector(selector).value === question, input, question), true);
            const retry_after = status === 503 ? await observeCountdown(question) : null;
            const second = await retry(question, 1);
            const result = await reply(second, 201);
            await assertVisiblePair(question, answer);
            results.push({ status, code, retry_after, turn: assertStored(audit(), first.payload, 32, result, answer, 2) });
        }
        return results;
    });

    await inspect('QUI-06', 'Keyboard and long answer at 360, 768 and 1440 px', async () => {
        await openWidget();
        const long = await submit('QA17 nội dung dài');
        const result = await reply(long, 201);
        const answer = 'Dàikhôngdấucách'.repeat(160);
        await assertVisiblePair('QA17 nội dung dài', answer);
        assertStored(audit(), long.payload, 32, result, answer);
        await closeWidget();
        const sizes = [];
        for (const size of [360, 768, 1440]) {
            await width(size);
            assert.ok(await evaluate((name) => {
                const button = [...document.querySelectorAll('button')].find((element) => element.textContent.trim() === name);
                button.focus();
                return document.activeElement === button;
            }, launcher));
            await key('Enter', 13);
            await waitFor('keyboard opened dialog and moved focus', () => evaluate((selector) => document.querySelector(selector)?.contains(document.activeElement), dialog));
            for (const modifiers of [0, 0, 0, 8, 8, 8]) {
                await key('Tab', 9, modifiers);
                assert.equal(await evaluate((selector) => document.querySelector(selector).contains(document.activeElement), dialog), true);
            }
            const geometry = await evaluate((dialog, messages, input) => {
                const box = document.querySelector(dialog);
                const log = document.querySelector(messages);
                const within = (element) => {
                    const { left, top, right, bottom, width, height } = element.getBoundingClientRect();
                    return width > 0 && height > 0 && left >= -0.5 && top >= -0.5 && right <= innerWidth + 0.5 && bottom <= innerHeight + 0.5;
                };
                log.scrollTop = log.scrollHeight;
                return { width: innerWidth, controls_inside: [box, box.querySelector('button'), document.querySelector(input), box.querySelector('[type="submit"]')].every(within),
                    log_horizontal_overflow: Math.max(0, log.scrollWidth - log.clientWidth), log_scroll_top: log.scrollTop };
            }, dialog, messages, input);
            assert.equal(geometry.width, size);
            assert.ok(geometry.controls_inside && geometry.log_horizontal_overflow === 0 && geometry.log_scroll_top > 0, JSON.stringify(geometry));
            await key('Escape', 27);
            await waitFor('Escape closes widget and returns focus', () => evaluate((dialog, name) => !document.querySelector(dialog)
                && document.activeElement.textContent.trim() === name, dialog, launcher));
            sizes.push(geometry);
        }
        return sizes;
    });

    await inspect('QUI-03b', 'Real Laravel 429 and browser countdown', async () => {
        await openWidget();
        assert.ok(customerB);
        const before = rows(audit());
        let replayCount = 0;
        let limited;
        for (let index = 0; index <= 10; index++) {
            const probe = await api(chatPath, 'POST', customerB.payload);
            if (probe.status === 429) {
                limited = probe;
                break;
            }
            assert.equal(probe.status, 200);
            replayCount++;
        }
        assert.ok(limited && Number(limited.retry_after) >= 1);
        const question = 'QA17 giới hạn ứng dụng';
        const rejected = await submit(question);
        await reply(rejected, 429);
        const retryAfter = await observeCountdown(question);
        assert.deepEqual(rows(audit()), before);
        const accepted = await retry(question, 1);
        const result = await reply(accepted, 201);
        await assertVisiblePair(question, 'QA17 trả lời sau giới hạn');
        const after = audit();
        assert.deepEqual([after.sessions.length, after.turns.length, after.messages.length, after.fake_calls.length], [3, 12, 24, 14]);
        return { replay_probes: replayCount, status: 429, exposed_retry_after: Number(limited.retry_after), countdown: retryAfter,
            rejected_without_write: true, turn: assertStored(after, rejected.payload, 32, result, 'QA17 trả lời sau giới hạn') };
    });
    }
} catch (error) {
    checkpoints.push({ id: 'SETUP', status: 'FAIL', error: String(error.stack ?? error).replaceAll(password, '[REDACTED]') });
} finally {
    if (socket?.readyState === WebSocket.OPEN) {
        await send('Browser.close').catch(() => undefined);
        socket.close();
    }
}

const passed = checkpoints.length === (liveMode ? 2 : guestBrowser ? 5 : 7) && checkpoints.every(({ status }) => status === 'PASS');
process.stdout.write(JSON.stringify({ passed, browser: browserVersion, checkpoints }, null, 2) + '\n');
process.exitCode = passed ? 0 : 1;
