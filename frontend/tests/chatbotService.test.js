import test, { mock } from 'node:test';
import assert from 'node:assert/strict';
import { CanceledError } from 'axios';

mock.module(new URL('../src/api/axios.js', import.meta.url).href, { defaultExport: {} });
const { createChatbotService } = await import('../src/services/chatbotService.js');
test.after(() => mock.restoreAll());

const uuid = 'd3a70b4c-41f3-4b08-8f79-df5f3b44d620';
const payload = { request_id: uuid, session_id: null, message: 'Tôi nên kiểm tra gì trước chuyến đi?' };
const date = '2026-11-22T03:00:00.000000Z';
const data = {
    session_id: 42,
    request_id: uuid,
    user_message: { id: 101, sender: 'USER', message: payload.message, created_at: date },
    assistant_message: { id: 102, sender: 'ASSISTANT', message: 'Bạn nên xem chính sách hủy.', created_at: date },
};

test('gets CSRF before POST, preserves abort signal, and projects only the message pair', async () => {
    const signal = new AbortController().signal;
    const calls = [];
    const service = createChatbotService({
        async get(...args) { calls.push(args); },
        async post(...args) {
            calls.push(args);
            return { data: { data: { ...data, user_id: 1000 } } };
        },
    });

    assert.deepEqual(await service.send({ ...payload, message: ' ' + payload.message + ' ' }, { signal }), data);
    assert.deepEqual(calls, [
        ['/sanctum/csrf-cookie', { signal, timeout: 10000 }],
        ['/api/customer/chat/messages', payload, { signal, timeout: 18000 }],
    ]);
});

test('keeps retry metadata and retries the exact UUID and initial session', async () => {
    const requests = [];
    const service = createChatbotService({
        async get() {},
        async post(_url, input) {
            requests.push(input);
            if (requests.length === 1) {
                throw { response: { status: 503, data: { code: 'CHAT_PROVIDER_RATE_LIMITED', message: 'private' },
                    headers: { 'retry-after': '7' } } };
            }
            return { data: { data } };
        },
    });
    await assert.rejects(service.send(payload), (error) => {
        assert.equal(error.status, 503);
        assert.equal(error.code, 'CHAT_PROVIDER_RATE_LIMITED');
        assert.equal(error.retryAfter, 7);
        assert.equal(error.retryable, true);
        assert.notEqual(error.message, 'private');
        return true;
    });
    assert.deepEqual(await service.send(payload), data);
    assert.deepEqual(requests, [payload, payload]);
});

test('projects only assistant destination cards on both successful sends and replays', async () => {
    const property = { property_id: 71, property_name: 'Chỗ nghỉ QA', starting_price: '650000.00', url: 'https://example.test' };
    const city = { city: 'Đà Lạt', reason: 'Đi dạo quanh hồ.', properties: [property] };
    let destinations = [city];
    const service = createChatbotService({
        async get() {},
        async post() {
            return { data: { data: {
                ...data,
                user_message: { ...data.user_message, destinations: [city] },
                assistant_message: { ...data.assistant_message, destinations },
            } } };
        },
    });
    const first = await service.send(payload);
    assert.deepEqual(first.user_message, data.user_message);
    assert.deepEqual(first.assistant_message.destinations, [{
        city: 'Đà Lạt', reason: 'Đi dạo quanh hồ.',
        properties: [{ propertyId: 71, propertyName: 'Chỗ nghỉ QA', startingPrice: '650000.00' }],
    }]);
    assert.deepEqual(await service.send(payload), first);
    destinations = [];
    assert.deepEqual((await service.send(payload)).assistant_message.destinations, []);
    destinations = [{ ...city, properties: [{ ...property, starting_price: 650000 }] }];
    await assert.rejects(service.send(payload), { status: 0, retryable: true });
});

test('rejects malformed successes as recoverable without accepting a different UUID or sender', async () => {
    for (const response of [
        { ...data, request_id: 'wrong' },
        { ...data, assistant_message: { ...data.assistant_message, sender: 'USER' } },
        { ...data, assistant_message: { ...data.assistant_message, id: data.user_message.id } },
    ]) {
        const service = createChatbotService({ async get() {}, async post() { return { data: { data: response } }; } });
        await assert.rejects(service.send(payload), (error) => error.retryable && error.status === 0);
    }
});

test('distinguishes conflict, permissions and validation from a pending request', async () => {
    for (const [status, response, retryable] of [
        [403, {}, false],
        [404, {}, false],
        [409, { code: 'CHAT_REQUEST_CONFLICT' }, false],
        [409, { code: 'CHAT_IN_PROGRESS' }, true],
        [409, { code: 'CHAT_RETRY_REQUIRED' }, true],
        [409, { code: 'CHAT_ATTEMPTS_EXHAUSTED' }, false],
        [422, { errors: { message: ['Câu hỏi vượt giới hạn.'] } }, false],
    ]) {
        const service = createChatbotService({
            async get() {},
            async post() { throw { response: { status, data: response } }; },
        });
        await assert.rejects(service.send(payload), (error) => {
            assert.equal(error.status, status);
            assert.equal(error.retryable, retryable);
            if (status === 422) assert.equal(error.message, 'Câu hỏi vượt giới hạn.');
            return true;
        });
    }
});

test('preserves cancellation and skips POST if aborted while obtaining CSRF', async () => {
    const controller = new AbortController();
    const canceled = new CanceledError();
    const service = createChatbotService({
        async get() { controller.abort(canceled); },
        async post() { assert.fail('Canceled before POST'); },
    });

    await assert.rejects(service.send(payload, { signal: controller.signal }), (error) => error === canceled);
});
