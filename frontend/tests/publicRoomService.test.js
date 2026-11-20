import test, { mock } from 'node:test';
import assert from 'node:assert/strict';
import axios, { AxiosError, CanceledError } from 'axios';
import { createPublicRoomApi } from '../src/api/publicRoomApi.js';
import { mapPublicRoomDetail } from '../src/mappers/publicRoomMapper.js';

let adapter;
const client = axios.create({ adapter: config => adapter(config) });
mock.module(new URL('../src/api/axios.js', import.meta.url).href, { defaultExport: client });
const { roomService, createRoomService } = await import('../src/services/roomService.js');
test.after(() => mock.restoreAll());

const fixture = () => ({ data: {
    id: 12, room_number: '201', name: 'Phòng Deluxe', description: null, capacity: 2,
    price_per_night: '850000.25', room_type: { id: 3, name: 'Deluxe' },
    images: ['https://example.com/room.jpg'], amenities: [{ id: 7, name: 'Wifi' }],
    property: { id: 5, name: 'Cơ sở kiểm thử', type: 'HOTEL', address: 'Địa chỉ cơ sở' },
    currency: 'VND', price_unit: 'night',
} });
const success = (data, config) => ({ data, status: 200, headers: {}, config });

test('production service uses the real domain API and mapper with one GET and AbortSignal', async () => {
    const calls = [];
    const body = fixture();
    adapter = async config => { calls.push(config); return success(body, config); };
    const signal = new AbortController().signal;
    const result = await roomService.getRoomById('12', { signal });
    assert.deepEqual(result, body);
    assert.equal(calls.length, 1);
    assert.equal(calls[0].method, 'get');
    assert.equal(calls[0].url, '/api/public/rooms/12');
    assert.equal(calls[0].params, undefined);
    assert.equal(calls[0].data, undefined);
    assert.equal(calls[0].signal, signal);
    assert.notEqual(result.data, body.data);
    assert.notEqual(result.data.property, body.data.property);
    assert.notEqual(result.data.images, body.data.images);
    assert.notEqual(result.data.amenities[0], body.data.amenities[0]);
});

test('factory preserves the domain API boundary and returns one data envelope', async () => {
    const calls = [];
    const signal = new AbortController().signal;
    const service = createRoomService({ detail: async (...args) => { calls.push(args); return { data: fixture() }; } });
    assert.deepEqual(await service.getRoomById(12, { signal }), fixture());
    assert.deepEqual(calls, [[12, { signal }]]);
});

test('mapper preserves decimal strings including zero, null, empty collections and raw display text', () => {
    for (const price of ['0.00', '0.01', '9999999999.99']) {
        const body = fixture();
        Object.assign(body.data, { price_per_night: price, images: [], amenities: [] });
        body.data.property.type = 'HOMESTAY';
        assert.deepEqual(mapPublicRoomDetail(body, 12), body);
    }
    const body = fixture();
    body.data.description = '<script>not HTML</script>\nMô tả';
    assert.equal(mapPublicRoomDetail(body, 12).data.description, body.data.description);
});

test('mapper returns only the public allowlist and never mutates the response', () => {
    const body = fixture();
    body.data.host_id = 90;
    body.data.property.host = { email: 'private@example.test' };
    body.data.room_type.internal = true;
    body.data.amenities[0].pivot = { room_id: 12 };
    const before = structuredClone(body);
    assert.deepEqual(mapPublicRoomDetail(body, 12), fixture());
    assert.deepEqual(body, before);
});

test('mapper rejects missing envelopes, null detail and malformed nested data instead of an empty room', () => {
    const invalid = [null, [], {}, { data: null }, { data: [] }, fixture().data];
    const changes = [
        room => { room.id = 13; }, room => { room.id = '12'; }, room => { delete room.room_number; },
        room => { delete room.description; }, room => { room.description = 1; },
        room => { room.capacity = 0; }, room => { room.capacity = 2147483648; },
        room => { room.price_per_night = 850000; }, room => { room.price_per_night = '-1.00'; },
        room => { room.price_per_night = '1e2'; }, room => { room.price_per_night = '01.00'; },
        room => { room.price_per_night = '1.001'; }, room => { room.price_per_night = '10000000000.00'; },
        room => { room.room_type = null; }, room => { room.room_type.id = 0; },
        room => { room.images = [null]; }, room => { room.images = null; },
        room => { room.amenities = [{}]; }, room => { room.amenities = null; },
        room => { room.property = null; }, room => { room.property.type = 'PRIVATE'; },
        room => { room.property.address = null; }, room => { room.currency = 'USD'; },
        room => { room.price_unit = 'stay'; },
    ];
    for (const change of changes) { const body = fixture(); change(body.data); invalid.push(body); }
    for (const body of invalid) {
        assert.throws(() => mapPublicRoomDetail(body, 12), { code: 'INVALID_PUBLIC_ROOM_RESPONSE' });
    }
});

test('noncanonical IDs fail locally before any transport call', async () => {
    let calls = 0;
    adapter = async config => { calls++; return success(fixture(), config); };
    for (const id of [undefined, null, true, {}, [], 0, -1, 1.5, NaN, Infinity, '0', '01', '+12', '12 ', '12.0', '1e2', '9007199254740992']) {
        await assert.rejects(roomService.getRoomById(id), { isLocalValidation: true, status: 400 });
    }
    assert.equal(calls, 0);
});

for (const status of [401, 403, 404, 409, 422, 500]) {
    test(`HTTP ${status} keeps rejection identity and performs no fallback or automatic retry`, async () => {
        let calls = 0;
        const failure = new AxiosError('HTTP failure', 'ERR_BAD_RESPONSE', undefined, undefined,
            { status, data: { message: 'Lỗi API', errors: { room: ['Không hợp lệ'] } }, headers: {} });
        adapter = async () => { calls++; throw failure; };
        await assert.rejects(roomService.getRoomById(12), error => error === failure);
        assert.equal(calls, 1);
    });
}

for (const code of ['ERR_NETWORK', 'ECONNABORTED', 'ETIMEDOUT']) {
    test(`${code} keeps error identity for the page recovery state`, async () => {
        const failure = new AxiosError('Transport failure', code);
        adapter = async () => { throw failure; };
        await assert.rejects(roomService.getRoomById(12), error => error === failure);
    });
}

test('cancellation remains recognizable and an aborted request never returns room data', async () => {
    const controller = new AbortController();
    let finish;
    adapter = config => new Promise(resolve => { finish = () => resolve(success(fixture(), config)); });
    const pending = roomService.getRoomById(12, { signal: controller.signal });
    controller.abort();
    finish();
    await assert.rejects(pending, error => axios.isCancel(error));
    const failure = new CanceledError('Cancelled');
    adapter = async () => { throw failure; };
    await assert.rejects(roomService.getRoomById(12), error => error === failure);
});

test('domain API leaves the existing UC7 endpoints intact', async () => {
    const calls = [];
    adapter = async config => { calls.push(config); return success({}, config); };
    const api = createPublicRoomApi(client);
    await api.list({ guest_count: '2' });
    await api.filterOptions();
    assert.deepEqual(calls.map(call => call.url), ['/api/public/rooms', '/api/public/rooms/filter-options']);
    assert.deepEqual(calls[0].params, { guest_count: '2' });
});

test('malformed success rejects through the production entry point', async () => {
    adapter = async config => success({ data: null }, config);
    await assert.rejects(roomService.getRoomById(12), { code: 'INVALID_PUBLIC_ROOM_RESPONSE' });
});
