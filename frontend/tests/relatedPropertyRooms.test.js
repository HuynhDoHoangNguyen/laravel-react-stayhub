import test, { mock } from 'node:test';
import assert from 'node:assert/strict';
import axios from 'axios';
import { createElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { MemoryRouter } from 'react-router-dom';
import { fileURLToPath } from 'node:url';
import { mapRelatedPropertyRooms } from '../src/mappers/relatedPropertyRoomsMapper.js';
import { createCatalogLoader } from '../src/services/catalog.js';

let adapter;
let server;
const client = axios.create({ adapter: config => adapter(config) });
mock.module(new URL('../src/api/axios.js', import.meta.url).href, { defaultExport: client });
const { propertyService } = await import('../src/services/propertyService.js');
test.after(async () => { await server?.close(); mock.restoreAll(); });
const dto = count => ({ data: { id: 7, name: 'Cơ sở thật', type: 'HOTEL', starting_price: null,
    rooms: Array.from({ length: count }, (_, index) => ({ id: index + 1, name: 'Phòng ' + (index + 1), capacity: 2,
        price_per_night: index === 0 ? null : '0.00', room_type: { name: 'Deluxe' }, images: [], amenities: [] })) } });
const reply = (config, body) => ({ config, headers: {}, status: 200, data: body });
const normalized = count => ({ source: 'api', data: { id: 7, name: 'Cơ sở thật', rooms: Array.from({ length: count }, (_, index) => ({
    id: index + 1, name: 'Phòng ' + (index + 1), capacity: 2, price: index === 0 ? null : '0.00', photos: [], amenities: [],
})) } });

test('production property service feeds related projection using the public endpoint and AbortSignal', async () => {
    const calls = [];
    adapter = async config => { calls.push(config); return reply(config, dto(7)); };
    const signal = new AbortController().signal;
    const result = mapRelatedPropertyRooms(await propertyService.getPropertyById(7, { signal }), 7, 2);
    assert.equal(calls.length, 1);
    assert.equal(calls[0].url, '/api/public/properties/7');
    assert.equal(calls[0].signal, signal);
    assert.deepEqual(result.rooms.map(room => room.id), [1, 3, 4, 5]);
    assert.equal(result.rooms[0].price, null);
    assert.equal(result.rooms[1].price, '0.00');
    assert.equal(result.propertyName, 'Cơ sở thật');
});

test('projection excludes the current room before limiting, handles small lists and copies arrays', () => {
    for (const count of [0, 1, 2, 4, 5, 7]) {
        const source = normalized(count);
        const before = structuredClone(source);
        const result = mapRelatedPropertyRooms(source, 7, 1);
        assert.equal(result.rooms.length, Math.min(4, Math.max(0, count - 1)));
        assert.ok(!result.rooms.some(room => room.id === 1));
        assert.deepEqual(source, before);
        if (result.rooms.length) assert.notEqual(result.rooms[0].photos, source.data.rooms[1].photos);
    }
    const duplicated = normalized(3);
    duplicated.data.rooms.splice(1, 0, duplicated.data.rooms[0]);
    assert.deepEqual(mapRelatedPropertyRooms(duplicated, 7, '2').rooms.map(room => room.id), [1, 3]);
});

test('wrong property, missing data and invalid IDs fail rather than suggesting unrelated rooms', () => {
    for (const result of [null, { source: 'api', data: null }, { ...normalized(1), source: 'unknown' },
        { source: 'api', data: { ...normalized(1).data, id: 8 } }]) assert.throws(() => mapRelatedPropertyRooms(result, 7, 1));
    assert.throws(() => mapRelatedPropertyRooms(normalized(1), '../7', 1));
    assert.throws(() => mapRelatedPropertyRooms(normalized(1), 7, 0));
    assert.equal(mapRelatedPropertyRooms({ ...normalized(2), source: 'mock' }, 7, 1).source, 'mock');
});

test('suggestions loader supports failure/retry and discards late responses after cancellation', async () => {
    let finish;
    let oldSignal;
    adapter = config => { oldSignal = config.signal; return new Promise(resolve => { finish = () => resolve(reply(config, dto(3))); }); };
    const loader = createCatalogLoader(async options => mapRelatedPropertyRooms(await propertyService.getPropertyById(7, options), 7, 1));
    const pending = loader.reload();
    await new Promise(resolve => setTimeout(resolve, 0));
    loader.cancel();
    finish();
    await pending;
    assert.equal(oldSignal.aborted, true);
    assert.equal(loader.getSnapshot().result, null);
    adapter = async () => { throw new Error('offline'); };
    await loader.reload();
    assert.equal(loader.getSnapshot().status, 'error');
    assert.equal(loader.getSnapshot().result, null);
    adapter = async config => reply(config, dto(3));
    await loader.reload();
    assert.equal(loader.getSnapshot().status, 'ready');
    assert.deepEqual(loader.getSnapshot().result.rooms.map(room => room.id), [2, 3]);
    loader.cancel();
});

async function render(state, search = '', currentRoomId = 2) {
    if (!server) {
        const { createServer } = await import('vite');
        server = await createServer({ root: fileURLToPath(new URL('..', import.meta.url)), logLevel: 'error',
            server: { middlewareMode: true, hmr: false, watch: null } });
    }
    const { RelatedRoomsContent } = await server.ssrLoadModule('/src/components/hotel/RelatedPropertyRooms.jsx');
    return renderToStaticMarkup(createElement(MemoryRouter, null, createElement(RelatedRoomsContent, {
        propertyId: 7, currentRoomId, state, search, navigationState: { catalogPage: '?page=2' }, onRetry() {},
    })));
}

test('related card links preserve dates, guests and filters and update old room selection for each target', async () => {
    const result = mapRelatedPropertyRooms(normalized(3), 7, 2);
    const query = '?startDate=2027-01-01&endDate=2027-01-03&adults=2&children=1&roomId=2&min_price=0.00';
    const html = await render({ status: 'ready', result }, query);
    const links = [...html.matchAll(/href="([^"]+)"/g)].map(match => new URL(match[1].replaceAll('&amp;', '&'), 'https://example.test'));
    assert.deepEqual(links.map(link => link.pathname), ['/rooms/1', '/rooms/3', '/properties/7']);
    assert.deepEqual(links.map(link => link.searchParams.get('roomId')), ['1', '3', '2']);
    for (const link of links) for (const [key, value] of new URLSearchParams(query)) {
        if (key !== 'roomId') assert.equal(link.searchParams.get(key), value);
    }
    assert.ok(html.includes('Chưa có ảnh'));
    assert.ok(html.includes('Chưa có giá tham khảo'));
    assert.match(html, /0\s*₫/u);
    assert.ok(!html.includes('/customer/checkout'));
    assert.equal((html.match(/<article/g) ?? []).length, 2);
    assert.ok(html.includes('Xem tất cả phòng tại Cơ sở thật'));
    const noQuery = await render({ status: 'ready', result });
    assert.ok(noQuery.includes('href="/rooms/1"'));
});

test('empty, loading and error remain local to suggestions; mock content is labeled', async () => {
    const loading = await render({ status: 'loading', result: null });
    assert.ok(loading.includes('role="status"'));
    assert.ok(!loading.includes('<article'));
    const error = await render({ status: 'error', result: null, error: new Error('offline') });
    assert.ok(error.includes('role="alert"'));
    assert.ok(error.includes('Thử lại danh sách phòng'));
    assert.ok(error.includes('Bạn vẫn có thể xem và đặt phòng hiện tại'));
    const empty = await render({ status: 'ready', result: { propertyName: 'Cơ sở thật', source: 'api', rooms: [] } });
    assert.ok(empty.includes('Cơ sở chưa có phòng khác'));
    assert.ok(empty.includes('/properties/7'));
    const mockHtml = await render({ status: 'ready', result: { ...mapRelatedPropertyRooms(normalized(3), 7, 2), source: 'mock' } });
    assert.ok(mockHtml.includes('Dữ liệu giả lập DEV'));
    assert.ok(!mockHtml.includes('/customer/checkout'));
});
