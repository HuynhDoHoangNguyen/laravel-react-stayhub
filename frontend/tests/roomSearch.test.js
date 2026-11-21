import test, { mock } from 'node:test';
import assert from 'node:assert/strict';
import axios, { AxiosError, CanceledError } from 'axios';
import { createElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { MemoryRouter } from 'react-router-dom';
import { fileURLToPath } from 'node:url';
import { createPublicRoomApi } from '../src/api/publicRoomApi.js';
import { mapRoomSearchPage, mapRoomFilterOptions } from '../src/mappers/roomSearchMapper.js';

let adapter;
let uiServer;
const client = axios.create({ adapter: config => adapter(config) });
mock.module(new URL('../src/api/axios.js', import.meta.url).href, { defaultExport: client });
const { roomSearchService, createRoomSearchService, readRoomSearch, updateRoomSearch, createRoomSearchLoader } = await import('../src/services/roomSearchService.js');
test.after(async () => { await uiServer?.close(); mock.restoreAll(); });

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

test('quick refinement changes preserve the other room criteria and send decimal input unchanged', async () => {
    const original = 'guest_count=3&room_type_ids[]=1&amenity_ids[]=7&min_rating=4&page=5&per_page=2';
    const priced = updateRoomSearch(original, { min_price: '0.00', max_price: '1000000.25' });
    const sorted = updateRoomSearch(priced, { sort: 'price_desc' });
    adapter = async config => {
        assert.deepEqual(config.params, { min_price: '0.00', max_price: '1000000.25', guest_count: '3', min_rating: '4',
            sort: 'price_desc', per_page: '2', room_type_ids: ['1'], amenity_ids: ['7'] });
        return response(page, config);
    };
    assert.equal((await roomSearchService.list(sorted)).meta.total, 1);
    assert.equal(readRoomSearch(updateRoomSearch(sorted, { page: 2 })).params.page, '2');
    assert.equal(readRoomSearch(updateRoomSearch(sorted, { min_rating: '4.5' })).params.page, undefined);
    assert.equal(readRoomSearch(original).params.page, '5');
});

test('quick price presets remove only the lower price bound and keep room type, amenities and rating', () => {
    const next = readRoomSearch(updateRoomSearch('min_price=500000&min_rating=4&room_type_ids[]=3&amenity_ids[]=8&page=2',
        { min_price: null, max_price: '1000000' }));
    assert.deepEqual(next.params, { max_price: '1000000', min_rating: '4', room_type_ids: ['3'], amenity_ids: ['8'] });
});

test('refinement field errors survive the real API and loader for both input panels', async () => {
    const errors = { min_price: ['Giá tối thiểu không hợp lệ.'], max_price: ['Giá tối đa không hợp lệ.'] };
    const failure = new AxiosError('Validation', 'ERR_BAD_REQUEST', undefined, undefined, { status: 422, data: { errors } });
    adapter = async () => { throw failure; };
    const loader = createRoomSearchLoader();
    await loader.load(signal => roomSearchService.list('min_price=1.001&max_price=bad', { signal }));
    assert.equal(loader.getSnapshot().error, failure);
    assert.deepEqual(loader.getSnapshot().error.response.data.errors, errors);
    assert.equal(loader.getSnapshot().value, null);
    loader.cancel();
});

// Compile production JSX with the project's Vite config; these are SSR checks, not browser interaction tests.
async function renderSearch(component, props) {
    if (!uiServer) {
        const { createServer } = await import('vite');
        uiServer = await createServer({ root: fileURLToPath(new URL('..', import.meta.url)), logLevel: 'error',
            server: { middlewareMode: true, hmr: false, watch: null } });
    }
    const module = await uiServer.ssrLoadModule('/src/components/search/' + component + '.jsx');
    return renderToStaticMarkup(createElement(MemoryRouter, null, createElement(module[component], props)));
}

test('restored room card renders real room facts, property rating and a safe image placeholder', async () => {
    const room = mapRoomSearchPage(page).data[0];
    const markup = await renderSearch('SearchRoomCard', { room });
    for (const text of ['Fixture room', 'Fixture property', 'Suite', 'Wifi', '2 đánh giá cơ sở', 'khách / phòng', 'Chưa có ảnh']) {
        assert.ok(markup.includes(text), text);
    }
    assert.match(markup, /href="\/properties\/2"/);
    assert.ok(markup.includes('100,25'));
    assert.ok(!markup.includes('unsplash.com'));
    assert.ok(!markup.includes('Lưu yêu thích'));
    const photos = await renderSearch('SearchRoomCard', { room: { ...room,
        images: ['https://example.test/first.jpg', 'https://example.test/second.jpg'] } });
    assert.equal((photos.match(/<img /g) ?? []).length, 2);
    assert.ok(photos.includes('https://example.test/first.jpg'));
    assert.ok(photos.includes('https://example.test/second.jpg'));
    assert.match(photos, /aria-label="Xem ảnh 1 của Fixture room" aria-pressed="true"/);
    assert.match(photos, /aria-label="Xem ảnh 2 của Fixture room" aria-pressed="false"/);
    const unrated = await renderSearch('SearchRoomCard', { room: { ...room, price_per_night: '0.00',
        property: { ...room.property, rating: null, review_count: 0 } } });
    assert.ok(unrated.includes('Cơ sở chưa có đánh giá'));
    assert.match(unrated, /0\s*₫/u);
});

test('restored result header uses server metadata and offers only supported sort values', async () => {
    const markup = await renderSearch('ResultsHeader', { meta: { ...page.meta, total: 1234, from: 13, to: 24 },
        sortBy: 'price_desc', view: 'list', onSortChange() {}, onViewChange() {} });
    assert.ok(markup.includes('1.234 phòng phù hợp'));
    assert.ok(markup.includes('Hiển thị 13–24 trên tổng số 1.234 kết quả'));
    assert.match(markup, /value="price_desc" selected/);
    assert.match(markup, /aria-label="Xem dạng danh sách" aria-pressed="true"/);
    assert.ok(!markup.includes('RATING_DESC'));
});

test('AssistantPanel preserves raw decimal values, field error associations and supported suggestions', async () => {
    const markup = await renderSearch('AssistantPanel', { params: { min_price: '0.00', max_price: '1000000.25', sort: 'price_desc' },
        errors: { max_price: ['Khoảng giá không hợp lệ.'] }, onApply() {} });
    for (const text of ['Tinh chỉnh tìm kiếm', 'value="0.00"', 'value="1000000.25"', 'assistant-max_price-error',
        'Khoảng giá không hợp lệ.', 'Cơ sở được đánh giá 4+', 'Dưới 1 triệu']) assert.ok(markup.includes(text), text);
    assert.ok(!markup.includes('Resort'));
    assert.ok(!markup.includes('Hồ bơi &amp; Spa'));
    assert.ok(!markup.includes('Tìm theo tên'));
});

test('restored grid displays loading skeletons instead of stale room data', async () => {
    const room = mapRoomSearchPage(page).data[0];
    const loading = await renderSearch('ResultsGrid', { rooms: [room], isLoading: true, view: 'list' });
    assert.match(loading, /aria-busy="true"/);
    assert.ok(!loading.includes('Fixture room'));
    const ready = await renderSearch('ResultsGrid', { rooms: [room], isLoading: false, view: 'grid' });
    assert.match(ready, /aria-busy="false"/);
    assert.ok(ready.includes('Fixture room'));
});

test('pagination stays bounded for very large server totals and marks the active page', async () => {
    const markup = await renderSearch('Pagination', { currentPage: 500, totalPages: 2147483647, onPageChange() {} });
    assert.ok((markup.match(/<button/g) ?? []).length <= 9);
    assert.match(markup, /aria-label="Trang 500" aria-current="page"/);
    assert.ok(markup.includes('Trang 2147483647'));
    assert.equal(await renderSearch('Pagination', { currentPage: 1, totalPages: 1, onPageChange() {} }), '');
    const first = await renderSearch('Pagination', { currentPage: 1, totalPages: 3, onPageChange() {} });
    assert.match(first, /aria-label="Trang trước" disabled/);
    const last = await renderSearch('Pagination', { currentPage: 3, totalPages: 3, onPageChange() {} });
    assert.match(last, /aria-label="Trang sau" disabled/);
});

test('empty result actions distinguish retry from clearing filters without inventing data', async () => {
    const empty = await renderSearch('EmptyState', { title: 'Không có phòng phù hợp', message: 'Thử thay đổi bộ lọc.',
        onRetry() {}, actionLabel: 'Xóa bộ lọc' });
    assert.ok(empty.includes('Xóa bộ lọc'));
    const failure = await renderSearch('EmptyState', { title: 'Đã xảy ra lỗi kết nối', message: 'Không tải được phòng.', onRetry() {} });
    assert.ok(failure.includes('Thử lại'));
});

test('property card preserves missing and zero prices without fake rating, city, amenities or image', async () => {
    const property = { id: 9, name: 'Real property', propertyType: 'HOMESTAY', address: 'Real address', price: null,
        averageRating: null, reviewCount: 0, photos: [], amenities: [] };
    const html = await renderSearch('SearchPropertyCard', { property });
    for (const text of ['Real property', 'Real address', 'Chưa có ảnh', 'Chưa có giá tham khảo', 'Cơ sở chưa có đánh giá']) assert.ok(html.includes(text));
    assert.match(html, /href="\/properties\/9"/);
    assert.ok(!html.includes('unsplash'));
    assert.ok(!html.includes('Lưu yêu thích'));
    const zero = await renderSearch('SearchPropertyCard', { property: { ...property, price: '0.00', averageRating: 4.5, reviewCount: 2 } });
    assert.match(zero, /0\s*₫/u);
    assert.ok(zero.includes('2 đánh giá cơ sở'));
});

test('property header hides unsupported sorting and grid selects the correct card projection', async () => {
    const html = await renderSearch('ResultsHeader', { kind: 'properties', meta: { total: 3, current_page: 1, last_page: 2 }, view: 'grid', onViewChange() {} });
    assert.ok(html.includes('3 cơ sở lưu trú'));
    assert.ok(!html.includes('<select'));
    assert.ok(!html.includes('undefined'));
    const grid = await renderSearch('ResultsGrid', { properties: [{ id: 4, name: 'Property without rooms', propertyType: 'HOTEL', address: '', price: null, averageRating: null, reviewCount: 0, photos: [] }], isLoading: false });
    assert.ok(grid.includes('Property without rooms'));
    assert.ok(!grid.includes('khách / phòng'));
});

test('availability cards forward selected room, dates and guests to checkout while catalog cards stay informational', async () => {
    const room = mapRoomSearchPage(page).data[0];
    const query = 'startDate=2026-12-01&endDate=2026-12-03&adults=2&children=1';
    const html = await renderSearch('ResultsGrid', { rooms: [room], isLoading: false, isAvailableDate: true, searchParams: query });
    assert.ok(html.includes('Đặt ngay'));
    const href = html.match(/href="(\/customer\/checkout\?[^"]+)"/)[1].replaceAll('&amp;', '&');
    const url = new URL(href, 'https://example.test');
    assert.equal(url.searchParams.get('roomId'), '3');
    assert.equal(url.searchParams.get('propertyId'), '2');
    for (const [key, value] of new URLSearchParams(query)) assert.equal(url.searchParams.get(key), value);
    assert.ok(!html.includes('Chưa kiểm tra phòng trống'));
    const catalog = await renderSearch('ResultsGrid', { rooms: [room], isLoading: false, searchParams: query });
    assert.ok(!catalog.includes('/customer/checkout'));
    assert.ok(catalog.includes('Chưa kiểm tra phòng trống'));
    const loading = await renderSearch('ResultsGrid', { rooms: [room], isLoading: true, isAvailableDate: true, searchParams: query });
    assert.ok(!loading.includes('/customer/checkout'));
});

test('newest sorting is offered consistently only for availability and metadata tolerates missing from/to', async () => {
    for (const component of ['ResultsHeader', 'AssistantPanel']) {
        const props = { params: { sort: 'newest' }, sortBy: 'newest', availability: true,
            meta: { total: 25, current_page: 2, last_page: 3 }, view: 'grid', onViewChange() {}, onApply() {} };
        const html = await renderSearch(component, props);
        assert.match(html, /value="newest" selected/);
        assert.ok(html.includes('Mới nhất'));
        assert.ok(!html.includes('Giá trị không hợp lệ'));
        assert.ok(!html.includes('undefined'));
        const catalog = await renderSearch(component, { ...props, availability: false });
        assert.ok(catalog.includes('Giá trị không hợp lệ'));
        assert.ok(!catalog.includes('Mới nhất'));
    }
});
