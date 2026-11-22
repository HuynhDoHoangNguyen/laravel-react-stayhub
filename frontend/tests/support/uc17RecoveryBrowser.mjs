import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { mkdtempSync, readFileSync, rmSync } from 'node:fs';
import { createServer } from 'node:http';
import { tmpdir } from 'node:os';
import { join, resolve, sep } from 'node:path';
import { setTimeout as delay } from 'node:timers/promises';
import { fileURLToPath } from 'node:url';

const frontend = fileURLToPath(new URL('../../', import.meta.url));
const loopback = (port) => 'http://127.0.0.1:' + port;
const time = '2026-11-22T03:00:00Z';
const fixtureUsers = [
    { id: 7, role: 'CUSTOMER', status: 'ACTIVE', name: 'Khách QA A', email: 'a@example.test' },
    { id: 8, role: 'CUSTOMER', status: 'ACTIVE', name: 'Khách QA B', email: 'b@example.test' },
];
let user = fixtureUsers[0];
let appUrl;
const requests = [];
const checkpoints = [];

async function waitFor(label, read, timeout = 12000) {
    const deadline = Date.now() + timeout;
    do {
        const value = await read();
        if (value) return value;
        await delay(60);
    } while (Date.now() < deadline);
    throw new Error('Browser recovery timeout: ' + label);
}

function listen(server) {
    return new Promise((resolve, reject) => {
        server.once('error', reject);
        server.listen(0, '127.0.0.1', () => resolve(server.address().port));
    });
}

const close = (server) => new Promise((resolve) => server.close(resolve));

async function body(request) {
    let input = '';
    for await (const chunk of request) input += chunk;
    return JSON.parse(input);
}

const api = createServer(async (request, response) => {
    function respond(status, data, headers = {}) {
        response.writeHead(status, {
            'Content-Type': 'application/json',
            'Access-Control-Allow-Origin': appUrl,
            'Access-Control-Allow-Credentials': 'true',
            'Access-Control-Allow-Headers': 'Content-Type, X-XSRF-TOKEN',
            'Access-Control-Allow-Methods': 'GET, POST, OPTIONS',
            'Access-Control-Expose-Headers': 'Retry-After',
            ...headers,
        });
        response.end(data === undefined ? undefined : JSON.stringify(data));
    }

    try {
        if (request.method === 'OPTIONS' || request.url === '/sanctum/csrf-cookie') return respond(204);
        if (request.url === '/api/me') return user ? respond(200, { user }) : respond(401, {});
        if (request.url === '/api/chat-recovery-fixture-expire') {
            user = null;
            return respond(401, {});
        }
        if (request.url === '/logout') {
            user = null;
            return respond(200, { message: 'Đã đăng xuất.' });
        }
        if (request.url === '/login') {
            const { email } = await body(request);
            user = fixtureUsers.find((account) => account.email === email) ?? null;
            return user ? respond(200, { user }) : respond(422, {});
        }
        if (request.url === '/api/customer/chat/messages' && request.method === 'POST') {
            const payload = await body(request);
            requests.push({ payload, actor: user?.id, respond });
            return;
        }
        return respond(200, { data: [] });
    } catch {
        return respond(500, { message: 'Fixture failed.' });
    }
});

function success(request, reply, status = 201, id = 42) {
    request.respond(status, { data: {
        session_id: request.payload.session_id ?? id,
        request_id: request.payload.request_id,
        user_message: { id: id * 10 + 1, sender: 'USER', message: request.payload.message, created_at: time },
        assistant_message: { id: id * 10 + 2, sender: 'ASSISTANT', message: reply, created_at: time },
    } });
}

const edge = process.env.STAYHUB_RECOVERY_EDGE ?? join(
    process.env['ProgramFiles(x86)'] ?? 'C:/Program Files (x86)', 'Microsoft/Edge/Application/msedge.exe',
);
const tempRoot = resolve(tmpdir());
const profile = mkdtempSync(join(tempRoot, 'stayhub-uc17-recovery-'));
let vite;
let browser;
let socket;
let send;

try {
    const apiPort = await listen(api);
    const reserve = createServer();
    const uiPort = await listen(reserve);
    await close(reserve);
    appUrl = loopback(uiPort);
    vite = spawn(process.execPath, ['node_modules/vite/bin/vite.js', '--host', '127.0.0.1', '--port', String(uiPort), '--strictPort'], {
        cwd: frontend, env: { ...process.env, VITE_BACKEND_URL: loopback(apiPort) }, stdio: 'ignore', windowsHide: true,
    });
    await waitFor('Vite', () => fetch(appUrl).then((response) => response.ok).catch(() => false));

    browser = spawn(edge, [
        '--headless=new', '--disable-gpu', '--disable-background-networking', '--no-first-run', '--disable-sync',
        '--remote-debugging-port=0', '--user-data-dir=' + profile, 'about:blank',
    ], { stdio: 'ignore', windowsHide: true });
    const port = await waitFor('Edge debug port', () => {
        try { return readFileSync(join(profile, 'DevToolsActivePort'), 'utf8').split(/\r?\n/)[0]; } catch { return null; }
    });
    const pages = await (await fetch(loopback(port) + '/json/list')).json();
    const page = pages.find((entry) => entry.type === 'page');
    assert.ok(page?.webSocketDebuggerUrl);
    socket = new WebSocket(page.webSocketDebuggerUrl);
    await new Promise((resolve, reject) => {
        socket.addEventListener('open', resolve, { once: true });
        socket.addEventListener('error', reject, { once: true });
    });

    let sequence = 0;
    const pending = new Map();
    socket.addEventListener('message', ({ data }) => {
        const event = JSON.parse(data);
        const command = pending.get(event.id);
        if (!command) return;
        pending.delete(event.id);
        clearTimeout(command.timer);
        if (event.error) command.reject(new Error(event.error.message));
        else command.resolve(event.result);
    });
    send = (method, params = {}) => new Promise((resolve, reject) => {
        const id = ++sequence;
        const timer = setTimeout(() => {
            pending.delete(id);
            reject(new Error('CDP timeout: ' + method));
        }, 5000);
        pending.set(id, { resolve, reject, timer });
        socket.send(JSON.stringify({ id, method, params }));
    });

    const evaluate = async (fn, ...args) => {
        const expression = '(' + fn.toString() + ')(...' + JSON.stringify(args) + ')';
        const result = await send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
        if (result.exceptionDetails) throw new Error(result.exceptionDetails.exception?.description ?? 'Browser evaluation failed');
        return result.result.value;
    };
    const click = (label, selector = 'button') => evaluate((label, selector) => {
        const button = [...document.querySelectorAll(selector)].find((el) => el.textContent.trim() === label);
        if (!button || button.disabled) return false;
        button.click();
        return true;
    }, label, selector);
    const contains = (selector, text) => evaluate((selector, text) => document.querySelector(selector)?.textContent.includes(text) ?? false, selector, text);
    const setInput = (selector, value) => evaluate((selector, value) => {
        const input = document.querySelector(selector);
        const prototype = input instanceof HTMLTextAreaElement ? HTMLTextAreaElement.prototype : HTMLInputElement.prototype;
        Object.getOwnPropertyDescriptor(prototype, 'value').set.call(input, value);
        input.dispatchEvent(new Event('input', { bubbles: true }));
    }, selector, value);
    const openWidget = async () => {
        await waitFor('widget launcher', () => click('Trợ lý StayHub'));
        await waitFor('widget open', () => evaluate(() => !!document.querySelector('[role="dialog"] textarea')));
    };
    const closeWidget = () => evaluate(() => document.querySelector('[aria-label="Đóng trợ lý"]').click());
    const submit = (question) => setInput('[role="dialog"] textarea', question)
        .then(() => waitFor('send question', () => click('Gửi', '[role="dialog"] button')));
    const requestAt = (index) => waitFor('HTTP request ' + index, () => requests[index]);

    async function login(email) {
        await evaluate(() => {
            if (location.pathname === '/login') return;
            const link = [...document.querySelectorAll('a')].find((node) => node.getAttribute('href') === '/login');
            if (link) link.click();
            else {
                window.history.pushState({}, '', '/login?role=customer');
                window.dispatchEvent(new PopStateEvent('popstate'));
            }
        });
        await waitFor('login form', () => evaluate(() => !!document.querySelector('#login-email')));
        await setInput('#login-email', email);
        await setInput('#login-password', 'fixture-only-password');
        await evaluate(() => document.querySelector('#login-email').form.requestSubmit());
        await waitFor('customer route after login', () => evaluate(() => location.pathname === '/customer'));
    }

    await send('Emulation.setDeviceMetricsOverride', { width: 1100, height: 820, deviceScaleFactor: 1, mobile: false });
    await send('Page.navigate', { url: appUrl + '/customer' });
    await waitFor('initial customer widget', () => click('Trợ lý StayHub'), 25000);
    await waitFor('widget open', () => evaluate(() => !!document.querySelector('[role="dialog"] textarea')));
    await submit('Câu hỏi cho lượt giới hạn');
    const first = await requestAt(0);
    assert.equal(await click('Gửi', '[role="dialog"] button'), false);
    assert.equal(requests.length, 1);
    first.respond(429, { message: 'Fixture rate limited' }, { 'Retry-After': '2' });
    await waitFor('retry countdown', () => contains('[role="alert"]', 'Thử lại sau'));
    assert.equal(await click('Thử lại', '[role="dialog"] button'), false);
    assert.equal(requests.length, 1);
    await waitFor('retry after deadline', () => click('Thử lại', '[role="dialog"] button'));
    const retry = await requestAt(1);
    assert.deepEqual(retry.payload, first.payload);
    success(retry, 'Kết quả retry một');
    await waitFor('first pair', () => contains('[role="log"]', 'Kết quả retry một'));
    checkpoints.push('duplicate guard and Retry-After preserve the exact payload');

    await submit('Câu hỏi sẽ bị ngắt chờ');
    const interrupted = await requestAt(2);
    await closeWidget();
    await openWidget();
    await waitFor('unknown state after reopen', () => contains('[role="alert"]', 'Chưa biết lượt gửi'));
    assert.equal(await evaluate(() => document.querySelector('[role="dialog"] textarea').readOnly), true);
    success(interrupted, 'Kết quả trả khi đã đóng');
    assert.ok(await click('Thử lại', '[role="dialog"] button'));
    const recovered = await requestAt(3);
    assert.deepEqual(recovered.payload, interrupted.payload);
    success(recovered, 'Kết quả trả khi đã đóng', 200, 43);
    await waitFor('replayed pair', () => contains('[role="log"]', 'Kết quả trả khi đã đóng'));
    assert.equal(await evaluate(() => document.querySelectorAll('[role="log"] p').length), 4);
    checkpoints.push('close and reopen retain one pending turn and render one pair on replay');

    await submit('Câu hỏi sẽ được bỏ');
    const discard = await requestAt(4);
    discard.respond(409, { code: 'CHAT_RETRY_REQUIRED' });
    await waitFor('discard control', () => click('Bỏ lượt này', '[role="dialog"] button'));
    assert.ok(await contains('[role="dialog"]', 'có thể đã được lưu'));
    assert.ok(await click('Giữ lượt gửi', '[role="dialog"] button'));
    assert.equal(await evaluate(() => document.querySelector('[role="dialog"] textarea').readOnly), true);
    assert.ok(await click('Bỏ lượt này', '[role="dialog"] button'));
    assert.ok(await click('Xác nhận bỏ lượt', '[role="dialog"] button'));
    await submit('Câu hỏi mới sau khi bỏ');
    const replacement = await requestAt(5);
    assert.notEqual(replacement.payload.request_id, discard.payload.request_id);
    success(replacement, 'Kết quả câu hỏi mới', 201, 44);
    await waitFor('replacement result', () => contains('[role="log"]', 'Kết quả câu hỏi mới'));
    checkpoints.push('discard requires confirmation and the edited question uses a new UUID');

    // Deliberately ignore AbortSignal here so late responses reach the current SPA.
    await evaluate(async () => {
        const { chatbotService } = await import('/src/services/chatbotService.js');
        const send = chatbotService.send.bind(chatbotService);
        window.__recoverySettled = 0;
        chatbotService.send = (payload) => send(payload).finally(() => { window.__recoverySettled++; });
    });
    await submit('Câu hỏi cũ sẽ thành công');
    const oldSuccess = await requestAt(6);
    await closeWidget();
    await evaluate(() => document.querySelector('[aria-label="Mở menu tài khoản"]').click());
    await waitFor('logout button', () => click('Đăng xuất'));
    await waitFor('logout navigated away', () => evaluate(() => ['/', '/login'].includes(location.pathname)));
    await login('b@example.test');
    await openWidget();
    success(oldSuccess, 'Phản hồi riêng của khách A', 201, 45);
    await waitFor('delayed success received', () => evaluate(() => window.__recoverySettled === 1));
    assert.equal(await contains('[role="log"]', 'Phản hồi riêng của khách A'), false);
    await submit('Câu hỏi của khách B');
    const b = await requestAt(7);
    assert.equal(b.actor, 8);
    assert.equal(b.payload.session_id, null);
    assert.notEqual(b.payload.request_id, oldSuccess.payload.request_id);

    await evaluate(async () => {
        const { default: client } = await import('/src/api/axios.js');
        await client.get('/api/chat-recovery-fixture-expire').catch(() => {});
    });
    await waitFor('expired session navigates to login', () => evaluate(() => location.pathname === '/login'));
    await login('a@example.test');
    await openWidget();
    b.respond(401, {});
    await waitFor('delayed 401 received', () => evaluate(() => window.__recoverySettled === 2));
    assert.equal(await evaluate(() => location.pathname), '/customer');
    assert.equal(await contains('[role="dialog"]', 'Phiên đăng nhập đã hết hạn'), false);
    assert.equal(await evaluate(() => document.querySelector('[role="dialog"] textarea').value), '');
    await submit('Câu hỏi của phiên mới');
    const next = await requestAt(8);
    assert.equal(next.actor, 7);
    assert.equal(next.payload.session_id, null);
    assert.notEqual(next.payload.request_id, b.payload.request_id);
    success(next, 'Chỉ hiển thị phiên hiện tại', 201, 46);
    await waitFor('new session reply', () => contains('[role="log"]', 'Chỉ hiển thị phiên hiện tại'));
    checkpoints.push('logout, account change and session expiry discard delayed success and 401');

    console.log(JSON.stringify({ browser: (await send('Browser.getVersion')).product, transport: 'HTTP fixture',
        passed: checkpoints.length, checkpoints }, null, 2));
} catch (error) {
    const state = await send?.('Runtime.evaluate', {
        expression: '({ path: location.pathname, text: document.body?.innerText.slice(0, 320) })', returnByValue: true,
    }).catch(() => null);
    console.error(JSON.stringify({ checkpoints, fixtureRequests: requests.length, page: state?.result?.value }));
    throw error;
} finally {
    if (send) await send('Browser.close').catch(() => {});
    socket?.close();
    if (browser?.exitCode === null) browser.kill();
    if (vite?.exitCode === null) vite.kill();
    api.closeAllConnections();
    await close(api).catch(() => {});
    if (resolve(profile).startsWith(tempRoot + sep + 'stayhub-uc17-recovery-')) {
        await delay(450);
        rmSync(profile, { recursive: true, force: true, maxRetries: 5, retryDelay: 250 });
    }
}
