import test, { mock } from 'node:test';
import assert from 'node:assert/strict';
import axios from 'axios';
import { readSearchNavigation, changeSearchTab, changePropertyFilters, legacyPropertiesTarget, searchBackLink, searchStayQuery } from '../src/utils/searchNavigation.js';
import { readPropertyBooking } from '../src/utils/propertyBooking.js';
import { createPublicPropertyApi } from '../src/api/publicPropertyApi.js';
import { createCatalogLoader } from '../src/services/catalog.js';

test('property filters reset the page, preserve stay and room criteria, and stay out of room requests', () => {
    const search = changePropertyFilters('q=Hue&page=2&per_page=1&adults=2&min_rating=3', {
        keyword: ' Đà Nẵng ', minRating: '4.5',
    });
    const url = new URLSearchParams(search);
    assert.equal(url.get('city'), 'Đà Nẵng');
    assert.equal(url.has('q'), false);
    assert.equal(url.has('page'), false);
    assert.equal(url.get('adults'), '2');
    assert.equal(searchBackLink({ searchPage: '?' + search }), '/search?' + search);
    const rooms = readSearchNavigation(changeSearchTab(search, 'rooms'));
    assert.equal(rooms.propertyRating, '4.5');
    assert.equal(new URLSearchParams(rooms.roomSearch).has('property_min_rating'), false);
    assert.equal(new URLSearchParams(rooms.roomSearch).get('min_rating'), '3');
    const reset = new URLSearchParams(changePropertyFilters(search));
    assert.equal(reset.has('city'), false);
    assert.equal(reset.has('property_min_rating'), false);
    assert.equal(reset.get('min_rating'), '3');
});

let adapter;
const client = axios.create({ adapter: config => adapter(config) });
mock.module(new URL('../src/api/axios.js', import.meta.url).href, { defaultExport: client });
const { propertyService } = await import('../src/services/propertyService.js');
test.after(() => mock.restoreAll());

test('general searches default to properties and legacy room filters retain their tab', () => {
    for (const search of ['', 'city=Hue', 'adults=2&children=1', 'startDate=2026-12-01&endDate=2026-12-03&guest_count=3']) {
        assert.equal(readSearchNavigation(search).tab, 'properties');
    }
    const query = 'min_price=0.00&amenity_ids[]=7&amenity_ids[]=8&page=2';
    const result = readSearchNavigation(query);
    assert.equal(result.tab, 'rooms');
    assert.equal(result.error, null);
    assert.equal(new URLSearchParams(result.roomSearch).get('min_price'), '0.00');
    assert.deepEqual(new URLSearchParams(result.roomSearch).getAll('amenity_ids[]'), ['7', '8']);
    assert.equal(readSearchNavigation('tab=rooms&' + query).roomSearch, result.roomSearch);
    assert.equal(readSearchNavigation('tab=properties&' + query).tab, 'properties');
    assert.equal(new URLSearchParams(changeSearchTab('', 'rooms')).get('tab'), 'rooms');
});

test('property details receive only stay context and can return to the complete search URL', () => {
    const dated = '?tab=properties&city=Hue&checkIn=2026-12-01&checkOut=2026-12-03&adults=2&children=1&min_price=90000&page=3';
    const today = new Date(2026, 10, 23);
    assert.equal(searchStayQuery(dated), '?checkIn=2026-12-01&checkOut=2026-12-03&adults=2&children=1');
    assert.deepEqual(readPropertyBooking(searchStayQuery(dated), today), {
        status: 'valid', checkIn: '2026-12-01', checkOut: '2026-12-03', adults: 2, children: 1, errors: [],
    });
    assert.equal(searchBackLink({ searchPage: dated }), '/search' + dated);
    assert.equal(searchStayQuery('?keyword=Hue&guest_count=3'), '?guest_count=3');
    assert.equal(searchStayQuery('?tab=properties&page=2'), '');
});

test('invalid and duplicate tabs are errors rather than silently selecting another data source', () => {
    for (const query of ['tab=', 'tab=hosts', 'tab=rooms&tab=properties', 'tab=properties&tab=properties']) {
        assert.ok(readSearchNavigation(query).error);
    }
    assert.throws(() => changeSearchTab('', 'hosts'));
});

test('switching tabs resets only pagination and retains raw room criteria for return and Back', () => {
    const before = 'min_price=0.00&max_price=100.25&guest_count=3&min_rating=4.5&room_type_ids[]=2&amenity_ids[]=8&sort=price_desc&page=4&per_page=6';
    const next = changeSearchTab(before, 'properties');
    const parsed = readSearchNavigation(next);
    assert.equal(parsed.tab, 'properties');
    assert.deepEqual(parsed.propertyParams, { page: 1, per_page: 12 });
    const room = readSearchNavigation(changeSearchTab(next, 'rooms'));
    assert.equal(new URLSearchParams(room.roomSearch).get('max_price'), '100.25');
    assert.equal(new URLSearchParams(room.roomSearch).get('page'), null);
    assert.equal(new URLSearchParams(room.roomSearch).get('per_page'), null);
    assert.equal(new URLSearchParams(before).get('page'), '4');
});

test('PR15-01: changing tabs resets page sizes for both consumers and preserves all other URL values', () => {
    const criteria = 'startDate=2026-12-01&endDate=2026-12-03&check_in_date=2026-12-01&check_out_date=2026-12-03&adults=2&children=1&roomsCount=1&city=Hue&keyword=Hue&q=Hue&guest_count=3&min_price=0.00&max_price=1000000.25&min_rating=4&sort=newest&room_type_ids[]=2&room_type_ids[]=3&amenity_ids[]=7&amenity_ids[]=8';
    for (const size of [undefined, 6, 12, 24, 25, 50]) {
        for (const page of [2, 8]) {
            const original = criteria + '&page=' + page + (size === undefined ? '' : '&per_page=' + size);
            let next = original;
            for (const tab of ['properties', 'rooms', 'properties']) {
                next = changeSearchTab(next, tab);
                const url = new URLSearchParams(next);
                assert.equal(url.has('page'), false);
                assert.equal(url.has('per_page'), false);
                assert.equal(readSearchNavigation(next).tab, tab);
                assert.deepEqual(readSearchNavigation(next).propertyParams, { page: 1, per_page: 12 });
                url.delete('tab');
                assert.deepEqual([...url], [...new URLSearchParams(criteria)]);
                assert.equal(searchBackLink({ searchPage: '?' + next }), '/search?' + next);
            }
            // Original history entries and legacy redirects retain their own pagination.
            assert.equal(new URLSearchParams(original).get('page'), String(page));
            const redirected = new URL(legacyPropertiesTarget(original), 'https://example.test');
            assert.equal(redirected.searchParams.get('per_page'), size === undefined ? null : String(size));
            assert.equal(readSearchNavigation(original).roomSearch, new URLSearchParams(original).toString());
            if (size > 24) assert.equal(readSearchNavigation(redirected.search).propertyParams, null);
        }
    }
});

test('PR15-01: availability tab switch reaches the real property service with valid pagination', async () => {
    const calls = [];
    adapter = async config => {
        calls.push(config);
        return { status: 200, headers: {}, config, data: { data: [],
            meta: { current_page: 1, last_page: 1, per_page: 12, total: 0 }, links: {} } };
    };
    for (const size of [25, 50]) {
        const nav = readSearchNavigation(changeSearchTab('startDate=2026-12-01&endDate=2026-12-03&adults=2&page=2&per_page=' + size, 'properties'));
        const result = await propertyService.getProperties(nav.propertyParams);
        assert.equal(result.source, 'api');
        assert.deepEqual(result.data, []);
    }
    assert.equal(calls.length, 2);
    for (const call of calls) {
        assert.equal(call.url, '/api/public/properties');
        assert.deepEqual(call.params, { page: 1, per_page: 12 });
    }
});

test('property pagination rejects duplicate and malformed fields without receiving room filters', () => {
    for (const query of ['page=0', 'page=01', 'page=2&page=3', 'per_page=25', 'per_page=2&per_page=2']) {
        assert.equal(readSearchNavigation('tab=properties&' + query).propertyParams, null);
    }
    assert.deepEqual(readSearchNavigation('tab=properties&min_price=bad&page=2&per_page=3').propertyParams, { page: 2, per_page: 3 });
});

test('legacy list redirect preserves pagination, invalid input and unknown intent for explicit validation', () => {
    assert.equal(legacyPropertiesTarget(''), '/search?tab=properties');
    const url = new URL(legacyPropertiesTarget('?page=2&per_page=6&city=Hue'), 'https://example.test');
    assert.equal(url.pathname, '/search');
    assert.equal(url.searchParams.get('page'), '2');
    assert.equal(url.searchParams.get('city'), 'Hue');
    assert.equal(url.searchParams.get('tab'), 'properties');
    assert.equal(new URL(legacyPropertiesTarget('?tab=rooms&page=0'), url).searchParams.get('tab'), 'properties');
});

test('backlinks restore exact room/property context and accept validated legacy pagination', () => {
    for (const search of ['', '?guest_count=3&min_price=0.00&page=2', '?tab=properties&page=3&per_page=6']) {
        assert.equal(searchBackLink({ searchPage: search }), '/search' + search);
    }
    assert.equal(searchBackLink({ catalogPage: '?page=2&per_page=6' }), '/search?page=2&per_page=6&tab=properties');
    assert.equal(searchBackLink(null), '/search?tab=properties');
});

test('untrusted backlink values never become an external route or bypass tab validation', () => {
    for (const searchPage of ['https://evil.test', '//evil.test', '/admin', '?redirect=https://evil.test', '?tab=hosts', '?tab=rooms&tab=properties']) {
        assert.equal(searchBackLink({ searchPage }), '/search?tab=properties');
    }
    assert.equal(searchBackLink({ catalogPage: '?page=0' }), '/search?tab=properties');
});

test('availability context survives tab switching and detail backlinks without leaking into property API params', () => {
    const query = 'startDate=2026-12-01&endDate=2026-12-03&city=Hue&adults=2&children=1&guest_count=3&room_type_ids[]=2&sort=newest&page=3';
    const properties = readSearchNavigation(changeSearchTab(query, 'properties'));
    assert.deepEqual(properties.propertyParams, { page: 1, per_page: 12 });
    const rooms = new URLSearchParams(changeSearchTab('tab=properties&' + query, 'rooms'));
    for (const key of ['startDate', 'endDate', 'city', 'adults', 'children', 'guest_count', 'sort']) {
        assert.equal(rooms.get(key), new URLSearchParams(query).get(key));
    }
    assert.equal(rooms.has('page'), false);
    assert.equal(searchBackLink({ searchPage: '?' + query }), '/search?' + query);
    const aliases = '?check_in_date=2026-12-01&check_out_date=2026-12-03&keyword=Hue&room_type_id=2';
    assert.equal(searchBackLink({ searchPage: aliases }), '/search' + aliases);
});

test('production property service sends only active pagination and preserves null/zero prices and metadata', async () => {
    const rows = [null, '0.00'].map((price, index) => ({ id: index + 1, name: 'Property ' + index,
        type: 'HOTEL', address: 'Address', starting_price: price, rating: null, review_count: 0 }));
    const meta = { current_page: 2, last_page: 3, per_page: 2, total: 5, from: 3, to: 4 };
    const calls = [];
    adapter = async config => { calls.push(config); return { status: 200, headers: {}, config, data: { data: rows, meta, links: { next: '?page=3' } } }; };
    const signal = new AbortController().signal;
    const nav = readSearchNavigation('tab=properties&guest_count=3&min_price=0.00&sort=price_desc&page=2&per_page=2');
    const result = await propertyService.getProperties(nav.propertyParams, { signal });
    assert.equal(calls.length, 1);
    assert.equal(calls[0].url, '/api/public/properties');
    assert.deepEqual(calls[0].params, { page: 2, per_page: 2 });
    assert.equal(calls[0].signal, signal);
    assert.equal(result.data[0].price, null);
    assert.equal(result.data[1].price, '0.00');
    assert.deepEqual(result.meta, meta);
    assert.equal(result.links.next, '?page=3');
    assert.equal(result.source, 'api');
});

test('property HTTP failures stay errors without mock fallback and can be retried explicitly', async () => {
    let calls = 0;
    adapter = async () => { calls++; throw Object.assign(new Error('offline'), { response: { status: 500 } }); };
    const loader = createCatalogLoader(options => propertyService.getProperties({ page: 1, per_page: 12 }, options));
    await loader.reload();
    assert.equal(loader.getSnapshot().status, 'error');
    assert.equal(loader.getSnapshot().result, null);
    assert.equal(calls, 1);
    adapter = async config => ({ status: 200, headers: {}, config, data: { data: [], meta: { current_page: 1, last_page: 1, per_page: 12, total: 0 }, links: {} } });
    await loader.reload();
    assert.equal(loader.getSnapshot().status, 'ready');
    assert.deepEqual(loader.getSnapshot().result.data, []);
    loader.cancel();
});

test('leaving property results aborts the request and ignores late responses even if transport ignores abort', async () => {
    let finish;
    let signal;
    const loader = createCatalogLoader(options => { signal = options.signal; return new Promise(resolve => { finish = resolve; }); });
    const pending = loader.reload();
    loader.cancel();
    finish({ data: ['stale'] });
    await pending;
    assert.equal(signal.aborted, true);
    assert.equal(loader.getSnapshot().result, null);
});

test('property API retains its actual endpoint and does not call legacy Search', async () => {
    adapter = async config => { assert.equal(config.url, '/api/public/properties'); return { status: 200, headers: {}, config, data: { data: [] } }; };
    assert.deepEqual(await createPublicPropertyApi(client).getProperties({ page: 1, per_page: 12 }), { data: [] });
});

test('searchPaginatedProperties delegates to /api/public/properties-search with keyword, guest_count and pagination without forcing last_page = 1', async () => {
    const calls = [];
    const page2Item = {
        id: 15,
        name: 'Villa Đà Lạt Gia Đình',
        type: 'HOMESTAY',
        address: '20 Trần Phú, Đà Lạt',
        starting_price: '1200000.00',
        rating: 4.8,
        review_count: 5,
    };
    const serverMeta = { current_page: 2, last_page: 2, per_page: 1, total: 2, from: 2, to: 2 };
    adapter = async config => {
        calls.push(config);
        return {
            status: 200,
            headers: {},
            config,
            data: { data: [page2Item], meta: serverMeta, links: { prev: '?page=1' } },
        };
    };

    const result = await propertyService.searchPaginatedProperties({
        page: 2,
        per_page: 1,
        keyword: ' Đà Lạt ',
        guest_count: 4,
        min_rating: '4.5',
    });

    assert.equal(calls.length, 1);
    assert.equal(calls[0].url, '/api/public/properties-search');
    assert.deepEqual(calls[0].params, {
        page: 2,
        per_page: 1,
        keyword: 'Đà Lạt',
        guest_count: 4,
        min_rating: '4.5',
    });
    assert.equal(result.data.length, 1);
    assert.equal(result.data[0].id, 15);
    assert.deepEqual(result.meta, serverMeta);
});

test('searchPaginatedProperties falls back to /api/public/properties when neither keyword nor guest_count is provided', async () => {
    const calls = [];
    const meta = { current_page: 1, last_page: 1, per_page: 12, total: 0 };
    adapter = async config => {
        calls.push(config);
        return { status: 200, headers: {}, config, data: { data: [], meta, links: {} } };
    };

    await propertyService.searchPaginatedProperties({ page: 1, per_page: 12, keyword: '   ', guest_count: null });
    assert.equal(calls.length, 1);
    assert.equal(calls[0].url, '/api/public/properties');
    assert.deepEqual(calls[0].params, { page: 1, per_page: 12 });
});
