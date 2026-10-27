import test from 'node:test';
import assert from 'node:assert/strict';
import axios, { AxiosError } from 'axios';
import { getAuthDestination } from '../src/utils/authNavigation.js';
import { isAccountFailure, normalizeAuthError, subscribeToAuthFailures } from '../src/services/authSession.js';

test('role destinations keep registered paths and preserve search/hash', () => {
    assert.equal(getAuthDestination('CUSTOMER', '/customer/checkout?roomId=12#summary'), '/customer/checkout?roomId=12#summary');
    assert.equal(getAuthDestination('HOST', '/host/bookings/12/create-invoice'), '/host/bookings/12/create-invoice');
    assert.equal(getAuthDestination('HOST', '/host/invoices/create'), '/host/invoices/create');
    assert.equal(getAuthDestination('ADMIN', '/admin'), '/admin');
    assert.equal(getAuthDestination('CUSTOMER', '/properties/12?startDate=2026-12-01'), '/properties/12?startDate=2026-12-01');
});

test('external, malformed and encoded redirect attempts fall back to the role dashboard', () => {
    for (const target of [undefined, '', 'https://example.test', '//example.test', '/\\example.test',
        '/%2f%2fexample.test', '/%5cexample.test', '/%252f%252fexample.test', '/%zz',
        '/customer\n', '/customer/%0a', '/login?returnTo=/customer', '/login-test', '/missing']) {
        assert.equal(getAuthDestination('CUSTOMER', target), '/customer', String(target));
    }
});

test('returnTo never grants a different role or a route that is not implemented', () => {
    assert.equal(getAuthDestination('CUSTOMER', '/host/invoices'), '/customer');
    assert.equal(getAuthDestination('HOST', '/admin'), '/host');
    assert.equal(getAuthDestination('ADMIN', '/customer'), '/admin');
    assert.equal(getAuthDestination('HOST', '/host/properties'), '/host');
    assert.equal(getAuthDestination('CUSTOMER', '/customer/../admin'), '/customer');
    assert.equal(getAuthDestination('UNKNOWN', '/admin'), '/');
});

test('only the two explicit account codes classify a 403 as session invalidation', () => {
    for (const code of ['ACCOUNT_BLOCKED', 'ACCOUNT_INACTIVE']) {
        assert.equal(isAccountFailure({ response: { status: 403, data: { code } } }), true);
    }
    assert.equal(isAccountFailure({ response: { status: 403, data: { message: 'Role denied' } } }), false);
    assert.equal(isAccountFailure({ response: { status: 500, data: { code: 'ACCOUNT_BLOCKED' } } }), false);
});

test('normalized validation/rate-limit errors retain field errors and Retry-After without credentials', () => {
    const fields = { email: ['Email hoặc mật khẩu không chính xác.'] };
    const error = normalizeAuthError({ response: { status: 422, data: { errors: fields } }, config: { data: 'sensitive-fixture' } });
    assert.deepEqual(error.fields, fields);
    assert.equal(error.status, 422);
    assert.equal(error.config, undefined);
    assert.equal(JSON.stringify(error).includes('sensitive-fixture'), false);
    const limited = normalizeAuthError({ response: { status: 429, data: {}, headers: { 'retry-after': '23' } } });
    assert.equal(limited.retryAfter, 23);
    assert.match(limited.message, /23/);
    assert.equal(normalizeAuthError(limited), limited);
});

test('network, expired CSRF and server errors have distinct messages and do not claim logout', () => {
    const network = normalizeAuthError(new Error('offline'));
    const csrf = normalizeAuthError({ response: { status: 419 } });
    const server = normalizeAuthError({ response: { status: 500, data: { message: 'private SQL details' } } });
    assert.equal(network.status, 0);
    assert.equal(csrf.status, 419);
    assert.notEqual(network.message, csrf.message);
    assert.equal(server.message.includes('SQL'), false);
    assert.equal(network.message.includes('Đăng xuất thành công'), false);
});

function rejectingClient(status, data = {}) {
    return axios.create({ adapter: async (config) => {
        const response = { status, data, headers: {}, config };
        throw new AxiosError('Fixture response', 'ERR_BAD_RESPONSE', config, null, response);
    } });
}

test('account failure from an existing domain request notifies centrally and preserves rejection', async () => {
    const client = rejectingClient(403, { code: 'ACCOUNT_BLOCKED', message: 'Tài khoản đã bị khóa.' });
    const received = [];
    const cleanup = subscribeToAuthFailures(client, (error, version) => received.push({ error, version }), () => 7);
    await assert.rejects(client.get('/api/customer/invoices'), (error) => error.response.status === 403);
    assert.equal(received.length, 1);
    assert.equal(received[0].version, 7);
    assert.equal(received[0].error.code, 'ACCOUNT_BLOCKED');
    cleanup();
});

test('role/ownership 403, public 401, CSRF and network errors do not invalidate auth', async () => {
    for (const [status, url] of [[403, '/api/host/invoices'], [401, '/api/public/properties/1/reviews'],
        [401, '/login'], [419, '/login'], [500, '/logout']]) {
        const client = rejectingClient(status);
        let count = 0;
        const cleanup = subscribeToAuthFailures(client, () => count++, () => 1);
        await assert.rejects(client.get(url));
        assert.equal(count, 0);
        cleanup();
    }
});

test('401 on a protected API or logout informs the provider that the session ended', async () => {
    for (const url of ['/api/me', '/api/customer/invoices', '/logout']) {
        const client = rejectingClient(401);
        let count = 0;
        const cleanup = subscribeToAuthFailures(client, (error) => { assert.equal(error.status, 401); count++; }, () => 1);
        await assert.rejects(client.get(url));
        assert.equal(count, 1);
        cleanup();
    }
});

test('late responses carry their request version so a newer login cannot be cleared by old failures', async () => {
    let fail;
    let start;
    const started = new Promise((resolve) => { start = resolve; });
    const client = axios.create({ adapter: (config) => new Promise((resolve, reject) => {
        fail = () => reject(new AxiosError('Fixture', '', config, null, { status: 401, data: {}, headers: {}, config }));
        start();
    }) });
    let version = 1;
    let receivedVersion;
    const cleanup = subscribeToAuthFailures(client, (error, capturedVersion) => { receivedVersion = capturedVersion; }, () => version);
    const pending = client.get('/api/customer/invoices');
    await started;
    version = 2;
    fail();
    await assert.rejects(pending);
    assert.equal(receivedVersion, 1);
    assert.notEqual(receivedVersion, version);
    cleanup();
});

test('mount cleanup removes both interceptors and remounting does not duplicate callbacks', async () => {
    const client = rejectingClient(403, { code: 'ACCOUNT_INACTIVE' });
    let count = 0;
    const subscribe = () => subscribeToAuthFailures(client, () => count++, () => 1);
    const firstCleanup = subscribe();
    firstCleanup();
    const secondCleanup = subscribe();
    await assert.rejects(client.get('/api/me'));
    assert.equal(count, 1);
    secondCleanup();
    await assert.rejects(client.get('/api/me'));
    assert.equal(count, 1);
    assert.equal(client.interceptors.request.handlers.filter(Boolean).length, 0);
    assert.equal(client.interceptors.response.handlers.filter(Boolean).length, 0);
});
