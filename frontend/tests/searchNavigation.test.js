import test, { mock } from 'node:test';
import assert from 'node:assert/strict';
import axios from 'axios';
import { readSearchNavigation, changeSearchTab, legacyPropertiesTarget, searchBackLink } from '../src/utils/searchNavigation.js';
import { createPublicPropertyApi } from '../src/api/publicPropertyApi.js';
import { createCatalogLoader } from '../src/services/catalog.js';

let adapter;
const client = axios.create({ adapter: config => adapter(config) });
mock.module(new URL('../src/api/axios.js', import.meta.url).href, { defaultExport: client });
const { propertyService } = await import('../src/services/propertyService.js');
test.after(() => mock.restoreAll());

test('default room URLs stay intact and UI tab is removed before room parsing', () => {
    const query = 'min_price=0.00&amenity_ids[]=7&amenity_ids[]=8&page=2';
    const result = readSearchNavigation(query);
    assert.equal(result.tab, 'rooms');
    assert.equal(result.error, null);
    assert.equal(new URLSearchParams(result.roomSearch).get('min_price'), '0.00');
    assert.deepEqual(new URLSearchParams(result.roomSearch).getAll('amenity_ids[]'), ['7', '8']);
    assert.equal(readSearchNavigation('tab=rooms&' + query).roomSearch, result.roomSearch);
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
    assert.deepEqual(parsed.propertyParams, { page: 1, per_page: 6 });
    const room = readSearchNavigation(changeSearchTab(next, 'rooms'));
    assert.equal(new URLSearchParams(room.roomSearch).get('max_price'), '100.25');
    assert.equal(new URLSearchParams(room.roomSearch).get('page'), null);
    assert.equal(new URLSearchParams(before).get('page'), '4');
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
