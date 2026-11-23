import test, { mock } from 'node:test';
import assert from 'node:assert/strict';
import axios, { AxiosError } from 'axios';

let adapter;
const client = axios.create({ adapter: (config) => adapter(config) });
mock.module(new URL('../src/api/axios.js', import.meta.url).href, { defaultExport: client });
const { hostPropertyVerificationService: service } = await import('../src/services/hostPropertyVerificationService.js');
test.after(() => mock.restoreAll());

const entry = {
    id: 7, status: 'SUBMITTED', submitted_at: '2026-11-23T05:00:00Z', address_snapshot: '12 Bạch Đằng',
    reviewed_at: null, rejection_reason: null,
};
const url = '/api/host/properties/1/verification';
const document = new File(['test'], 'sample.pdf', { type: 'application/pdf' });

test('current handles an empty result and projects an existing record', async () => {
    const { signal } = new AbortController();
    let data = null;
    adapter = async (config) => {
        assert.equal(config.url, url);
        assert.equal(config.method, 'get');
        assert.equal(config.signal, signal);
        return { data: { data } };
    };
    assert.equal(await service.current('1', { signal }), null);
    data = { ...entry, document_path: 'private/file.pdf' };
    assert.deepEqual(await service.current(1, { signal }), entry);
});

test('submit initializes CSRF and sends multipart with the same abort signal', async () => {
    const requests = [];
    const { signal } = new AbortController();
    adapter = async (config) => {
        requests.push([config.method, config.url]);
        assert.equal(config.signal, signal);
        if (config.method === 'post') {
            assert.ok(config.data instanceof FormData);
            assert.equal(config.data.get('document').name, 'sample.pdf');
        }
        return { data: { data: entry } };
    };
    assert.deepEqual(await service.submit(1, document, { signal }), entry);
    assert.deepEqual(requests, [['get', '/sanctum/csrf-cookie'], ['post', url]]);
});

test('malformed successes are errors and submit requires an actual recorded entry', async () => {
    for (const body of [{}, { data: { ...entry, status: 'ACTIVE' } }, { data: { ...entry, submitted_at: 'bad' } }]) {
        adapter = async () => ({ data: body });
        await assert.rejects(service.current(1), { kind: 'contract' });
    }
    adapter = async () => ({ data: { data: null } });
    await assert.rejects(service.submit(1, document), { kind: 'contract' });
});

test('ownership and duplicate response errors retain their HTTP status', async () => {
    adapter = async () => { throw new AxiosError('missing', undefined, undefined, undefined, { status: 404 }); };
    await assert.rejects(service.current(1), (error) => error.response.status === 404);
    adapter = async ({ method }) => {
        if (method === 'post') throw new AxiosError('conflict', undefined, undefined, undefined, { status: 409 });
        return { data: {} };
    };
    await assert.rejects(service.submit(1, document), (error) => error.response.status === 409);
});

test('current exposes reviewed outcomes and only a rejected entry carries a reason', async () => {
    const approved = { ...entry, status: 'APPROVED', reviewed_at: '2026-11-23T06:00:00Z' };
    const rejected = { ...approved, status: 'REJECTED', rejection_reason: 'Thiếu địa chỉ.' };
    for (const result of [approved, rejected, { ...approved, status: 'INVALIDATED' }]) {
        adapter = async () => ({ data: { data: result } });
        assert.deepEqual(await service.current(1), result);
    }
    adapter = async () => ({ data: { data: { ...rejected, rejection_reason: null } } });
    await assert.rejects(service.current(1), { kind: 'contract' });
});
