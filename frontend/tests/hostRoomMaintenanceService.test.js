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
