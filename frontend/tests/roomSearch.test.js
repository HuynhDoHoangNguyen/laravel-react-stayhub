import test, { mock } from 'node:test';
import assert from 'node:assert/strict';
import axios, { AxiosError, CanceledError } from 'axios';
import { createPublicRoomApi } from '../src/api/publicRoomApi.js';
import { mapRoomSearchPage, mapRoomFilterOptions } from '../src/mappers/roomSearchMapper.js';

let adapter;
const client = axios.create({ adapter: config => adapter(config) });
mock.module(new URL('../src/api/axios.js', import.meta.url).href, { defaultExport: client });
const { roomSearchService, createRoomSearchService, readRoomSearch, updateRoomSearch, createRoomSearchLoader } = await import('../src/services/roomSearchService.js');
test.after(() => mock.restoreAll());

const row = { id: 3, name: 'Fixture room', capacity: 3, price_per_night: '100.25', currency: 'VND', price_unit: 'night',
    property: { id: 2, name: 'Fixture property', address: null, rating: 4.5, review_count: 2 }, room_type: { id: 1, name: 'Suite' }, images: [], amenities: [{ id: 1, name: 'Wifi' }] };
const page = { data: [row], meta: { current_page: 1, last_page: 1, per_page: 12, total: 1, from: 1, to: 1 },
    links: { first: '?page=1', last: '?page=1', next: null, prev: null } };
const options = { data: { room_types: [{ id: 1, name: 'Suite', property: { id: 2, name: 'Fixture property' } }], amenities: [{ id: 1, name: 'Wifi' }] } };
const response = (data, config) => ({ data, status: 200, headers: {}, config });

test('real public export calls the shared transport with decimal strings, arrays, defaults on server and cancellation', async () => {
    const calls = [];
    adapter = async config => { calls.push(config); return response(page, config); };
    const signal = new AbortController().signal;
    const result = await roomSearchService.list('min_price=0.00&max_price=100.25&guest_count=3&room_type_ids[]=1&room_type_ids[]=2&amenity_ids[]=1&sort=price_desc&page=2&per_page=1', { signal });
    assert.equal(result.data[0].price_per_night, '100.25');
    assert.equal(result.data[0].property.address, '');
    assert.equal(calls[0].url, '/api/public/rooms');
    assert.equal(calls[0].method, 'get');
    assert.deepEqual(calls[0].params, { min_price: '0.00', max_price: '100.25', guest_count: '3', sort: 'price_desc', page: '2', per_page: '1', room_type_ids: ['1', '2'], amenity_ids: ['1'] });
    assert.equal(calls[0].signal, signal);
    const uri = client.getUri(calls[0]);
    assert.match(decodeURIComponent(uri), /room_type_ids\[\]=1&room_type_ids\[\]=2/);
    await roomSearchService.list('');
    assert.deepEqual(calls[1].params, {});
});

test('options use a separate GET, preserve room-type property labels and contain no invented choices', async () => {
    const signal = new AbortController().signal;
    adapter = async config => {
        assert.equal(config.url, '/api/public/rooms/filter-options');
        assert.equal(config.signal, signal);
        assert.equal(config.params, undefined);
        return response(options, config);
    };
    assert.deepEqual(await roomSearchService.filterOptions({ signal }), options.data);
    assert.deepEqual(mapRoomFilterOptions({ data: { room_types: [], amenities: [] } }), { room_types: [], amenities: [] });
});

test('URL state retains selected filters, invalid raw input and duplicate scalar errors', () => {
    const search = 'min_price=0&max_price=&guest_count=01&room_type_ids[]=2&amenity_ids[]=1&amenity_ids[]=3&page=0';
    assert.deepEqual(readRoomSearch(search).params, { min_price: '0', max_price: '', guest_count: '01', page: '0', room_type_ids: ['2'], amenity_ids: ['1', '3'] });
    assert.deepEqual(readRoomSearch('page=1&page=2').errors, { page: ['Bộ lọc này chỉ nhận một giá trị.'] });
});

test('applying filters and sort restarts pagination; paging preserves decimal and option criteria', () => {
    const search = 'min_price=0.00&room_type_ids[]=2&amenity_ids[]=1&per_page=2&page=4';
    const changed = updateRoomSearch(search, { max_price: '100.10', guest_count: '3', room_type_ids: ['4', '5'] });
    assert.deepEqual(readRoomSearch(changed).params, { min_price: '0.00', max_price: '100.10', guest_count: '3', per_page: '2', room_type_ids: ['4', '5'], amenity_ids: ['1'] });
    assert.equal(readRoomSearch(updateRoomSearch(changed, { page: 2 })).params.page, '2');
    assert.equal(readRoomSearch(updateRoomSearch(search, { sort: 'price_desc' })).params.page, undefined);
    assert.equal(readRoomSearch(search).params.page, '4'); // browser Back restores original URL
    const reset = updateRoomSearch(search, { min_price: null, room_type_ids: [], amenity_ids: [], per_page: null });
    assert.deepEqual(readRoomSearch(reset).params, {});
});

test('unsupported dates/location/rating cannot be dropped silently; explicit transition retains supported criteria', async () => {
    let calls = 0;
    const service = createRoomSearchService({ list: async () => { ++calls; } });
    const search = 'startDate=2026-12-01&endDate=2026-12-02&city=Hue&minRating=4&min_price=0&page=3';
    await assert.rejects(service.list(search), error => error.response.status === 422 && !!error.response.data.errors.startDate);
    assert.equal(calls, 0);
    const retained = updateRoomSearch(search, { min_price: '5' });
    assert.equal(new URLSearchParams(retained).get('city'), 'Hue');
    const explicit = updateRoomSearch(search, {}, { clearUnsupported: true });
    assert.deepEqual(readRoomSearch(explicit), { params: { min_price: '0' }, unsupported: [], errors: {} });
    await assert.rejects(service.list('sort=price_asc&sort=price_desc'), error => !!error.response.data.errors.sort);
    assert.equal(calls, 0);
});

test('valid empty page and zero price are preserved, not replaced with mock rooms', () => {
    const empty = { ...page, data: [], meta: { ...page.meta, total: 0, from: null, to: null } };
    assert.deepEqual(mapRoomSearchPage(empty).data, []);
    const zero = mapRoomSearchPage({ ...page, data: [{ ...row, price_per_night: '0.00' }] });
    assert.equal(zero.data[0].price_per_night, '0.00');
});

test('malformed successes fail visibly instead of becoming fake empty or mock results', async () => {
    const badPages = [{}, { data: [] }, { ...page, meta: { ...page.meta, total: '1' } },
        { ...page, data: [{ ...row, price_per_night: 100.25 }] }, { ...page, data: [{ ...row, capacity: 0 }] },
        { ...page, data: [{ ...row, property: null }] }, { ...page, data: [{ ...row, currency: 'USD' }] }];
    for (const body of badPages) {
        adapter = async config => response(body, config);
        await assert.rejects(roomSearchService.list(''), /Dữ liệu phòng/);
    }
    for (const body of [{}, { data: { room_types: [], amenities: null } }, { data: { room_types: [{ id: 1, name: 'Suite' }], amenities: [] } }]) {
        assert.throws(() => mapRoomFilterOptions(body), /Dữ liệu phòng/);
    }
});

test('HTTP, timeout and network failures keep identity and field errors without implicit retry', async () => {
    for (const status of [401, 403, 422, 500, 0]) {
        let calls = 0;
        const error = new AxiosError('Fixture', status ? undefined : 'ECONNABORTED', undefined, undefined,
            status ? { status, data: { errors: { max_price: ['Invalid range'] } } } : undefined);
        adapter = async () => { ++calls; throw error; };
        await assert.rejects(roomSearchService.list(''), actual => actual === error);
        assert.equal(calls, 1);
    }
});

test('an already aborted signal never reaches transport', async () => {
    let calls = 0;
    adapter = async () => { ++calls; throw new Error('Must not call'); };
    const controller = new AbortController(); controller.abort();
    await assert.rejects(roomSearchService.list('', { signal: controller.signal }), CanceledError);
    assert.equal(calls, 0);
});

const deferred = () => { let resolve, reject; const promise = new Promise((ok, fail) => { resolve = ok; reject = fail; }); return { promise, resolve, reject }; };

test('late success and late errors cannot replace the newest results, including transports ignoring abort', async () => {
    const loader = createRoomSearchLoader();
    const older = deferred();
    let oldSignal;
    const first = loader.load(signal => { oldSignal = signal; return older.promise; });
    const latest = { data: [], meta: { total: 0 } };
    await loader.load(async () => latest);
    assert.equal(oldSignal.aborted, true);
    older.resolve(page); await first;
    assert.equal(loader.getSnapshot().value, latest);
    const failed = deferred();
    const staleFailure = loader.load(() => failed.promise);
    await loader.load(async () => page);
    failed.reject(new Error('Late failure')); await staleFailure;
    assert.equal(loader.getSnapshot().value, page);
    assert.equal(loader.getSnapshot().error, null);
});

test('request errors are recoverable; replacing a result clears old totals during loading; disposal aborts', async () => {
    const loader = createRoomSearchLoader();
    let updates = 0;
    const unsubscribe = loader.subscribe(() => ++updates);
    const error = new Error('Offline');
    await loader.load(async () => { throw error; });
    assert.equal(loader.getSnapshot().error, error);
    await loader.load(async () => page);
    assert.equal(loader.getSnapshot().error, null);
    const pending = deferred();
    let signal;
    const request = loader.load(active => { signal = active; return pending.promise; });
    assert.deepEqual(loader.getSnapshot(), { loading: true, value: null, error: null });
    loader.cancel(); unsubscribe();
    const count = updates;
    pending.resolve(page); await request;
    assert.equal(signal.aborted, true);
    assert.equal(updates, count);
    assert.equal(loader.getSnapshot().value, null);
});

test('factory can use an independent real API adapter without changing shared configuration', async () => {
    const independent = axios.create({ adapter: async config => response(page, config) });
    const service = createRoomSearchService(createPublicRoomApi(independent));
    assert.equal((await service.list('guest_count=2')).meta.total, 1);
});

test('rating persists in URL, resets paging, travels to API and preserves actual review average/count', async () => {
    const next = updateRoomSearch('min_price=0&page=3', { min_rating: '4.5' });
    assert.deepEqual(readRoomSearch(next).params, { min_price: '0', min_rating: '4.5' });
    adapter = async config => {
        assert.equal(config.params.min_rating, '4.5');
        return response(page, config);
    };
    const result = await roomSearchService.list(next);
    assert.equal(result.data[0].property.rating, 4.5);
    assert.equal(result.data[0].property.review_count, 2);
    assert.equal(readRoomSearch(updateRoomSearch(next, { min_rating: '' })).params.min_rating, undefined);
    const unrated = mapRoomSearchPage({ ...page, data: [{ ...row, property: { ...row.property, rating: null, review_count: 0 } }] });
    assert.equal(unrated.data[0].property.rating, null);
    for (const property of [{ ...row.property, rating: 6 }, { ...row.property, rating: null },
        { ...row.property, review_count: -1 }, { ...row.property, review_count: 0 }]) {
        assert.throws(() => mapRoomSearchPage({ ...page, data: [{ ...row, property }] }), /Dữ liệu phòng/);
    }
});
