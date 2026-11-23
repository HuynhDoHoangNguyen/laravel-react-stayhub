import test, { mock } from 'node:test';
import assert from 'node:assert/strict';
import { CanceledError } from 'axios';

const unusedClient = { get: () => assert.fail('Unexpected singleton request') };
mock.module(new URL('../src/api/axios.js', import.meta.url).href, { defaultExport: unusedClient });
const { createHostRoomMaintenanceService } = await import('../src/services/hostRoomMaintenanceService.js');
test.after(() => mock.restoreAll());

const maintenance = { id: 2, start_date: '2026-12-01', end_date: '2026-12-03', reason: null, status: 'SCHEDULED' };
const response = (data, meta = {}) => ({
    status: 200,
    data: { data, meta: { current_page: 1, last_page: 1, per_page: 15, total: data.length, ...meta }, links: {} },
});

test('read uses the scoped GET, page and signal and projects only display fields', async () => {
    const { signal } = new AbortController();
    const legacy = { ...maintenance, id: 3, start_date: '0000-00-00', reason: 'Legacy', status: 'COMPLETED' };
    const input = response([{ ...maintenance, room_id: 7 }, legacy], { current_page: 2, last_page: 2, total: 17 });
    const service = createHostRoomMaintenanceService({
        get: async (url, options) => {
            assert.equal(url, '/api/host/rooms/7/maintenances');
            assert.deepEqual(options, { params: { page: 2 }, signal, timeout: 10000 });
            return input;
        },
    });
    assert.deepEqual(await service.list('7', '2', { signal }), {
        data: [maintenance, legacy], meta: { current_page: 2, last_page: 2, total: 17 },
    });
    assert.equal(input.data.data[0].room_id, 7);
});

test('distinguishes empty pages and rejects a page that does not match the requested page', async () => {
    const empty = createHostRoomMaintenanceService({ get: async () => response([]) });
    assert.deepEqual(await empty.list(7), { data: [], meta: { current_page: 1, last_page: 1, total: 0 } });
    await assert.rejects(empty.list(7, 2), /không khớp/);
    const beyond = createHostRoomMaintenanceService({
        get: async () => response([], { current_page: 3, last_page: 2, total: 16 }),
    });
    assert.deepEqual(await beyond.list(7, 3), { data: [], meta: { current_page: 3, last_page: 2, total: 16 } });
});

test('rejects malformed lists, duplicate or unknown rows and invalid pagination', async () => {
    for (const input of [
        response([{ ...maintenance, reason: false }]),
        response([{ ...maintenance, status: 'NEW' }]),
        response([maintenance, maintenance]),
        response([], { per_page: 50 }),
        response([maintenance], { total: 0 }),
        { status: 200, data: { data: [] } },
    ]) {
        const service = createHostRoomMaintenanceService({ get: async () => input });
        await assert.rejects(service.list(7), /không hợp lệ/);
    }
});

test('invalid IDs fail before transport and HTTP or cancellation errors preserve identity', async () => {
    const blocked = createHostRoomMaintenanceService(unusedClient);
    await assert.rejects(blocked.list('07'), (error) => error.isLocalValidation);
    await assert.rejects(blocked.list(7, 0), (error) => error.isLocalValidation);
    for (const failure of [new CanceledError(), Object.assign(new Error('Forbidden'), { response: { status: 403 } })]) {
        const service = createHostRoomMaintenanceService({ get: async () => { throw failure; } });
        await assert.rejects(service.list(7), (error) => error === failure);
    }
});

test('create uses the scoped POST, whitelists the payload, and maps the confirmed schedule', async () => {
    const { signal } = new AbortController();
    const { start_date, end_date } = maintenance;
    const service = createHostRoomMaintenanceService({
        post: async (url, body, options) => {
            assert.equal(url, '/api/host/rooms/7/maintenances');
            assert.deepEqual(body, { start_date, end_date, reason: 'Vệ sinh máy lạnh' });
            assert.deepEqual(options, { signal, timeout: 10000 });
            return { status: 201, data: { data: { ...maintenance, reason: body.reason, room_id: 7 } } };
        },
    });
    assert.deepEqual(await service.create('7', { start_date, end_date, reason: ' Vệ sinh máy lạnh ', status: 'COMPLETED' }, { signal }),
        { ...maintenance, reason: 'Vệ sinh máy lạnh' });
});

test('create sends an empty reason as null and rejects missing or mismatched confirmation', async () => {
    for (const input of [
        { status: 200, data: { data: maintenance } },
        { status: 201, data: {} },
        { status: 201, data: { data: { ...maintenance, status: 'COMPLETED' } } },
        { status: 201, data: { data: { ...maintenance, end_date: '2026-12-05' } } },
    ]) {
        const service = createHostRoomMaintenanceService({
            post: async (_url, body) => {
                assert.equal(body.reason, null);
                return input;
            },
        });
        await assert.rejects(service.create(7, { ...maintenance, reason: ' ' }), /lịch bảo trì/i);
    }
});

test('create preserves API conflicts, field validation and cancellation for the form', async () => {
    const draft = { start_date: maintenance.start_date, end_date: maintenance.end_date };
    await assert.rejects(createHostRoomMaintenanceService(unusedClient).create('07', draft), (error) => error.isLocalValidation);
    for (const failure of [
        Object.assign(new Error('Conflict'), { response: { status: 409, data: { code: 'CATALOG_ROOM_BOOKING_CONFLICT' } } }),
        Object.assign(new Error('Invalid'), { response: { status: 422, data: { errors: { end_date: ['Ngày không hợp lệ.'] } } } }),
        new CanceledError(),
    ]) {
        const service = createHostRoomMaintenanceService({ post: async () => { throw failure; } });
        await assert.rejects(service.create(7, draft), (error) => error === failure);
    }
});

test('update puts only editable fields for the selected schedule and accepts its matching confirmation', async () => {
    const { signal } = new AbortController();
    const { start_date, end_date } = maintenance;
    const service = createHostRoomMaintenanceService({
        put: async (url, body, options) => {
            assert.equal(url, '/api/host/maintenances/2');
            assert.deepEqual(body, { start_date, end_date, reason: null });
            assert.deepEqual(options, { signal, timeout: 10000 });
            return { status: 200, data: { data: maintenance } };
        },
    });
    assert.deepEqual(await service.update('2', { ...maintenance, room_id: 7, reason: '' }, { signal }), maintenance);
    await assert.rejects(service.update('07', maintenance), (error) => error.isLocalValidation);
});

test('update rejects a mismatched schedule and preserves the status conflict response', async () => {
    const mismatched = createHostRoomMaintenanceService({
        put: async () => ({ status: 200, data: { data: { ...maintenance, id: 3 } } }),
    });
    await assert.rejects(mismatched.update(2, maintenance), /không khớp/);

    const failure = Object.assign(new Error('Conflict'), {
        response: { status: 409, data: { code: 'CATALOG_ROOM_MAINTENANCE_STATUS_CONFLICT' } },
    });
    const service = createHostRoomMaintenanceService({ put: async () => { throw failure; } });
    await assert.rejects(service.update(2, maintenance), (error) => error === failure);
});

test('cancel sends a scoped DELETE without a payload and confirms the same cancelled schedule', async () => {
    const { signal } = new AbortController();
    const cancelled = { ...maintenance, status: 'CANCELLED' };
    const service = createHostRoomMaintenanceService({
        delete: async (url, options) => {
            assert.equal(url, '/api/host/maintenances/2');
            assert.deepEqual(options, { signal, timeout: 10000 });
            return { status: 200, data: { data: cancelled } };
        },
    });
    assert.deepEqual(await service.cancel('2', { signal }), cancelled);
    await assert.rejects(service.cancel('02'), (error) => error.isLocalValidation);
});

test('cancel requires a matching cancelled schedule and preserves transport and conflict errors', async () => {
    for (const [result, error] of [
        [{ status: 204 }, /không nhận được xác nhận/i],
        [{ status: 200, data: { data: maintenance } }, /không khớp/],
        [{ status: 200, data: { data: { ...maintenance, id: 3, status: 'CANCELLED' } } }, /không khớp/],
    ]) {
        const service = createHostRoomMaintenanceService({ delete: async () => result });
        await assert.rejects(service.cancel(2), error);
    }

    for (const failure of [
        new CanceledError(),
        Object.assign(new Error('Conflict'), {
            response: { status: 409, data: { code: 'CATALOG_ROOM_MAINTENANCE_STATUS_CONFLICT' } },
        }),
    ]) {
        const service = createHostRoomMaintenanceService({ delete: async () => { throw failure; } });
        await assert.rejects(service.cancel(2), (error) => error === failure);
    }
});

test('complete patches the status endpoint and verifies the selected schedule and destination', async () => {
    const { signal } = new AbortController();
    const completed = { ...maintenance, status: 'COMPLETED' };
    const service = createHostRoomMaintenanceService({
        patch: async (url, body, options) => {
            assert.equal(url, '/api/host/maintenances/2/status');
            assert.deepEqual(body, { status: 'COMPLETED' });
            assert.deepEqual(options, { signal, timeout: 10000 });
            return { status: 200, data: { data: completed } };
        },
    });
    assert.deepEqual(await service.complete('2', { signal }), completed);
    await assert.rejects(service.complete('02'), (error) => error.isLocalValidation);
    const mismatched = createHostRoomMaintenanceService({ patch: async () => ({ status: 200, data: { data: maintenance } }) });
    await assert.rejects(mismatched.complete(2), /không khớp/);
});

test('start patches the status endpoint with IN_PROGRESS', async () => {
    const started = { ...maintenance, status: 'IN_PROGRESS' };
    const service = createHostRoomMaintenanceService({
        patch: async (url, body) => {
            assert.equal(url, '/api/host/maintenances/2/status');
            assert.deepEqual(body, { status: 'IN_PROGRESS' });
            return { status: 200, data: { data: started } };
        },
    });
    assert.deepEqual(await service.start(2), started);
});
