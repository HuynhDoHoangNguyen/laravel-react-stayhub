import test from 'node:test';
import assert from 'node:assert/strict';
import axios, { AxiosError } from 'axios';
import { adaptCatalogProperty, createCatalogService, createCatalogLoader, formatCatalogPrice, catalogParams, catalogBackLink } from '../src/services/catalog.js';
import { getAuthDestination } from '../src/utils/authNavigation.js';

const dto = { id: 1, name: 'Fixture', address: 'Public address', type: 'HOTEL', description: '<script>fixture</script>',
    primary_image: 'https://example.com/primary.jpg', starting_price: '0.00', rating: null, review_count: 0, currency: 'VND', price_unit: 'night' };
function clientFixture({ body, status = 200, network = false } = {}) {
    const calls = [];
    const client = axios.create({ adapter: async (config) => {
        calls.push(config);
        if (network) throw new AxiosError('private network details', 'ECONNABORTED', config);
        const data = body || (config.url === '/api/public/properties'
            ? { data: [dto], meta: { current_page: 2, last_page: 4, per_page: 6, total: 20 }, links: { next: 'fixture' } }
            : { data: dto });
        const response = { config, headers: {}, status, data };
        if (status >= 400) throw new AxiosError('private server details', '', config, null, response);
        return response;
    } });
    return { client, calls };
}

test('catalog adapter maps the real DTO without inventing a city, contact, rating or area', () => {
    const view = adaptCatalogProperty({ ...dto, rooms: [{ id: 2, room_number: '101', name: 'Room', capacity: 2,
        price_per_night: '500000.25', room_type: { id: 3, name: 'Double' }, images: ['https://example.com/room.jpg'], amenities: [{ id: 4, name: 'Wifi' }] }],
    amenities: [{ id: 4, name: 'Wifi' }], images: ['https://example.com/gallery.jpg'] });
    assert.equal(view.price, '0.00'); assert.equal(view.averageRating, null);
    assert.equal(view.city, undefined); assert.equal(view.contactInfo, undefined); assert.equal(view.rooms[0].area, undefined);
    assert.equal(view.rooms[0].roomTypeName, 'Double'); assert.equal(view.rooms[0].price, '500000.25');
    assert.deepEqual(view.rooms[0].amenities, ['Wifi']); assert.deepEqual(view.photos, ['https://example.com/gallery.jpg']);
    assert.equal(view.description, '<script>fixture</script>');
});

test('zero and missing prices remain distinct and render VND without invented defaults', () => {
    assert.match(formatCatalogPrice('0.00'), /0/);
    for (const price of [null, undefined, '', ' ', true, false, [], {}, 'invalid']) assert.equal(formatCatalogPrice(price), 'Chưa có giá tham khảo');
    assert.notEqual(formatCatalogPrice('500000.25'), formatCatalogPrice(null));
});

test('list service preserves server metadata and sends only pagination fields', async () => {
    const { client, calls } = clientFixture(); const service = createCatalogService(client);
    const result = await service.getProperties({ page: 2, per_page: 6, startDate: 'not-supported' });
    assert.equal(result.source, 'api'); assert.equal(result.meta.total, 20); assert.equal(result.meta.last_page, 4);
    assert.deepEqual(calls[0].params, { page: 2, per_page: 6 }); assert.equal(result.links.next, 'fixture');
    await service.getFeaturedProperties(); assert.deepEqual(calls[1].params, { page: 1, per_page: 6 });
});

test('a list error never becomes an empty success or falls back to production mocks', async () => {
    for (const status of [404, 422, 500]) {
        const { client, calls } = clientFixture({ status, body: { message: 'private SQL stack', errors: { page: ['Invalid page'] } } });
        const service = createCatalogService(client, { dev: false, enableMocks: 'true', properties: [{ id: 900 }] });
        await assert.rejects(service.getProperties(), (error) => {
            assert.equal(error.status, status); assert.equal(error.message.includes('SQL'), false); assert.equal(error.config, undefined);
            if (status === 422) assert.deepEqual(error.fields, { page: ['Invalid page'] });
            return true;
        });
        assert.equal(calls.length, 1);
    }
});

test('mocks require both the DEV flag and explicit string opt-in and use mock-only pagination', async () => {
    for (const [dev, enableMocks, mocked] of [[true, 'true', true], [false, 'true', false], [true, 'false', false], [true, true, false]]) {
        const { client, calls } = clientFixture();
        const service = createCatalogService(client, { dev, enableMocks, properties: [{ id: 1 }, { id: 2 }, { id: 3 }] });
        const result = await service.getProperties({ page: 2, per_page: 1 });
        assert.equal(result.source, mocked ? 'mock' : 'api'); assert.equal(calls.length, mocked ? 0 : 1);
        if (mocked) { assert.deepEqual(result.data, [{ id: 2 }]); assert.equal(result.meta.total, 3); }
    }
});

test('detail maps Resource wrapper, returns 404 as missing and keeps network errors distinct', async () => {
    const { client, calls } = clientFixture(); const service = createCatalogService(client);
    assert.equal((await service.getPropertyById(1)).data.name, 'Fixture');
    assert.equal(calls[0].url, '/api/public/properties/1');
    const missing = clientFixture({ status: 404 });
    assert.deepEqual(await createCatalogService(missing.client).getPropertyById(1), { data: null, source: 'api', unavailable: true });
    const failed = clientFixture({ network: true }); await assert.rejects(createCatalogService(failed.client).getPropertyById(1));
    for (const id of ['../me', 'abc', '01', '-1']) assert.equal((await service.getPropertyById(id)).data, null);
    assert.equal(calls.length, 1);
});

test('a mock detail retains mock identity so public review calls cannot be enabled by the adapter', async () => {
    const { client, calls } = clientFixture();
    const service = createCatalogService(client, { dev: true, enableMocks: 'true', properties: [{ id: 1, name: 'DEV fixture' }] });
    assert.equal((await service.getPropertyById(1)).source, 'mock'); assert.equal(calls.length, 0);
    assert.equal((await service.getPropertyById(99)).data, null);
});

test('URL pagination and back navigation reject unsupported filters, invalid values and external locations', () => {
    assert.deepEqual(catalogParams('page=2&per_page=24'), { page: 2, per_page: 24 });
    for (const query of ['page=0', 'page=1.5', 'page=01', 'per_page=25', 'per_page=', 'page=2&page=3', 'startDate=2026-12-01', 'page=2147483648']) assert.equal(catalogParams(query), null);
    assert.equal(catalogBackLink('?page=2&per_page=6'), '/properties?page=2&per_page=6');
    for (const location of ['https://evil.test', '//evil.test', '?redirect=https://evil.test', '/customer']) assert.equal(catalogBackLink(location), '/properties');
    assert.equal(getAuthDestination('CUSTOMER', '/properties?page=2'), '/properties?page=2');
    assert.equal(getAuthDestination('HOST', '/properties'), '/properties');
});

function deferred() { let resolve, reject; const promise = new Promise((done, fail) => { resolve = done; reject = fail; }); return { promise, resolve, reject }; }

test('switching pages or properties never lets older responses overwrite the newer result', async () => {
    const tasks = [deferred(), deferred()]; let index = 0;
    const loader = createCatalogLoader(() => tasks[index++].promise);
    const first = loader.reload(); const second = loader.reload();
    tasks[1].resolve({ data: [{ id: 2 }], source: 'api' }); await second;
    tasks[0].resolve({ data: [{ id: 1 }], source: 'api' }); await first;
    assert.equal(loader.getSnapshot().result.data[0].id, 2);
});

test('unmount and late errors cannot restore old detail or leak an old error; retry clears stale data', async () => {
    const tasks = [deferred(), deferred(), deferred()]; let index = 0;
    const loader = createCatalogLoader(() => tasks[index++].promise);
    const first = loader.reload(); loader.cancel(); tasks[0].resolve({ data: { id: 1 } }); await first;
    assert.equal(loader.getSnapshot().result, null);
    const failed = loader.reload(); tasks[1].reject(new Error('private failure')); await failed;
    assert.equal(loader.getSnapshot().status, 'error'); assert.equal(loader.getSnapshot().error.message.includes('private'), false);
    const retried = loader.reload(); assert.equal(loader.getSnapshot().result, null); assert.equal(loader.getSnapshot().status, 'loading');
    tasks[2].resolve({ data: null, source: 'api' }); await retried; assert.equal(loader.getSnapshot().status, 'ready');
});

test('malformed resource responses are errors rather than arbitrary data rendered as a catalog', async () => {
    const { client } = clientFixture({ body: { data: { id: 1 } } }); await assert.rejects(createCatalogService(client).getProperties());
    const malformedMeta = clientFixture({ body: { data: [], meta: { current_page: 1, last_page: 0, total: -1, per_page: 200 } } });
    await assert.rejects(createCatalogService(malformedMeta.client).getProperties());
});
