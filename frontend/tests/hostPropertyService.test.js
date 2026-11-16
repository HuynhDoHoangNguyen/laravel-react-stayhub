import test, { mock } from 'node:test';
import assert from 'node:assert/strict';
import axios, { AxiosError } from 'axios';

let adapter;
const client = axios.create({ adapter: (config) => adapter(config) });
mock.module(new URL('../src/api/axios.js', import.meta.url).href, { defaultExport: client });
const { createHostPropertyService, hostPropertyService } = await import('../src/services/hostPropertyService.js');
test.after(() => mock.restoreAll());

test('delete room type uses CSRF and sends no body, with the expected ID in the response', async () => {
    const urls = [];
    const { signal } = new AbortController();
    adapter = async (config) => {
        urls.push(config.url);
        assert.equal(config.signal, signal);
        assert.equal(config.timeout, 10000);
        if (config.method === 'get') return { config, status: 204 };
        assert.equal(config.method, 'delete');
        assert.equal(config.data, undefined);
        assert.equal(config.params, undefined);
        return { config, status: 200, data: {
            success: true, message: 'Fixture', data: { deleted_room_type_id: 7 },
        } };
    };
    assert.deepEqual(await hostPropertyService.deleteRoomType('7', { signal }), { deleted_room_type_id: 7 });
    assert.deepEqual(urls, ['/sanctum/csrf-cookie', '/api/host/room-types/7']);
});

test('delete room type distinguishes HTTP rejection from uncertain results and does not replay', async () => {
    await assert.rejects(createHostPropertyService({ get: () => assert.fail('Unexpected HTTP') })
        .deleteRoomType('01'), (error) => error.status === 404);
    for (const status of [401, 403, 404, 409, 419, 422, 500, undefined]) {
        const error = new AxiosError('Fixture');
        if (status) error.response = { status };
        let requests = 0;
        const service = createHostPropertyService({ get: async () => {}, delete: async () => { requests++; throw error; } });
        await assert.rejects(service.deleteRoomType(7),
            (failure) => failure === error && failure.outcomeUnknown === (!status || status >= 500));
        assert.equal(requests, 1);
    }
    const invalid = createHostPropertyService({ get: async () => {}, delete: async () => ({
        status: 200, data: { success: true, message: 'Fixture', data: { deleted_room_type_id: 8 } },
    }) });
    await assert.rejects(invalid.deleteRoomType(7), (error) => error.outcomeUnknown);
});

test('update room type sends two fields after CSRF and projects the requested record', async () => {
    const urls = [];
    const { signal } = new AbortController();
    adapter = async (config) => {
        urls.push(config.url);
        assert.equal(config.signal, signal);
        assert.equal(config.timeout, 10000);
        if (config.method === 'get') return { config, status: 204 };
        assert.equal(config.method, 'put');
        assert.deepEqual(JSON.parse(config.data), { name: 'Suite', description: null });
        return { config, status: 200, data: {
            success: true, message: 'Fixture', data: { id: 7, name: 'Suite', description: null, property_id: 12 },
        } };
    };
    assert.deepEqual(await hostPropertyService.updateRoomType('7', { name: 'Suite', property_id: 99 }, { signal }),
        { id: 7, name: 'Suite', description: null });
    assert.deepEqual(urls, ['/sanctum/csrf-cookie', '/api/host/room-types/7']);
});

test('update room type rejects bad ID without HTTP and never replays a failed submission', async () => {
    await assert.rejects(createHostPropertyService({ get: () => assert.fail('Unexpected GET') })
        .updateRoomType('01', {}), (error) => error.status === 404);
    for (const status of [401, 403, 404, 409, 419, 422, 500, undefined]) {
        let submissions = 0;
        const error = new AxiosError('Fixture');
        if (status) error.response = { status };
        const service = createHostPropertyService({ get: async () => {}, put: async () => { submissions++; throw error; } });
        await assert.rejects(service.updateRoomType(7, { name: 'Suite' }),
            (failure) => failure === error && failure.outcomeUnknown === (!status || status >= 500));
        assert.equal(submissions, 1);
    }
    const error = new AxiosError('CSRF network failure');
    await assert.rejects(createHostPropertyService({
        get: async () => { throw error; }, put: () => assert.fail('Unexpected PUT'),
    }).updateRoomType(7, { name: 'Suite' }), (failure) => failure === error && !failure.outcomeUnknown);
});

test('update room type requires 200 and the correct record and projection', async () => {
    const valid = { id: 7, name: 'Suite', description: null };
    for (const [status, data] of [[201, valid], [200, { ...valid, id: 8 }], [200, { ...valid, description: 2 }]]) {
        const service = createHostPropertyService({ get: async () => {}, put: async () => ({
            status, data: { success: true, message: 'Fixture', data },
        }) });
        await assert.rejects(service.updateRoomType(7, { name: 'Suite' }), (error) => error.outcomeUnknown === true);
    }
});

test('create room type gets CSRF before POST and sends only allowed fields', async () => {
    const calls = [];
    const { signal } = new AbortController();
    adapter = async (config) => {
        calls.push(config.url);
        assert.equal(config.signal, signal);
        assert.equal(config.timeout, 10000);
        if (config.method === 'get') return { config, status: 204 };
        assert.equal(config.method, 'post');
        assert.deepEqual(JSON.parse(config.data), { name: 'Suite', description: null });
        return { config, status: 201, data: {
            success: true, message: 'Fixture', data: { id: 7, name: 'Suite', description: null, property_id: 12 },
        } };
    };
    assert.deepEqual(await hostPropertyService.createRoomType(12, { name: 'Suite', description: null, property_id: 99 }, { signal }),
        { id: 7, name: 'Suite', description: null });
    assert.deepEqual(calls, ['/sanctum/csrf-cookie', '/api/host/properties/12/room-types']);
});

test('create room type distinguishes rejected requests from unknown outcomes without replay', async () => {
    for (const status of [401, 403, 404, 409, 419, 422, 500, undefined]) {
        let submissions = 0;
        const error = new AxiosError('Fixture');
        if (status) error.response = { status };
        const service = createHostPropertyService({ get: async () => {}, post: async () => { submissions++; throw error; } });
        await assert.rejects(service.createRoomType(12, { name: 'Suite' }),
            (failure) => failure === error && failure.outcomeUnknown === (!status || status >= 500));
        assert.equal(submissions, 1);
    }
    const error = new AxiosError('CSRF network failure');
    await assert.rejects(createHostPropertyService({
        get: async () => { throw error; }, post: () => assert.fail('Unexpected POST'),
    }).createRoomType(12, { name: 'Suite' }), (failure) => failure === error && !failure.outcomeUnknown);
    await assert.rejects(createHostPropertyService({
        get: () => assert.fail('Unexpected GET'),
    }).createRoomType('01', { name: 'Suite' }), (failure) => failure.status === 404);
});

test('create room type requires 201 and a valid projection', async () => {
    for (const status of [200, 201]) {
        const service = createHostPropertyService({ get: async () => {}, post: async () => ({
            status, data: { success: true, message: 'Fixture', data: { id: 1, name: 'Suite' } },
        }) });
        await assert.rejects(service.createRoomType(12, { name: 'Suite' }), (error) => error.outcomeUnknown === true);
    }
});

test('room type list uses shared Axios with signal and projects only the approved fields', async () => {
    const { signal } = new AbortController();
    adapter = async (config) => {
        assert.equal(config.url, '/api/host/properties/12/room-types');
        assert.equal(config.method, 'get');
        assert.equal(config.timeout, 10000);
        assert.equal(config.signal, signal);
        assert.equal(config.params, undefined);
        return { config, status: 200, data: { success: true, message: 'Fixture', data: [
            { id: 4, name: 'Family', description: null, property_id: 12 },
        ] } };
    };
    assert.deepEqual(await hostPropertyService.listRoomTypes('12', { signal }), [
        { id: 4, name: 'Family', description: null },
    ]);
});

test('room type list accepts an empty result and rejects malformed responses', async () => {
    const body = { success: true, message: 'Fixture', data: [] };
    const service = createHostPropertyService({ get: async () => ({ data: body }) });
    assert.deepEqual(await service.listRoomTypes(12), []);
    for (const data of [null, {}, [null], [{ id: '1', name: 'Family', description: null }],
        [{ id: 1, name: 'Family' }], [{ id: 1, name: 'Family', description: 5 }]]) {
        body.data = data;
        await assert.rejects(service.listRoomTypes(12), /Phản hồi danh sách loại phòng không hợp lệ/);
    }
});

test('room type list rejects invalid IDs without calling HTTP and preserves request failures', async () => {
    const invalid = createHostPropertyService({ get: () => assert.fail('Unexpected request') });
    for (const id of [0, '01', 'abc', '9007199254740992']) {
        await assert.rejects(invalid.listRoomTypes(id), (error) => error.status === 404);
    }
    for (const status of [401, 403, 404, 422, 500, undefined]) {
        const failure = new AxiosError('Fixture failure');
        if (status) failure.response = { status };
        const service = createHostPropertyService({ get: async () => { throw failure; } });
        await assert.rejects(service.listRoomTypes(12), (error) => error === failure);
    }
    const controller = new AbortController();
    controller.abort();
    const canceled = createHostPropertyService(axios.create({ adapter: () => assert.fail('Unexpected request') }));
    await assert.rejects(canceled.listRoomTypes(12, { signal: controller.signal }), axios.isCancel);
});

test('production Host service calls the shared client and projects id/name only', async () => {
    const signal = new AbortController().signal;
    adapter = async (config) => {
        assert.equal(config.url, '/api/host/properties');
        assert.equal(config.signal, signal);
        assert.equal(config.timeout, 10000);
        assert.equal(config.params, undefined);
        return { config, status: 200, headers: {}, data: {
            success: true, message: 'Danh sách cơ sở lưu trú của host.',
            data: [{ id: 2, name: 'Cơ sở', private: 'omitted' }],
        } };
    };
    assert.deepEqual(await hostPropertyService.list({ signal }), [{ id: 2, name: 'Cơ sở' }]);
});

test('empty owned collection succeeds and malformed responses fail', async () => {
    const body = { success: true, message: 'Fixture', data: [] };
    const service = createHostPropertyService({ get: async () => ({ data: body }) });
    assert.deepEqual(await service.list(), []);
    for (const invalid of [null, {}, { ...body, success: false }, { ...body, data: {} },
        { ...body, data: [{ id: '2', name: 'Invalid' }] }, { ...body, data: [{ id: 1 }] }]) {
        await assert.rejects(createHostPropertyService({ get: async () => ({ data: invalid }) }).list());
    }
});

test('HTTP and network failures preserve rejection identity without retry', async () => {
    for (const status of [401, 403, 404, 422, 500, 0]) {
        const failure = new AxiosError('Fixture failure');
        if (status) failure.response = { status };
        let calls = 0;
        const service = createHostPropertyService({ get: async () => { calls++; throw failure; } });
        await assert.rejects(service.list(), (error) => error === failure);
        assert.equal(calls, 1);
    }
});

test('an already canceled request never reaches the Axios adapter', async () => {
    const controller = new AbortController();
    controller.abort();
    let calls = 0;
    const service = createHostPropertyService(axios.create({ adapter: async () => { calls++; throw Error('Unexpected adapter'); } }));
    await assert.rejects(service.list({ signal: controller.signal }), axios.isCancel);
    assert.equal(calls, 0);
});

const detail = {
    id: 12, name: 'Owned', type: 'HOTEL', address: 'Address', description: null, phone: null,
    check_in_time: '14:00:00', check_out_time: '12:00:00', status: 'BLOCKED', images: [],
};

test('detail uses shared Axios, signal and the approved ten-field projection', async () => {
    const signal = new AbortController().signal;
    adapter = async (config) => {
        assert.equal(config.url, '/api/host/properties/12');
        assert.equal(config.signal, signal);
        return { config, status: 200, headers: {}, data: { success: true, message: 'Detail', data: { ...detail, host_id: 99 } } };
    };
    assert.deepEqual(await hostPropertyService.detail('12', { signal }), detail);
});

test('detail rejects malformed success and mismatched identity', async () => {
    for (const data of [null, { ...detail, id: 2 }, { ...detail, images: null }, { ...detail, phone: 123 },
        { ...detail, status: 'UNKNOWN' }, { ...detail, type: 'UNKNOWN' }]) {
        const service = createHostPropertyService({ get: async () => ({ data: { success: true, message: 'Detail', data } }) });
        await assert.rejects(service.detail(12));
    }
});

test('invalid detail ids never call transport and HTTP errors retain identity', async () => {
    let calls = 0;
    const service = createHostPropertyService({ get: async () => { calls++; throw Error('Unexpected call'); } });
    for (const id of ['01', '0', 'abc', '../me', '9'.repeat(40)]) {
        await assert.rejects(service.detail(id), (error) => error.status === 404);
    }
    assert.equal(calls, 0);
    for (const status of [401, 403, 404, 500]) {
        const error = new AxiosError('Fixture');
        error.response = { status };
        await assert.rejects(createHostPropertyService({ get: async () => { throw error; } }).detail(12), (caught) => caught === error);
    }
});

test('canceled detail never reaches the adapter', async () => {
    const controller = new AbortController();
    controller.abort();
    const client = axios.create({ adapter: async () => { throw Error('Unexpected adapter'); } });
    await assert.rejects(createHostPropertyService(client).detail(12, { signal: controller.signal }), axios.isCancel);
});

test('create initializes CSRF and sends only approved fields once', async () => {
    const calls = [];
    const signal = new AbortController().signal;
    const service = createHostPropertyService({
        get: async (url, options) => { calls.push(url); assert.equal(options.signal, signal); },
        post: async (url, payload, options) => {
            calls.push(url);
            assert.equal(options.signal, signal);
            assert.deepEqual(payload, { name: 'Owned', type: 'HOTEL', address: 'Address' });
            return { status: 201, data: { success: true, message: 'Created', data: { ...detail, status: 'ACTIVE' } } };
        },
    });
    const created = await service.create({ name: 'Owned', type: 'HOTEL', address: 'Address', host_id: 99, status: 'BLOCKED' }, { signal });
    assert.equal(created.status, 'ACTIVE');
    assert.deepEqual(calls, ['/sanctum/csrf-cookie', '/api/host/properties']);
});

test('create validation errors are known and a sent timeout is uncertain without replay', async () => {
    for (const status of [422, 419, 403, 500, 0]) {
        const failure = new AxiosError('Fixture');
        if (status) failure.response = { status, data: { errors: { name: ['Fixture'] } } };
        let posts = 0;
        const service = createHostPropertyService({ get: async () => {}, post: async () => { posts++; throw failure; } });
        await assert.rejects(service.create({}), (caught) => caught === failure && caught.outcomeUnknown === (!status || status >= 500));
        assert.equal(posts, 1);
    }
});

test('CSRF failure sends no mutation and malformed create success is uncertain', async () => {
    let posts = 0;
    const failure = new AxiosError('CSRF unavailable');
    await assert.rejects(createHostPropertyService({
        get: async () => { throw failure; },
        post: async () => { posts++; },
    }).create({}), (caught) => caught === failure && caught.outcomeUnknown === false);
    assert.equal(posts, 0);
    await assert.rejects(createHostPropertyService({
        get: async () => {}, post: async () => ({ status: 201, data: {} }),
    }).create({}), (caught) => caught.outcomeUnknown === true);
});

test('update uses CSRF and PUT once, projects seven fields and checks matching detail identity', async () => {
    const calls = [];
    const signal = new AbortController().signal;
    const values = { name: 'Updated', type: 'HOMESTAY', address: 'Address', description: null,
        phone: '+0901234567', check_in_time: '15:30', check_out_time: '11:45' };
    adapter = async (config) => {
        calls.push([config.method, config.url]);
        assert.equal(config.signal, signal);
        assert.equal(config.timeout, 10000);
        if (config.method === 'put') {
            assert.deepEqual(JSON.parse(config.data), values);
            return { config, status: 200, headers: {}, data: { success: true, message: 'Updated', data: { ...detail, ...values, host_id: 99 } } };
        }
        return { config, status: 204, headers: {}, data: '' };
    };
    assert.deepEqual(await hostPropertyService.update(12, { ...values, host_id: 99, status: 'ACTIVE', images: [] }, { signal }), { ...detail, ...values });
    assert.deepEqual(calls, [['get', '/sanctum/csrf-cookie'], ['put', '/api/host/properties/12']]);
});

test('update invalid ids and CSRF failures send no mutation', async () => {
    let calls = 0;
    const failure = new AxiosError('CSRF unavailable');
    const service = createHostPropertyService({ get: async () => { calls++; throw failure; }, put: async () => { throw Error('Unexpected PUT'); } });
    for (const id of ['01', '0', '../me', 'abc', '9'.repeat(40)]) {
        await assert.rejects(service.update(id, {}), (error) => error.status === 404);
    }
    assert.equal(calls, 0);
    await assert.rejects(service.update(12, {}), (error) => error === failure && error.outcomeUnknown === false);
    assert.equal(calls, 1);
});

test('update retains errors and never replays known or ambiguous failures', async () => {
    for (const status of [401, 403, 404, 419, 422, 500, 0]) {
        const failure = new AxiosError('Fixture');
        if (status) failure.response = { status, data: { errors: { name: ['Invalid'] } } };
        let puts = 0;
        const service = createHostPropertyService({ get: async () => {}, put: async () => { puts++; throw failure; } });
        await assert.rejects(service.update(12, {}), (error) => error === failure && error.outcomeUnknown === (!status || status >= 500));
        assert.equal(puts, 1);
    }
});

test('update treats malformed success as uncertain and cancellation never reaches adapter', async () => {
    for (const response of [{ status: 200, data: {} }, { status: 201 },
        { status: 200, data: { success: true, message: 'Updated', data: { ...detail, id: 13 } } }]) {
        await assert.rejects(createHostPropertyService({ get: async () => {}, put: async () => response }).update(12, {}),
            (error) => error.outcomeUnknown === true);
    }
    const controller = new AbortController();
    controller.abort();
    let calls = 0;
    const service = createHostPropertyService(axios.create({ adapter: async () => { calls++; throw Error('Unexpected request'); } }));
    await assert.rejects(service.update(12, {}, { signal: controller.signal }), axios.isCancel);
    assert.equal(calls, 0);
});

test('status change initializes CSRF and sends exactly the two approved fields once', async () => {
    const signal = new AbortController().signal;
    const calls = [];
    adapter = async (config) => {
        calls.push([config.method, config.url]);
        assert.equal(config.signal, signal);
        assert.equal(config.timeout, 10000);
        if (config.method === 'patch') {
            assert.deepEqual(JSON.parse(config.data), { status: 'INACTIVE', expected_status: 'ACTIVE' });
            return { config, status: 200, headers: {}, data: { success: true, message: 'Changed', data: { ...detail, status: 'INACTIVE' } } };
        }
        return { config, status: 204, headers: {}, data: '' };
    };
    assert.equal((await hostPropertyService.changeStatus(12,
        { status: 'INACTIVE', expected_status: 'ACTIVE', host_id: 99, name: 'Injected' }, { signal })).status, 'INACTIVE');
    assert.deepEqual(calls, [['get', '/sanctum/csrf-cookie'], ['patch', '/api/host/properties/12/status']]);
});

test('status change rejects invalid ids without transport and CSRF failure without mutation', async () => {
    let calls = 0;
    const failure = new AxiosError('CSRF failed');
    const service = createHostPropertyService({ get: async () => { calls++; throw failure; }, patch: async () => { throw Error('Unexpected PATCH'); } });
    for (const id of ['01', '0', '../me', 'abc', '9'.repeat(40)]) {
        await assert.rejects(service.changeStatus(id, {}), (error) => error.status === 404);
    }
    assert.equal(calls, 0);
    await assert.rejects(service.changeStatus(12, {}), (error) => error === failure && !error.outcomeUnknown);
    assert.equal(calls, 1);
});

test('status change retains rejection identity and does not replay conflicts or ambiguous failures', async () => {
    for (const status of [401, 403, 404, 409, 419, 422, 500, 0]) {
        const failure = new AxiosError('Fixture');
        if (status) failure.response = { status };
        let patches = 0;
        const service = createHostPropertyService({ get: async () => {}, patch: async () => { patches++; throw failure; } });
        await assert.rejects(service.changeStatus(12, {}), (error) => error === failure && error.outcomeUnknown === (!status || status >= 500));
        assert.equal(patches, 1);
    }
});

test('malformed status success is uncertain and canceled status request never reaches transport', async () => {
    for (const response of [{ status: 200, data: {} }, { status: 201 },
        { status: 200, data: { success: true, message: 'Changed', data: { ...detail, id: 13 } } }]) {
        await assert.rejects(createHostPropertyService({ get: async () => {}, patch: async () => response }).changeStatus(12, {}),
            (error) => error.outcomeUnknown === true);
    }
    const controller = new AbortController(); controller.abort();
    let calls = 0;
    const service = createHostPropertyService(axios.create({ adapter: async () => { calls++; throw Error('Unexpected transport'); } }));
    await assert.rejects(service.changeStatus(12, {}, { signal: controller.signal }), axios.isCancel);
    assert.equal(calls, 0);
});

test('image list projects management fields and rejects malformed responses', async () => {
    const signal = new AbortController().signal;
    adapter = async (config) => {
        assert.equal(config.url, '/api/host/properties/12/images'); assert.equal(config.signal, signal);
        return { config, status: 200, headers: {}, data: { success: true, message: 'Images', data: [{ id: 31, url: 'https://example.com/a.jpg', is_primary: true, image_path: 'hidden' }] } };
    };
    assert.deepEqual(await hostPropertyService.images(12, { signal }), [{ id: 31, url: 'https://example.com/a.jpg', is_primary: true }]);
    for (const body of [null, {}, { success: true, message: 'Images', data: [{}] },
        { success: true, message: 'Images', data: [{ id: 31, url: 'javascript:bad', is_primary: true }] }]) {
        await assert.rejects(createHostPropertyService({ get: async () => ({ data: body }) }).images(12));
    }
});

test('upload uses CSRF then multipart with exactly one image and no manual content type', async () => {
    const calls = []; const signal = new AbortController().signal; const file = new File(['fixture'], 'fixture.png', { type: 'image/png' });
    const service = createHostPropertyService({
        get: async (url, options) => { calls.push(url); assert.equal(options.signal, signal); },
        post: async (url, body, options) => {
            calls.push(url); assert.equal(options.signal, signal); assert.equal(options.timeout, 30000);
            assert.equal(options.headers, undefined); assert.deepEqual([...body.keys()], ['image']); assert.equal(body.get('image'), file);
            return { status: 201, data: { success: true, message: 'Uploaded', data: { id: 31, url: 'https://example.com/a.png', is_primary: true } } };
        },
    });
    assert.equal((await service.uploadImage(12, file, { signal })).id, 31);
    assert.deepEqual(calls, ['/sanctum/csrf-cookie', '/api/host/properties/12/images']);
});

test('image operations reject invalid ids and retain HTTP errors without retries', async () => {
    let calls = 0; const file = new File(['fixture'], 'a.png');
    const service = createHostPropertyService({ get: async () => { calls++; }, post: async () => { calls++; } });
    for (const id of ['01', '../me', '0', 'x', '9'.repeat(40)]) {
        await assert.rejects(service.images(id), (error) => error.status === 404);
        await assert.rejects(service.uploadImage(id, file), (error) => error.status === 404);
    }
    assert.equal(calls, 0);
    for (const status of [401, 403, 404, 409, 419, 422, 500, 0]) {
        const failure = new AxiosError('Fixture'); if (status) failure.response = { status };
        let posts = 0;
        const failing = createHostPropertyService({ get: async () => {}, post: async () => { posts++; throw failure; } });
        await assert.rejects(failing.uploadImage(12, file), (error) => error === failure && error.outcomeUnknown === (!status || status >= 500));
        assert.equal(posts, 1);
        await assert.rejects(createHostPropertyService({ get: async () => { throw failure; } }).images(12), (error) => error === failure);
    }
});

test('upload CSRF failure sends no mutation; malformed success is uncertain; cancellation never reaches adapter', async () => {
    const file = new File(['fixture'], 'a.png'); const failure = new AxiosError('CSRF failed'); let posts = 0;
    await assert.rejects(createHostPropertyService({ get: async () => { throw failure; }, post: async () => { posts++; } }).uploadImage(12, file),
        (error) => error === failure && error.outcomeUnknown === false);
    assert.equal(posts, 0);
    for (const response of [{ status: 201, data: {} }, { status: 200 }, { status: 201, data: { success: true, message: 'Uploaded', data: { id: '31' } } }]) {
        await assert.rejects(createHostPropertyService({ get: async () => {}, post: async () => response }).uploadImage(12, file), (error) => error.outcomeUnknown === true);
    }
    const controller = new AbortController(); controller.abort(); let requests = 0;
    const canceled = createHostPropertyService(axios.create({ adapter: async () => { requests++; throw Error('Unexpected request'); } }));
    await assert.rejects(canceled.images(12, { signal: controller.signal }), axios.isCancel);
    await assert.rejects(canceled.uploadImage(12, file, { signal: controller.signal }), axios.isCancel);
    assert.equal(requests, 0);
});

test('primary and delete image mutations use CSRF, exact URLs, signal and empty body', async () => {
    const signal = new AbortController().signal; const calls = [];
    const image = { id: 31, url: 'https://example.com/a.png', is_primary: true };
    const service = createHostPropertyService({
        get: async (url, options) => { calls.push(url); assert.equal(options.signal, signal); },
        patch: async (url, body, options) => {
            calls.push(url); assert.equal(body, undefined); assert.equal(options.signal, signal); assert.equal(options.timeout, 10000);
            return { status: 200, data: { success: true, message: 'Primary', data: [{ ...image, private: 'omitted' }] } };
        },
        delete: async (url, options) => {
            calls.push(url); assert.equal(options.data, undefined); assert.equal(options.signal, signal);
            return { status: 200, data: { success: true, message: 'Deleted', data: { deleted_image_id: 31, images: [], cleanup_status: 'deleted', private: 'omitted' } } };
        },
    });
    assert.deepEqual(await service.makePrimaryImage(12, 31, { signal }), [image]);
    assert.deepEqual(await service.deleteImage(12, 31, { signal }), { deleted_image_id: 31, images: [], cleanup_status: 'deleted' });
    assert.deepEqual(calls, ['/sanctum/csrf-cookie', '/api/host/properties/12/images/31/primary', '/sanctum/csrf-cookie', '/api/host/properties/12/images/31']);
});

test('image mutation ids fail before transport and errors preserve identity without replay', async () => {
    let calls = 0; const invalid = createHostPropertyService({ get: async () => { calls++; } });
    for (const method of ['makePrimaryImage', 'deleteImage']) {
        for (const [pid, iid] of [['01', 31], [12, '0'], [12, '../me'], [12, '9'.repeat(40)]]) {
            await assert.rejects(invalid[method](pid, iid), (error) => error.status === 404);
        }
        for (const status of [401, 403, 404, 409, 419, 422, 500, 0]) {
            const failure = new AxiosError('Fixture'); if (status) failure.response = { status };
            let mutations = 0; const fail = async () => { mutations++; throw failure; };
            const service = createHostPropertyService({ get: async () => {}, patch: fail, delete: fail });
            await assert.rejects(service[method](12, 31), (error) => error === failure && error.outcomeUnknown === (!status || status >= 500));
            assert.equal(mutations, 1);
        }
    }
    assert.equal(calls, 0);
});

test('image mutations reject malformed success and primary mismatch; cleanup failed remains a committed result', async () => {
    const image = { id: 31, url: 'https://example.com/a.png', is_primary: false };
    for (const body of [{}, { success: true, message: 'Primary', data: [image] },
        { success: true, message: 'Primary', data: [{ ...image, id: 32, is_primary: true }] }]) {
        await assert.rejects(createHostPropertyService({ get: async () => {}, patch: async () => ({ status: 200, data: body }) }).makePrimaryImage(12, 31),
            (error) => error.outcomeUnknown === true);
    }
    for (const cleanup_status of ['deleted', 'missing', 'not_managed', 'shared', 'failed']) {
        const result = { deleted_image_id: 31, images: [], cleanup_status };
        assert.deepEqual(await createHostPropertyService({ get: async () => {}, delete: async () => ({ status: 200, data: { success: true, message: 'Deleted', data: result } }) }).deleteImage(12, 31), result);
    }
    for (const data of [{ deleted_image_id: 32, images: [], cleanup_status: 'deleted' },
        { deleted_image_id: 31, images: [image], cleanup_status: 'deleted' }, { deleted_image_id: 31, images: [], cleanup_status: 'unknown' }]) {
        await assert.rejects(createHostPropertyService({ get: async () => {}, delete: async () => ({ status: 200, data: { success: true, message: 'Deleted', data } }) }).deleteImage(12, 31),
            (error) => error.outcomeUnknown === true);
    }
});

test('CSRF failure and canceled image mutations send no write', async () => {
    const failure = new AxiosError('CSRF failed'); let writes = 0;
    for (const method of ['makePrimaryImage', 'deleteImage']) {
        const service = createHostPropertyService({ get: async () => { throw failure; }, patch: async () => { writes++; }, delete: async () => { writes++; } });
        await assert.rejects(service[method](12, 31), (error) => error === failure && !error.outcomeUnknown);
        const controller = new AbortController(); controller.abort();
        const canceled = createHostPropertyService(axios.create({ adapter: async () => { writes++; throw Error('Unexpected write'); } }));
        await assert.rejects(canceled[method](12, 31, { signal: controller.signal }), axios.isCancel);
    }
    assert.equal(writes, 0);
});
