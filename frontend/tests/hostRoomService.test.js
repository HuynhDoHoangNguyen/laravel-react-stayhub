import test, { mock } from 'node:test';
import assert from 'node:assert/strict';
import { AxiosError, CanceledError } from 'axios';
import { createHostRoomApi } from '../src/api/hostRoomApi.js';
import { mapHostRoomList, mapHostRoomDetail } from '../src/mappers/hostRoomMapper.js';

const unusedClient = { get: () => assert.fail('Unexpected singleton request') };
mock.module(new URL('../src/api/axios.js', import.meta.url).href, { defaultExport: unusedClient });
const { createHostRoomService } = await import('../src/services/hostRoomService.js');
test.after(() => mock.restoreAll());

const room = (overrides = {}) => ({
    id: 7, room_number: '101', name: 'Suite', description: null, capacity: 2,
    price_per_night: '123456.78', status: 'ACTIVE', room_type: { id: 3, name: 'Standard' },
    ...overrides,
});
const envelope = (items = [room()]) => ({ success: true, message: 'Danh sách phòng của cơ sở lưu trú.', data: items });

test('API preserves GET path, signal and timeout, with no query or body', async () => {
    const signal = new AbortController().signal;
    let calls = 0;
    const api = createHostRoomApi({ get: async (url, options) => {
        calls++;
        assert.equal(url, '/api/host/properties/12/rooms');
        assert.deepEqual(options, { signal, timeout: 10000 });
        return { data: envelope() };
    } });
    assert.deepEqual(await api.list('12', { signal, params: { status: 'ACTIVE' } }), envelope());
    assert.equal(calls, 1);
});

test('invalid property IDs are rejected locally before transport', async () => {
    const service = createHostRoomService(unusedClient);
    for (const id of [0, -1, '01', '1.0', '1e2', ' 1 ', true, null, undefined, {}, [], Number.MAX_SAFE_INTEGER + 1]) {
        await assert.rejects(service.list(id), (error) => error.isLocalValidation && error.status === 400);
    }
});

test('service keeps decimal strings, nullable room type and explicit projection without mutating source', async () => {
    const input = envelope([
        room({ property_id: 99, room_type: { id: 3, name: 'Standard', property_id: 99 } }),
        room({ id: 8, status: 'MAINTENANCE', price_per_night: '0.10', room_type: null }),
        room({ id: 9, status: 'INACTIVE', description: 'Ghi chú', price_per_night: '9999999999.99' }),
    ]);
    const before = structuredClone(input);
    const service = createHostRoomService({ get: async () => ({ data: input }) });
    const result = await service.list(12);
    assert.deepEqual(result, [
        room(),
        room({ id: 8, status: 'MAINTENANCE', price_per_night: '0.10', room_type: null }),
        room({ id: 9, status: 'INACTIVE', description: 'Ghi chú', price_per_night: '9999999999.99' }),
    ]);
    assert.deepEqual(input, before);
    assert.notEqual(result[0], input.data[0]);
    assert.notEqual(result[0].room_type, input.data[0].room_type);
    assert.deepEqual(mapHostRoomList(envelope([])), []);
});

test('malformed envelopes and room fields cannot become a valid empty list', async () => {
    const invalid = [
        null, {}, { success: false, message: 'Fixture', data: [] },
        { success: true, data: [] }, { ...envelope(), data: null },
        ...[
            { id: 0 }, { id: '7' }, { room_number: 101 }, { name: null }, { description: 1 },
            { capacity: '2' }, { capacity: 1.5 }, { price_per_night: 123456.78 },
            { price_per_night: '123456.7' }, { price_per_night: '1e2' },
            { status: 'UNKNOWN' }, { room_type: undefined }, { room_type: {} },
            { room_type: { id: 3, name: null } },
        ].map((fields) => envelope([room(fields)])),
        envelope([room(), room()]),
    ];
    for (const body of invalid) {
        const service = createHostRoomService({ get: async () => ({ data: body }) });
        await assert.rejects(service.list(1), /Phản hồi danh sách phòng không hợp lệ/);
    }
});

test('HTTP errors and cancellation preserve rejection identity and never replay', async () => {
    for (const status of [401, 403, 404, 422, 500, undefined]) {
        const error = new AxiosError('Fixture');
        if (status) error.response = { status, data: { message: 'Fixture' } };
        let calls = 0;
        const service = createHostRoomService({ get: async () => { calls++; throw error; } });
        await assert.rejects(service.list(1), (failure) => failure === error);
        assert.equal(calls, 1);
    }
    const canceled = new CanceledError('Fixture cancellation');
    const service = createHostRoomService({ get: async () => { throw canceled; } });
    await assert.rejects(service.list(1), (failure) => failure === canceled);
});

const detailEnvelope = (overrides = {}) => ({
    success: true,
    message: 'Chi tiết phòng của cơ sở lưu trú.',
    data: {
        ...room(),
        property: { id: 12, name: 'Fixture property', status: 'BLOCKED' },
        images: ['https://example.test/storage/rooms/primary.png'],
        amenities: [{ id: 2, name: 'WiFi', status: false }],
        ...overrides,
    },
});

test('detail transport uses canonical room ID, signal and timeout without query or body', async () => {
    const signal = new AbortController().signal;
    let calls = 0;
    const service = createHostRoomService({ get: async (url, options) => {
        calls++;
        assert.equal(url, '/api/host/rooms/7');
        assert.deepEqual(options, { signal, timeout: 10000 });
        return { data: detailEnvelope() };
    } });
    assert.deepEqual(await service.detail('7', { signal }), detailEnvelope().data);
    assert.equal(calls, 1);
    for (const id of ['01', 0, '1e2', null, {}, Number.MAX_SAFE_INTEGER + 1]) {
        await assert.rejects(service.detail(id), (error) => error.isLocalValidation && error.status === 400);
    }
    assert.equal(calls, 1);
});

test('detail projection preserves core list fields, null, decimal, empty arrays and inactive amenities', async () => {
    const body = detailEnvelope({
        description: null,
        price_per_night: '0.10',
        room_type: null,
        property: { id: 12, name: 'Fixture property', status: 'BLOCKED', host_id: 99 },
        amenities: [{ id: 2, name: 'WiFi', status: false, description: 'Internal fixture' }],
        created_at: 'Internal timestamp',
    });
    const before = structuredClone(body);
    const result = mapHostRoomDetail(body, 7);
    assert.deepEqual(result, detailEnvelope({
        price_per_night: '0.10', room_type: null,
    }).data);
    assert.deepEqual(body, before);
    assert.notEqual(result.images, body.data.images);
    assert.notEqual(result.amenities[0], body.data.amenities[0]);
    const empty = detailEnvelope({ images: [], amenities: [] });
    assert.deepEqual(mapHostRoomDetail(empty, 7), empty.data);
    assert.deepEqual(mapHostRoomList(envelope([body.data]))[0], {
        ...room({ price_per_night: '0.10', room_type: null }),
    });
});

test('detail rejects mismatched IDs, malformed projections and unsafe image URL schemes', async () => {
    const invalid = [
        null, {}, { ...detailEnvelope(), success: false }, { ...detailEnvelope(), message: null },
        ...[
            { id: 8 }, { id: '7' }, { property: null }, { property: { id: 12, name: 'Fixture', status: 'UNKNOWN' } },
            { images: null }, { images: ['rooms/relative.png'] }, { images: ['javascript:alert(1)'] },
            { images: ['https://fixture:credential@example.test/image.png'] },
            { amenities: null }, { amenities: [{ id: 2, name: 'WiFi', status: 0 }] },
            { amenities: [{ id: 2, name: 'One', status: true }, { id: 2, name: 'Duplicate', status: false }] },
            { price_per_night: 0.10 }, { room_type: {} },
        ].map(detailEnvelope),
    ];
    for (const body of invalid) {
        const service = createHostRoomService({ get: async () => ({ data: body }) });
        await assert.rejects(service.detail(7), /Phản hồi chi tiết phòng không hợp lệ/);
    }
});

test('detail preserves HTTP errors and cancellation and never replays requests', async () => {
    for (const error of [...[401, 403, 404, 422, 500].map((status) => {
        const failure = new AxiosError('Fixture');
        failure.response = { status };
        return failure;
    }), new CanceledError('Fixture canceled')]) {
        let calls = 0;
        const service = createHostRoomService({ get: async () => { calls++; throw error; } });
        await assert.rejects(service.detail(7), (failure) => failure === error);
        assert.equal(calls, 1);
    }
});

const createValues = () => ({
    room_type_id: '3', room_number: ' 101 ', name: ' Suite ', description: ' ',
    capacity: '2', price_per_night: '123456.78',
});

test('create bootstraps CSRF, whitelists and trims fields without converting decimal strings', async () => {
    const signal = new AbortController().signal;
    const calls = [];
    const service = createHostRoomService({
        get: async (url, options) => { calls.push(url); assert.deepEqual(options, { signal, timeout: 10000 }); },
        post: async (url, payload, options) => {
            calls.push(url);
            assert.deepEqual(options, { signal, timeout: 10000 });
            assert.deepEqual(payload, {
                room_type_id: '3', room_number: '101', name: 'Suite', description: null,
                capacity: '2', price_per_night: '123456.78',
            });
            return { status: 201, data: { success: true, message: 'Thêm phòng thành công.', data: room() } };
        },
    });
    assert.deepEqual(await service.create('12', { ...createValues(), status: 'INACTIVE', property_id: 99 }, { signal }), room());
    assert.deepEqual(calls, ['/sanctum/csrf-cookie', '/api/host/properties/12/rooms']);
});

test('create validates property ID before any request and CSRF failures issue no POST', async () => {
    await assert.rejects(createHostRoomService(unusedClient).create('01', createValues()),
        (error) => error.isLocalValidation && error.status === 400);
    const failure = new AxiosError('CSRF fixture');
    const service = createHostRoomService({
        get: async () => { throw failure; },
        post: () => assert.fail('Unexpected POST'),
    });
    await assert.rejects(service.create(12, createValues()), (error) => error === failure && !error.outcomeUnknown);
});

test('create preserves known HTTP errors and marks uncertain POST results without replay', async () => {
    for (const status of [401, 403, 404, 409, 419, 422, 500, undefined]) {
        const failure = new AxiosError('POST fixture');
        if (status) failure.response = { status, data: { message: 'Fixture', errors: { name: ['Fixture'] } } };
        let posts = 0;
        const service = createHostRoomService({
            get: async () => {},
            post: async () => { posts++; throw failure; },
        });
        await assert.rejects(service.create(12, createValues()),
            (error) => error === failure && error.outcomeUnknown === (!status || status >= 500));
        assert.equal(posts, 1);
    }
});

test('invalid create success envelopes require reconciliation instead of replaying POST', async () => {
    for (const response of [
        { status: 200, data: envelope() },
        { status: 201, data: null },
        { status: 201, data: { success: true, message: 'Fixture', data: room({ status: 'INACTIVE' }) } },
        { status: 201, data: { success: true, message: 'Fixture', data: room({ room_type: null }) } },
        { status: 201, data: { success: true, message: 'Fixture', data: room({ room_type: { id: 4, name: 'Other' } }) } },
    ]) {
        let posts = 0;
        const service = createHostRoomService({
            get: async () => {},
            post: async () => { posts++; return response; },
        });
        await assert.rejects(service.create(12, createValues()), (error) => error.outcomeUnknown === true);
        assert.equal(posts, 1);
    }
});

test('update uses CSRF then PUT and preserves decimal strings and all three statuses', async () => {
    for (const status of ['ACTIVE', 'INACTIVE', 'MAINTENANCE']) {
        const calls = [];
        const signal = new AbortController().signal;
        const service = createHostRoomService({
            get: async (url, options) => { calls.push(url); assert.deepEqual(options, { signal, timeout: 10000 }); },
            put: async (url, payload, options) => {
                calls.push(url);
                assert.deepEqual(options, { signal, timeout: 10000 });
                assert.deepEqual(payload, {
                    room_type_id: '3', room_number: '101', name: 'Suite', description: null,
                    capacity: '2', price_per_night: '123456.78',
                });
                return { status: 200, data: { success: true, message: 'Cập nhật phòng thành công.', data: room({ status }) } };
            },
        });
        assert.deepEqual(await service.update(7, { ...createValues(), status: 'ACTIVE', property_id: 99 }, { signal }), room({ status }));
        assert.deepEqual(calls, ['/sanctum/csrf-cookie', '/api/host/rooms/7']);
    }
});

test('update validates room ID locally and CSRF failure never issues PUT', async () => {
    await assert.rejects(createHostRoomService(unusedClient).update('01', createValues()),
        (error) => error.isLocalValidation && error.status === 400);
    const failure = new AxiosError('CSRF fixture');
    await assert.rejects(createHostRoomService({
        get: async () => { throw failure; }, put: () => assert.fail('Unexpected PUT'),
    }).update(7, createValues()), (error) => error === failure && !error.outcomeUnknown);
});

test('update preserves HTTP errors and cancellation, classifies uncertainty and never replays', async () => {
    const failures = [...[401, 403, 404, 409, 419, 422, 500, undefined].map((status) => {
        const error = new AxiosError('PUT fixture');
        if (status) error.response = { status, data: { errors: { name: ['Fixture'] } } };
        return error;
    }), new CanceledError('Fixture canceled')];
    for (const failure of failures) {
        let puts = 0;
        const service = createHostRoomService({
            get: async () => {},
            put: async () => { puts++; throw failure; },
        });
        await assert.rejects(service.update(7, createValues()),
            (error) => error === failure && error.outcomeUnknown === (!failure.response?.status || failure.response.status >= 500));
        assert.equal(puts, 1);
    }
});

test('malformed update responses require reconciliation for mismatched identity, type and invalid fields', async () => {
    for (const response of [
        { status: 201, data: {} }, { status: 200, data: null },
        ...[room({ id: 8 }), room({ room_type: null }), room({ room_type: { id: 9, name: 'Other' } }),
            room({ capacity: 0 }), room({ price_per_night: '0.00' }), room({ price_per_night: 123456.78 }),
            room({ status: 'BOOKED' })]
            .map((data) => ({ status: 200, data: { success: true, message: 'Fixture', data } })),
    ]) {
        let puts = 0;
        const service = createHostRoomService({
            get: async () => {}, put: async () => { puts++; return response; },
        });
        await assert.rejects(service.update(7, createValues()), (error) => error.outcomeUnknown === true);
        assert.equal(puts, 1);
    }
});

test('status bootstraps CSRF and sends only status with signal/timeout, preserving baseline null/decimal projection', async () => {
    for (const status of ['ACTIVE', 'INACTIVE', 'MAINTENANCE']) {
        const signal = new AbortController().signal;
        const calls = [];
        const expected = room({ status, room_type: null });
        const service = createHostRoomService({
            get: async (url, options) => { calls.push(url); assert.deepEqual(options, { signal, timeout: 10000 }); },
            patch: async (url, payload, options) => {
                calls.push(url); assert.deepEqual(payload, { status });
                assert.deepEqual(options, { signal, timeout: 10000 });
                return { status: 200, data: { success: true, message: 'Fixture', data: expected } };
            },
        });
        assert.deepEqual(await service.changeStatus(7, { status, expected_status: 'OTHER', property_id: 99 }, { signal }), expected);
        assert.deepEqual(calls, ['/sanctum/csrf-cookie', '/api/host/rooms/7/status']);
    }
});

test('status rejects invalid local IDs and CSRF failure never issues PATCH', async () => {
    await assert.rejects(createHostRoomService(unusedClient).changeStatus('01', { status: 'ACTIVE' }),
        (error) => error.isLocalValidation && error.status === 400);
    const failure = new AxiosError('CSRF fixture');
    await assert.rejects(createHostRoomService({
        get: async () => { throw failure; }, patch: () => assert.fail('Unexpected PATCH'),
    }).changeStatus(7, { status: 'ACTIVE' }), (error) => error === failure && !error.outcomeUnknown);
});

test('status HTTP errors and cancellation preserve identity and never automatically replay PATCH', async () => {
    for (const failure of [...[401,403,404,409,419,422,500,undefined].map((status) => {
        const error = new AxiosError('PATCH fixture');
        if (status) error.response = { status, data: { message: 'Fixture', errors: { status: ['Fixture'] } } };
        return error;
    }), new CanceledError('Fixture')]) {
        let count = 0;
        const service = createHostRoomService({
            get: async () => {}, patch: async () => { count++; throw failure; },
        });
        await assert.rejects(service.changeStatus(7, { status: 'INACTIVE' }),
            (error) => error === failure && error.outcomeUnknown === (!failure.response?.status || failure.response.status >= 500));
        assert.equal(count, 1);
    }
});

test('status malformed success or unexpected room/target requires reconciliation', async () => {
    for (const response of [
        { status: 201, data: {} }, { status: 200, data: null },
        ...[room({ id: 8, status: 'INACTIVE' }), room({ status: 'ACTIVE' }),
            room({ status: 'INACTIVE', price_per_night: 100 }), room({ status: 'INACTIVE', capacity: '2' })]
            .map((data) => ({ status: 200, data: { success: true, message: 'Fixture', data } })),
    ]) {
        let count = 0;
        const service = createHostRoomService({
            get: async () => {}, patch: async () => { count++; return response; },
        });
        await assert.rejects(service.changeStatus(7, { status: 'INACTIVE' }), (error) => error.outcomeUnknown === true);
        assert.equal(count, 1);
    }
});
