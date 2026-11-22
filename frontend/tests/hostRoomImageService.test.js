import test, { mock } from 'node:test';
import assert from 'node:assert/strict';
import { AxiosError, CanceledError } from 'axios';
import { mapHostRoomImages } from '../src/mappers/hostRoomImageMapper.js';

mock.module(new URL('../src/api/axios.js', import.meta.url).href, { defaultExport: {} });
const { createHostRoomImageService } = await import('../src/services/hostRoomImageService.js');
test.after(() => mock.restoreAll());

const image = { id: 4, url: 'https://example.test/room.png', is_primary: true };
const envelope = data => ({ success: true, message: 'Fixture', data });
const file = () => new File(['fixture'], 'room.png', { type: 'image/png' });

test('primary/delete use production transport, CSRF and mapper without body or automatic replay', async () => {
    for (const kind of ['primary', 'delete']) {
        const signal = new AbortController().signal;
        const calls = [];
        const client = {
            get: async (url, options) => { calls.push(url); assert.deepEqual(options, { signal, timeout: 10000 }); },
            patch: async (url, body, options) => {
                calls.push(url); assert.equal(body, undefined); assert.deepEqual(options, { signal, timeout: 10000 });
                return { status: 200, data: envelope([image]) };
            },
            delete: async (url, options) => {
                calls.push(url); assert.deepEqual(options, { signal, timeout: 10000 });
                return { status: 200, data: envelope({ deleted_image_id: 4, images: [], cleanup_status: 'failed' }) };
            },
        };
        const service = createHostRoomImageService(client);
        const result = kind === 'primary' ? await service.makePrimary(7, 4, { signal }) : await service.deleteImage(7, 4, { signal });
        assert.deepEqual(calls, ['/sanctum/csrf-cookie', '/api/host/rooms/7/images/4' + (kind === 'primary' ? '/primary' : '')]);
        assert.deepEqual(result, kind === 'primary' ? [image] : { deleted_image_id: 4, images: [], cleanup_status: 'failed' });
    }
});

test('action validation, CSRF failure and HTTP/cancellation identity preserve retry boundaries', async () => {
    for (const method of ['makePrimary', 'deleteImage']) {
        for (const id of ['01', '0', '1e2', -1, {}, null]) {
            await assert.rejects(createHostRoomImageService({ get: () => assert.fail('Unexpected request') })[method](7, id), error => error.isLocalValidation);
        }
        const csrf = new AxiosError('csrf'); csrf.response = { status: 419 };
        await assert.rejects(createHostRoomImageService({ get: async () => { throw csrf; } })[method](7, 4), error => error === csrf && !error.outcomeUnknown);
        for (const status of [401, 403, 404, 409, 419, 422, 500, 0]) {
            const failure = new AxiosError('fixture'); if (status) failure.response = { status };
            let calls = 0;
            const send = async () => { calls++; throw failure; };
            await assert.rejects(createHostRoomImageService({ get: async () => {}, patch: send, delete: send })[method](7, 4),
                error => error === failure && error.outcomeUnknown === (!status || status >= 500));
            assert.equal(calls, 1);
        }
        const canceled = new CanceledError();
        const send = async () => { throw canceled; };
        await assert.rejects(createHostRoomImageService({ get: async () => {}, patch: send, delete: send })[method](7, 4),
            error => error === canceled && error.outcomeUnknown);
    }
});

test('malformed action success is uncertain, including wrong IDs, stale lists and invalid cleanup status', async () => {
    for (const [method, bodies] of [
        ['makePrimary', [envelope([]), envelope([{ ...image, is_primary: false }]), envelope([{ ...image, id: 5 }]), envelope([image, { ...image, id: 5 }])]],
        ['deleteImage', [envelope({ deleted_image_id: 5, images: [], cleanup_status: 'deleted' }),
            envelope({ deleted_image_id: 4, images: [image], cleanup_status: 'deleted' }),
            envelope({ deleted_image_id: 4, images: [], cleanup_status: 'unknown' })]],
    ]) {
        for (const body of bodies) {
            const send = async () => ({ status: 200, data: body });
            await assert.rejects(createHostRoomImageService({ get: async () => {}, patch: send, delete: send })[method](7, 4), error => error.outcomeUnknown);
        }
    }
});

test('list uses production API/service/mapper with signal and no unrelated query', async () => {
    const signal = new AbortController().signal;
    const original = envelope([image]);
    const before = structuredClone(original);
    const service = createHostRoomImageService({ get: async (url, options) => {
        assert.equal(url, '/api/host/rooms/7/images');
        assert.deepEqual(options, { signal, timeout: 10000 });
        return { status: 200, data: original };
    } });
    const result = await service.list('7', { signal, params: { roomId: 99 } });
    assert.deepEqual(result, [image]);
    assert.notEqual(result[0], original.data[0]);
    assert.deepEqual(original, before);
    assert.deepEqual(mapHostRoomImages(envelope([])), []);
});

test('upload sends only image FormData after CSRF and keeps browser multipart boundary', async () => {
    const signal = new AbortController().signal;
    const calls = [];
    const selected = file();
    const service = createHostRoomImageService({
        get: async (url, options) => { calls.push(url); assert.deepEqual(options, { signal, timeout: 10000 }); },
        post: async (url, body, options) => {
            calls.push(url);
            assert.ok(body instanceof FormData);
            assert.deepEqual([...body.keys()], ['image']);
            assert.equal(body.get('image').name, 'room.png');
            assert.equal(await body.get('image').text(), 'fixture');
            assert.deepEqual(options, { signal, timeout: 30000 });
            return { status: 201, data: envelope(image) };
        },
    });
    assert.deepEqual(await service.upload(7, selected, { signal }), image);
    assert.deepEqual(calls, ['/sanctum/csrf-cookie', '/api/host/rooms/7/images']);
});

test('invalid IDs and files fail before transport', async () => {
    const service = createHostRoomImageService({ get: () => assert.fail('Unexpected GET'), post: () => assert.fail('Unexpected POST') });
    for (const id of [0, -1, '01', '1e2', true, null, {}, Number.MAX_SAFE_INTEGER + 1]) {
        await assert.rejects(service.list(id), error => error.isLocalValidation);
        await assert.rejects(service.upload(id, file()), error => error.isLocalValidation);
    }
    for (const selected of [null, {}, new File([], 'empty.png', { type: 'image/png' }),
        new File(['svg'], 'image.svg', { type: 'image/svg+xml' }),
        new File([new Uint8Array(5 * 1024 * 1024 + 1)], 'huge.png', { type: 'image/png' })]) {
        await assert.rejects(service.upload(7, selected), error => error.isLocalValidation);
    }
});

test('malformed lists and unsafe URLs never become valid empty galleries', async () => {
    for (const body of [null, {}, envelope(null), envelope([image, image]),
        ...[{ id: '4' }, { id: 0 }, { is_primary: 1 }, { url: 'javascript:alert(1)' },
            { url: 'https://user:secret@example.test/a.png' }, { url: 'rooms/relative.png' }]
            .map(change => envelope([{ ...image, ...change }]))]) {
        await assert.rejects(createHostRoomImageService({ get: async () => ({ status: 200, data: body }) }).list(7));
    }
});

test('CSRF failure never submits a mutation and does not mark outcome unknown', async () => {
    const failure = new AxiosError('csrf'); failure.response = { status: 419 };
    const service = createHostRoomImageService({ get: async () => { throw failure; }, post: () => assert.fail('No POST after failed CSRF') });
    await assert.rejects(service.upload(7, file()), error => error === failure && !error.outcomeUnknown);
});

test('upload preserves HTTP errors and only uncertain submitted outcomes require reconciliation', async () => {
    for (const status of [401, 403, 404, 409, 419, 422, 500, 0]) {
        let posts = 0;
        const failure = new AxiosError('fixture'); if (status) failure.response = { status };
        const service = createHostRoomImageService({ get: async () => {}, post: async () => { posts++; throw failure; } });
        await assert.rejects(service.upload(7, file()), error => error === failure && error.outcomeUnknown === (!status || status >= 500));
        assert.equal(posts, 1);
    }
    const canceled = new CanceledError();
    await assert.rejects(createHostRoomImageService({ get: async () => {}, post: async () => { throw canceled; } }).upload(7, file()),
        error => error === canceled && error.outcomeUnknown);
});

test('malformed success or wrong upload status is uncertain; list cancellation preserves identity', async () => {
    for (const response of [{ status: 200, data: envelope(image) }, { status: 201, data: {} }]) {
        const service = createHostRoomImageService({ get: async () => {}, post: async () => response });
        await assert.rejects(service.upload(7, file()), error => error.outcomeUnknown === true);
    }
    const failure = new CanceledError();
    await assert.rejects(createHostRoomImageService({ get: async () => { throw failure; } }).list(7), error => error === failure);
});
