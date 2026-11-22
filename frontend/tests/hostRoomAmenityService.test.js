import test, { mock } from 'node:test';
import assert from 'node:assert/strict';
import { CanceledError } from 'axios';

const unusedClient = { get: () => assert.fail('Unexpected singleton request') };
mock.module(new URL('../src/api/axios.js', import.meta.url).href, { defaultExport: unusedClient });
const { createHostRoomAmenityService } = await import('../src/services/hostRoomAmenityService.js');
test.after(() => mock.restoreAll());

const legacy = { id: 1, name: 'Máy giặt', status: false };
const wifi = { id: 3, name: 'Wi-Fi', status: true };
const response = (assigned = [legacy], available = [wifi]) => ({
    status: 200, data: { success: true, message: 'OK', data: { assigned, available } },
});

test('list uses the scoped GET and preserves active and inactive rows in their respective lists', async () => {
    const { signal } = new AbortController();
    const input = response([{ ...legacy, description: 'Private field' }]);
    const service = createHostRoomAmenityService({
        get: async (url, options) => {
            assert.equal(url, '/api/host/rooms/7/amenities');
            assert.deepEqual(options, { signal, timeout: 10000 });
            return input;
        },
    });
    assert.deepEqual(await service.list('7', { signal }), { assigned: [legacy], available: [wifi] });
    assert.equal(input.data.data.assigned[0].description, 'Private field');
});

test('list accepts empty results and rejects duplicate, overlapping or inactive available entries', async () => {
    const empty = createHostRoomAmenityService({ get: async () => response([], []) });
    assert.deepEqual(await empty.list(7), { assigned: [], available: [] });

    for (const input of [response([legacy, legacy]), response([wifi], [wifi]), response([], [legacy]), response([{ ...legacy, id: '1' }])]) {
        const service = createHostRoomAmenityService({ get: async () => input });
        await assert.rejects(service.list(7), /không hợp lệ/);
    }
});

test('assignment initializes CSRF then sends one PUT without a payload and returns the authoritative lists', async () => {
    const { signal } = new AbortController();
    const calls = [];
    const service = createHostRoomAmenityService({
        get: async (...args) => { calls.push(['get', ...args]); return { status: 204 }; },
        put: async (...args) => { calls.push(['put', ...args]); return response([legacy, wifi], []); },
    });
    assert.deepEqual(await service.assign(7, '3', { signal }), { assigned: [legacy, wifi], available: [] });
    const options = { signal, timeout: 10000 };
    assert.deepEqual(calls, [
        ['get', '/sanctum/csrf-cookie', options],
        ['put', '/api/host/rooms/7/amenities/3', undefined, options],
    ]);
});

test('invalid IDs and failed CSRF never reach the PUT', async () => {
    const failedCsrf = Object.assign(new Error('CSRF'), { response: { status: 419 } });
    let csrfCalls = 0;
    const service = createHostRoomAmenityService({
        get: async () => { csrfCalls++; throw failedCsrf; },
        put: () => assert.fail('PUT after invalid IDs or failed CSRF'),
    });
    await assert.rejects(service.assign('07', 3), (error) => error.isLocalValidation);
    await assert.rejects(service.assign(7, '3.0'), (error) => error.isLocalValidation);
    assert.equal(csrfCalls, 0);
    await assert.rejects(service.assign(7, 3), (error) => error === failedCsrf && !error.outcomeUnknown);
    assert.equal(csrfCalls, 1);
});

test('known errors reach the UI and ambiguous failures require a read before another attempt', async () => {
    for (const status of [422, 503, undefined]) {
        const error = status === undefined ? new CanceledError() : Object.assign(new Error('PUT'), { response: { status } });
        let putCalls = 0;
        const service = createHostRoomAmenityService({
            get: async () => ({ status: 204 }),
            put: async () => { putCalls++; throw error; },
        });
        await assert.rejects(service.assign(7, 3), (failure) => failure === error && failure.outcomeUnknown === (status !== 422));
        assert.equal(putCalls, 1);
    }
    const wrongAssignment = createHostRoomAmenityService({
        get: async () => ({ status: 204 }),
        put: async () => response(),
    });
    await assert.rejects(wrongAssignment.assign(7, 3), (error) => error.outcomeUnknown === true);
});

test('removal initializes CSRF then sends a single DELETE for the target pair and returns the updated lists', async () => {
    const { signal } = new AbortController();
    const calls = [];
    const service = createHostRoomAmenityService({
        get: async (...args) => { calls.push(['get', ...args]); return { status: 204 }; },
        delete: async (...args) => { calls.push(['delete', ...args]); return response([legacy], [wifi]); },
    });

    assert.deepEqual(await service.remove('7', 3, { signal }), { assigned: [legacy], available: [wifi] });
    const options = { signal, timeout: 10000 };
    assert.deepEqual(calls, [
        ['get', '/sanctum/csrf-cookie', options],
        ['delete', '/api/host/rooms/7/amenities/3', options],
    ]);
});

test('failed CSRF and invalid IDs never send DELETE', async () => {
    const csrf = Object.assign(new Error('CSRF'), { response: { status: 419 } });
    let gets = 0;
    const service = createHostRoomAmenityService({
        get: async () => { gets++; throw csrf; },
        delete: () => assert.fail('Unexpected DELETE'),
    });

    await assert.rejects(service.remove(7, '03'), (error) => error.isLocalValidation);
    assert.equal(gets, 0);
    await assert.rejects(service.remove(7, 3), (error) => error === csrf && !error.outcomeUnknown);
    assert.equal(gets, 1);
});

test('DELETE errors preserve HTTP identity and uncertain or inconsistent outcomes are marked for reconciliation', async () => {
    for (const status of [403, 404, 503, undefined]) {
        const error = Object.assign(new Error('DELETE'), status === undefined ? {} : { response: { status } });
        let deletes = 0;
        const service = createHostRoomAmenityService({
            get: async () => ({ status: 204 }),
            delete: async () => { deletes++; throw error; },
        });
        await assert.rejects(service.remove(7, 3), (failure) => failure === error && failure.outcomeUnknown === (!status || status >= 500));
        assert.equal(deletes, 1);
    }

    const missingRemoval = createHostRoomAmenityService({
        get: async () => ({ status: 204 }),
        delete: async () => response([legacy], [wifi]),
    });
    await assert.rejects(missingRemoval.remove(7, 1), (error) => error.outcomeUnknown === true);
});
