import test from 'node:test';
import assert from 'node:assert/strict';
import axios, { AxiosError } from 'axios';
import { registerCustomer, createRegistrationSubmission, validateRegistration } from '../src/services/registration.js';
import { subscribeToAuthFailures } from '../src/services/authSession.js';

const payload = {
    name: ' Fixture Customer ', email: ' Case.Person@Example.test ',
    password: 'Fixture-password1!', password_confirmation: 'Fixture-password1!',
};

function clientFixture({ status = 201, data = { message: 'Fixture registration confirmed' }, headers = {}, failureAt, networkError = false } = {}) {
    const requests = [];
    const client = axios.create({ adapter: async (config) => {
        requests.push(config);
        if (config.url === failureAt && networkError) {
            throw new AxiosError('Fixture timeout', 'ECONNABORTED', config);
        }
        if (config.url === '/sanctum/csrf-cookie') {
            return { status: 204, data: '', headers: {}, config };
        }
        const response = { status, data, headers, config };
        if (status >= 400) throw new AxiosError('Fixture response', 'ERR_BAD_RESPONSE', config, null, response);
        return response;
    } });
    return { client, requests };
}

test('signup validation rejects malformed addresses before form submission', () => {
    for (const email of [
        'customer@localhost', 'customer@example', 'customer@example.c', 'customer@example.123',
        'customer name@example.com', 'customer..name@example.com', '.customer@example.com',
        'customer.@example.com', 'customer@@example.com', 'customer@example..com',
        'customer@-example.com', 'customer@example-.com', 'customer@exam_ple.com',
        'customer@example.com.', `customer@${'a'.repeat(64)}.com`, `${'a'.repeat(65)}@example.com`,
        'customer@127.0.0.1', 'customer@[127.0.0.1]', '"customer"@example.com',
        'customer\nname@example.com', 'customer@vídụ.com',
    ]) {
        assert.ok(validateRegistration({ ...payload, email }).email, email);
    }
});

test('signup validation accepts aliases, subdomains, trim and the 254-character email boundary', () => {
    for (const email of [
        ' customer.name+booking@example.com ', 'Customer_1@sub.example.com.vn', "o'connor@example.travel",
        `${'a'.repeat(64)}@${'b'.repeat(63)}.${'c'.repeat(63)}.${'d'.repeat(61)}`,
    ]) {
        assert.deepEqual(validateRegistration({ ...payload, email }), {});
    }
});

test('signup validation rejects weak passwords, whitespace, control characters and too many UTF-8 bytes', () => {
    for (const password of [
        '12345678', 'abcdefgh', 'ABCDEFGH', 'Abcdefgh', 'Abcdefg1', 'abcdef1!', 'ABCDEFG1!',
        'Abcdefg!', 'Aa1!aaa', 'Aa1! aaa', ' Aa1!aaaa', 'Aa1!aaaa ',
        'Aa1!\taaaa', 'Aa1!\naaaa', 'Aa1!\u200Baaaa', 'Aa1!\0aaaa', `Aa1!${'á'.repeat(35)}`,
    ]) {
        // Do not include submitted passwords in assertion output.
        assert.ok(validateRegistration({ ...payload, password, password_confirmation: password }).password);
    }
});

test('signup validation accepts strong Unicode and boundary passwords and checks confirmation', () => {
    for (const password of ['Aa1!aaaa', 'ĐăngKý1!', `Aa1!${'á'.repeat(34)}`, `Aa1!${'x'.repeat(68)}`]) {
        assert.deepEqual(validateRegistration({ ...payload, password, password_confirmation: password }), {});
    }
    assert.ok(validateRegistration({ ...payload, password_confirmation: 'Different1!' }).password_confirmation);
    const emptyErrors = validateRegistration({ name: '', email: '', password: '', password_confirmation: '' });
    assert.deepEqual(Object.keys(emptyErrors).sort(), ['email', 'name', 'password', 'password_confirmation']);
});

test('registration initializes CSRF, posts a whitelist and never automatically logs in', async () => {
    const { client, requests } = clientFixture();
    const response = await registerCustomer(client, {
        ...payload, role: 'ADMIN', status: 'BLOCKED', id: 999, expectedRole: 'HOST', email_verified_at: '2000-01-01',
    });
    assert.deepEqual(response, { message: 'Fixture registration confirmed' });
    assert.deepEqual(requests.map(({ url, method }) => [url, method]), [
        ['/sanctum/csrf-cookie', 'get'], ['/register', 'post'],
    ]);
    const posted = JSON.parse(requests[1].data);
    assert.deepEqual(Object.keys(posted).sort(), ['email', 'name', 'password', 'password_confirmation']);
    assert.equal(posted.email, 'Case.Person@Example.test');
    assert.equal(posted.name, 'Fixture Customer');
    assert.equal(posted.password_confirmation, payload.password_confirmation);
    assert.equal(requests[1].timeout, 10000);
});

test('registration field errors preserve field mapping without exposing the request or technical details', async () => {
    const errors = { email: ['Duplicate fixture email'], password_confirmation: ['Confirmation required'] };
    const { client } = clientFixture({ status: 422, data: { message: 'private SQL details', errors } });
    await assert.rejects(registerCustomer(client, payload), (error) => {
        assert.equal(error.status, 422);
        assert.deepEqual(error.fields, errors);
        assert.equal(error.message.includes('SQL'), false);
        assert.equal(error.config, undefined);
        assert.equal(JSON.stringify(error).includes(payload.password), false);
        return true;
    });
});

test('register conflicts and throttles leave the shared auth failure subscriber untouched', async () => {
    for (const status of [409, 422, 429]) {
        const { client } = clientFixture({ status, data: {
            code: 'ALREADY_AUTHENTICATED', retry_after: 43, errors: { email: ['Fixture'] },
        } });
        let invalidations = 0;
        const cleanup = subscribeToAuthFailures(client, () => invalidations++, () => 1);
        await assert.rejects(registerCustomer(client, payload), (error) => {
            assert.equal(error.status, status);
            if (status === 409) assert.equal(error.code, 'ALREADY_AUTHENTICATED');
            if (status === 429) assert.equal(error.retryAfter, 43);
            return true;
        });
        assert.equal(invalidations, 0);
        cleanup();
    }
});

test('registration uses Retry-After and never retries a throttled POST automatically', async () => {
    const { client, requests } = clientFixture({ status: 429, data: {}, headers: { 'retry-after': '12' } });
    await assert.rejects(registerCustomer(client, payload), (error) => error.retryAfter === 12);
    assert.equal(requests.filter(({ url }) => url === '/register').length, 1);
});

test('CSRF expiration is a known rejection rather than an uncertain registration result', async () => {
    const { client } = clientFixture({ status: 419 });
    await assert.rejects(registerCustomer(client, payload), (error) => {
        assert.equal(error.status, 419);
        assert.equal(error.outcomeUnknown, false);
        return true;
    });
});

test('timeout after sending POST reports an unknown outcome without retrying or logging in', async () => {
    const { client, requests } = clientFixture({ failureAt: '/register', networkError: true });
    await assert.rejects(registerCustomer(client, payload), (error) => error.status === 0 && error.outcomeUnknown);
    assert.deepEqual(requests.map(({ url }) => url), ['/sanctum/csrf-cookie', '/register']);
});

test('failure to fetch CSRF never sends the registration payload', async () => {
    const { client, requests } = clientFixture({ failureAt: '/sanctum/csrf-cookie', networkError: true });
    await assert.rejects(registerCustomer(client, payload), (error) => error.status === 0 && !error.outcomeUnknown);
    assert.deepEqual(requests.map(({ url }) => url), ['/sanctum/csrf-cookie']);
});

test('server errors after a POST do not falsely guarantee that no account was created', async () => {
    const { client } = clientFixture({ status: 500, data: { message: 'private SQL details' } });
    await assert.rejects(registerCustomer(client, payload), (error) => {
        assert.equal(error.outcomeUnknown, true);
        assert.equal(error.message.includes('SQL'), false);
        return true;
    });
});

test('the form submission controller prevents concurrent duplicate POSTs', async () => {
    let complete;
    let calls = 0;
    const submission = createRegistrationSubmission(() => {
        calls++;
        return new Promise((resolve) => { complete = resolve; });
    });
    const pending = submission.submit(payload);
    assert.equal(submission.isPending(), true);
    assert.equal(await submission.submit(payload), null);
    assert.equal(calls, 1);
    complete({ message: 'Fixture success' });
    assert.deepEqual(await pending, { data: { message: 'Fixture success' } });
    assert.equal(submission.isPending(), false);
});

test('leaving signup ignores a late success and cannot finish a newer submission', async () => {
    const complete = [];
    const submission = createRegistrationSubmission(() => new Promise((resolve) => complete.push(resolve)));
    const old = submission.submit(payload);
    submission.cancel();
    const current = submission.submit(payload);
    complete[0]({ message: 'Old success' });
    assert.equal(await old, null);
    assert.equal(submission.isPending(), true);
    complete[1]({ message: 'Current success' });
    assert.deepEqual(await current, { data: { message: 'Current success' } });
});

test('leaving signup also ignores late errors instead of overwriting a new login screen', async () => {
    let fail;
    const submission = createRegistrationSubmission(() => new Promise((resolve, reject) => { fail = reject; }));
    const old = submission.submit(payload);
    submission.cancel();
    fail(new Error('Old failure'));
    assert.equal(await old, null);
    assert.equal(submission.isPending(), false);
});

test('a current rejection releases the form for an explicit user retry', async () => {
    let calls = 0;
    const failure = new Error('Fixture validation');
    const submission = createRegistrationSubmission(async () => {
        if (++calls === 1) throw failure;
        return { message: 'Fixture success' };
    });
    await assert.rejects(submission.submit(payload), (error) => error === failure);
    assert.equal(submission.isPending(), false);
    assert.deepEqual(await submission.submit(payload), { data: { message: 'Fixture success' } });
});
