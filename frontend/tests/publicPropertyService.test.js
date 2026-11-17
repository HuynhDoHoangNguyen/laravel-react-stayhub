import test, { mock } from 'node:test';
import assert from 'node:assert/strict';
import axios, { AxiosError, CanceledError } from 'axios';
import { createPublicPropertyApi } from '../src/api/publicPropertyApi.js';
import { mapCatalogListResponse, mapCatalogDetailResponse, mapPropertySearchResponse } from '../src/mappers/catalogMapper.js';

// Replace only the Vite-configured transport; exercise the real production exports.
let adapter;
const sharedClient = axios.create({ adapter: (config) => adapter(config) });
mock.module(new URL('../src/api/axios.js', import.meta.url).href, { defaultExport: sharedClient });
const { propertyService, createPropertyService } = await import('../src/services/propertyService.js');
test.after(() => mock.restoreAll());

const property = {
    id: 7, name: 'Fixture', address: 'Public address', type: 'HOMESTAY', description: null,
    check_in_time: '14:00:00', check_out_time: '12:00:00', primary_image: null,
    starting_price: '0.00', rating: null, review_count: 0, currency: 'VND', price_unit: 'night',
};
const meta = { current_page: 1, last_page: 1, per_page: 12, total: 1 };
const links = { first: '?page=1', last: '?page=1', prev: null, next: null };
const page = { data: [property], meta, links };
const detail = { data: { ...property, starting_price: null, rooms: [], images: [], amenities: [] } };
const response = (config, data) => ({ status: 200, headers: {}, config, data });

test('production property exports traverse service, API and mapper without changing the UI contract', async () => {
    const calls = [];
    adapter = async (config) => {
        calls.push(config);
        return response(config, config.url.endsWith('/7') ? detail : page);
    };
    const options = { signal: new AbortController().signal };
    const result = await propertyService.getProperties({ page: 2, per_page: 6, city: 'unsupported' }, options);
    assert.equal(result.source, 'api');
    assert.equal(result.data[0].price, '0.00');
    assert.equal(result.data[0].propertyType, 'HOMESTAY');
    assert.equal(result.data[0].averageRating, null);
    assert.deepEqual(result.meta, meta);
    assert.deepEqual(result.links, links);
    assert.deepEqual(calls[0].params, { page: 2, per_page: 6 });
    assert.equal(calls[0].url, '/api/public/properties');
    await propertyService.getFeaturedProperties(options);
    assert.deepEqual(calls[1].params, { page: 1, per_page: 6 });
    const found = await propertyService.getPropertyById('7', options);
    assert.equal(found.source, 'api');
    assert.equal(found.data.price, null);
    assert.deepEqual(found.data.rooms, []);
    assert.deepEqual(found.data.photos, []);
    assert.equal(calls[2].url, '/api/public/properties/7');
    assert.ok(calls.every((config) => config.method === 'get' && config.signal === options.signal && config.timeout === 10000));
});

test('existing Search uses its own transport and preserves filters and response projection', async () => {
    const calls = [];
    const rows = [{ id: 7, name: 'Search fixture', city: 'Hue', price: '100000.00', averageRating: 4 }];
    adapter = async (config) => { calls.push(config); return response(config, { data: rows }); };
    const options = { signal: new AbortController().signal };
    const result = await propertyService.searchProperties({ city: 'Hue', minPrice: 0, maxPrice: 200000,
        minRating: 3, amenities: [1, 2], propertyTypes: ['HOMESTAY'], keyword: 'fixture', sortBy: 'RATING_DESC' }, options);
    assert.deepEqual(result, { data: rows, total: 1, source: 'api' });
    assert.equal(calls[0].url, '/api/public/properties/search');
    assert.equal(calls[0].signal, options.signal);
    assert.deepEqual(calls[0].params, { city: 'Hue', minPrice: 0, maxPrice: 200000, minRating: 3,
        amenities: '1,2', propertyTypes: 'HOMESTAY', keyword: 'fixture', sortBy: 'RATING_DESC' });
    assert.equal(calls.length, 1);
});

test('Search 404 remains unavailable and never falls back to mocks or an unfiltered catalog', async () => {
    let requests = 0;
    const client = axios.create({ adapter: async (config) => {
        ++requests;
        throw new AxiosError('Fixture missing', '', config, null, { status: 404, config, data: {}, headers: {} });
    } });
    const service = createPropertyService(createPublicPropertyApi(client), { dev: false, enableMocks: 'true', properties: [{ id: 99 }] });
    assert.deepEqual(await service.searchProperties(), { data: [], total: 0, source: 'api', unavailable: true });
    assert.equal(requests, 1);
});

test('public transport preserves HTTP, network and cancellation identity without retrying', async () => {
    for (const status of [401, 403, 404, 422, 500, 0]) {
        let requests = 0;
        const original = new AxiosError('Fixture', status ? '' : 'ERR_NETWORK');
        if (status) original.response = { status, data: { errors: { page: ['Invalid'] } } };
        const client = axios.create({ adapter: async () => { ++requests; throw original; } });
        const api = createPublicPropertyApi(client);
        for (const invoke of [() => api.getProperties({ page: 1 }), () => api.getPropertyById(7), () => api.searchProperties({ city: 'Hue' })]) {
            await assert.rejects(invoke(), (received) => received === original);
        }
        assert.equal(requests, 3);
    }
    let requests = 0;
    const api = createPublicPropertyApi(axios.create({ adapter: async () => { ++requests; throw new Error('Unexpected'); } }));
    const controller = new AbortController(); controller.abort();
    for (const invoke of [() => api.getProperties({}, { signal: controller.signal }),
        () => api.getPropertyById(7, { signal: controller.signal }), () => api.searchProperties({}, { signal: controller.signal })]) {
        await assert.rejects(invoke(), CanceledError);
    }
    assert.equal(requests, 0);
});

test('the shared DEV gate applies to list, detail and mock Search without issuing real requests', async () => {
    const rows = [
        { id: 1, name: 'Stay A', city: 'Hue', propertyType: 'HOMESTAY', price: 300000, averageRating: 5, amenityIds: [1, 2] },
        { id: 2, name: 'Stay B', city: 'Hue', propertyType: 'HOTEL', price: 100000, averageRating: 3, amenityIds: [1] },
        { id: 3, name: 'Other', city: 'Da Nang', propertyType: 'HOMESTAY', price: 200000, averageRating: 4, amenityIds: [2] },
    ];
    for (const [dev, enableMocks, mocked] of [[true, 'true', true], [false, 'true', false], [true, 'false', false], [true, true, false]]) {
        let requests = 0;
        const client = axios.create({ adapter: async (config) => {
            ++requests;
            return response(config, config.url.endsWith('/search') ? [] : config.url.endsWith('/7') ? detail : page);
        } });
        const service = createPropertyService(createPublicPropertyApi(client), { dev, enableMocks, properties: rows });
        const result = await service.searchProperties({ city: 'Hue', keyword: 'Stay', minPrice: 200000, maxPrice: 400000,
            minRating: 4, amenities: [2], propertyTypes: ['HOMESTAY'], sortBy: 'PRICE_DESC' });
        assert.equal(result.source, mocked ? 'mock' : 'api');
        if (mocked) assert.deepEqual(result.data.map((item) => item.id), [1]);
        assert.equal((await service.getProperties()).source, mocked ? 'mock' : 'api');
        assert.equal((await service.getPropertyById(7)).source, mocked ? 'mock' : 'api');
        assert.equal(requests, mocked ? 0 : 3);
    }
    assert.deepEqual(rows.map((item) => item.id), [1, 2, 3]);
});

test('mapper retains decimal/null/empty values and metadata while excluding private fields', () => {
    const body = structuredClone(page);
    body.data[0].host_id = 123;
    body.data[0].phone = 'PRIVATE';
    const original = structuredClone(body);
    const mapped = mapCatalogListResponse(body);
    assert.equal(mapped.data[0].price, '0.00');
    assert.equal(mapped.data[0].averageRating, null);
    assert.equal(mapped.data[0].host_id, undefined);
    assert.equal(mapped.data[0].phone, undefined);
    assert.deepEqual(mapped.meta, meta);
    assert.deepEqual(mapped.links, links);
    assert.deepEqual(body, original);
    assert.equal(mapCatalogDetailResponse(detail).data.price, null);
    assert.deepEqual(mapPropertySearchResponse([]), { data: [], total: 0 });
    for (const malformed of [undefined, null, {}, { data: {} }, { data: null }, '<html>error</html>']) {
        assert.throws(() => mapPropertySearchResponse(malformed));
        assert.throws(() => mapCatalogDetailResponse(malformed));
        assert.throws(() => mapCatalogListResponse(malformed));
    }
});
