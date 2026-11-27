import test, { mock } from 'node:test';
import assert from 'node:assert/strict';

mock.module(new URL('../src/api/axios.js', import.meta.url).href, { defaultExport: {} });
const { createGuestChatbotService } = await import('../src/services/chatbotService.js');
test.after(() => mock.restoreAll());

const request_id = 'd3a70b4c-41f3-4b08-8f79-df5f3b44d620';

test('public chat sends only UUID and question with CSRF and projects public catalog IDs', async () => {
    const signal = new AbortController().signal;
    const calls = [];
    const service = createGuestChatbotService({
        async get(...params) { calls.push(params); },
        async post(...params) {
            calls.push(params);
            return { data: { data: { message: 'Phòng phù hợp', action: null, rooms: [{
                room_id: 7, property_id: 3, room_name: 'Phòng đôi', property_name: 'Khách sạn thử', url: 'https://example.test',
            }] } } };
        },
    });
    assert.deepEqual(await service.send({ request_id, message: '  Tìm phòng cho 2 người  ', session_id: 5 }, { signal }), {
        reply: 'Phòng phù hợp', action: null, rooms: [{ roomId: 7, propertyId: 3, roomName: 'Phòng đôi', propertyName: 'Khách sạn thử' }],
        destinations: [],
    });
    assert.deepEqual(calls, [
        ['/sanctum/csrf-cookie', { signal, timeout: 10000 }],
        ['/api/public/chat/messages', { request_id, message: 'Tìm phòng cho 2 người' }, { signal, timeout: 18000 }],
    ]);
});

test('public chat projects only approved travel card fields and accepts empty suggestions', async () => {
    const property = { property_id: 3, property_name: 'Chỗ nghỉ', starting_price: '850000.00', url: 'https://example.test' };
    const city = { city: 'Đà Lạt', reason: 'Đi dạo quanh hồ', properties: [property] };
    let data = { message: 'Gợi ý cho chuyến đi', action: null, rooms: [], destinations: [city] };
    const service = createGuestChatbotService({ async get() {}, async post() { return { data: { data } }; } });
    const send = () => service.send({ request_id, message: 'Tháng 11 nên đi đâu?' });

    assert.deepEqual((await send()).destinations, [{
        city: 'Đà Lạt', reason: 'Đi dạo quanh hồ',
        properties: [{ propertyId: 3, propertyName: 'Chỗ nghỉ', startingPrice: '850000.00' }],
    }]);
    data = { ...data, action: 'search', destinations: [] };
    assert.deepEqual(await send(), { reply: 'Gợi ý cho chuyến đi', action: 'search', rooms: [], destinations: [] });

    for (const destinations of [
        null, [null], [{ ...city, properties: [] }],
        [{ ...city, properties: [{ ...property, property_id: '../login' }] }],
        [{ ...city, properties: [{ ...property, starting_price: '850000.00\n' }] }],
    ]) {
        data = { ...data, destinations };
        await assert.rejects(send());
    }
});

test('public chat accepts allowlisted actions and denies untrusted destinations and IDs', async () => {
    let data = { message: 'Hãy đăng nhập', action: 'invoices', rooms: [] };
    const service = createGuestChatbotService({ async get() {}, async post() { return { data: { data } }; } });
    assert.equal((await service.send({ request_id, message: 'Hóa đơn' })).action, 'invoices');
    for (const bad of [
        { ...data, action: 'https://example.test' },
        { ...data, action: null, rooms: [{ room_id: '../../login', property_id: 3, room_name: 'Phòng', property_name: 'Khách sạn' }] },
    ]) {
        data = bad;
        await assert.rejects(service.send({ request_id, message: 'Hóa đơn' }));
    }
});

test('public chat exposes Retry-After and does not retry provider errors', async () => {
    let attempts = 0;
    const service = createGuestChatbotService({
        async get() {},
        async post() {
            attempts++;
            throw { response: { status: 429, headers: { 'retry-after': '12' } } };
        },
    });
    await assert.rejects(service.send({ request_id, message: 'Tôi cần tư vấn' }), { status: 429, retryAfter: 12 });
    assert.equal(attempts, 1);
});
