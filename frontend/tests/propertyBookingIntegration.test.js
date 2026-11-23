import test, { mock } from 'node:test';
import assert from 'node:assert/strict';
import axios from 'axios';
import { createElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { MemoryRouter } from 'react-router-dom';
import { fileURLToPath } from 'node:url';
import { readPropertyBooking } from '../src/utils/propertyBooking.js';

let adapter;
const client = axios.create({ adapter: config => adapter(config) });
mock.module(new URL('../src/api/axios.js', import.meta.url).href, { defaultExport: client });
const { createPropertyAvailabilityLoader, propertyRoomStatus } = await import('../src/hooks/usePropertyAvailability.js');
let server;
test.after(async () => { await server?.close(); mock.restoreAll(); });
const context = readPropertyBooking('checkIn=2027-01-01&checkOut=2027-01-03&adults=2&children=1', new Date(2026, 10, 21));
const room = { id: 23, name: 'Phòng thật', capacity: 3, price: '520000.25', photos: [], amenities: [] };
const property = { id: 7, name: 'Cơ sở thật', rooms: [room], price: null };
const rawRoom = { id: 23, name: room.name, price_per_night: room.price, capacity: 3,
    property: { id: 7, name: property.name }, images: [], amenities: [] };
const ready = { loading: false, error: null, value: { source: 'room-search', data: { id: 7, rooms: [room] } } };
const reply = (config, rows = [rawRoom]) => ({ config, headers: {}, status: 200,
    data: { data: rows, meta: { current_page: 1, last_page: 1, per_page: 50, total: rows.length } } });

async function render(component, props) {
    if (!server) {
        const { createServer } = await import('vite');
        server = await createServer({ root: fileURLToPath(new URL('..', import.meta.url)), logLevel: 'error',
            server: { middlewareMode: true, hmr: false, watch: null } });
    }
    const module = await server.ssrLoadModule('/src/components/hotel/' + component + '.jsx');
    return renderToStaticMarkup(createElement(MemoryRouter, null, createElement(module[component], props)));
}

test('property availability uses production service and transport with property, dates, guest sum and cancellation', async () => {
    const calls = [];
    adapter = async config => { calls.push(config); return reply(config); };
    const loader = createPropertyAvailabilityLoader(7, context, 'api');
    await loader.reload();
    assert.equal(calls.length, 1);
    assert.equal(calls[0].url, '/api/public/rooms/search');
    assert.deepEqual(calls[0].params, { property_id: 7, check_in_date: '2027-01-01', check_out_date: '2027-01-03', guest_count: 3, per_page: 50, page: 1, sort: 'price_asc' });
    assert.equal(propertyRoomStatus(loader.getSnapshot(), 23), 'available');
    assert.equal(propertyRoomStatus(loader.getSnapshot(), 24), 'unavailable');
    loader.cancel();
    assert.equal(calls[0].signal.aborted, true);
});

test('invalid context, mock catalog source and wrong-property results never open booking', async () => {
    let calls = 0;
    adapter = async config => { calls++; return reply(config, [{ ...rawRoom, property: { id: 8 } }]); };
    for (const [stay, source] of [[{ ...context, status: 'missing' }, 'api'], [context, 'mock'], [context, 'api']]) {
        const loader = createPropertyAvailabilityLoader(7, stay, source);
        await loader.reload();
        assert.ok(loader.getSnapshot().error);
        assert.equal(propertyRoomStatus(loader.getSnapshot(), 23), 'unknown');
        loader.cancel();
    }
    assert.equal(calls, 1);
});

test('late response after leaving and retry after network failure cannot retain stale availability', async () => {
    let finish;
    adapter = config => new Promise(resolve => { finish = () => resolve(reply(config)); });
    const loader = createPropertyAvailabilityLoader(7, context, 'api');
    const pending = loader.reload();
    await new Promise(resolve => setTimeout(resolve, 0));
    loader.cancel();
    finish();
    await pending;
    assert.equal(loader.getSnapshot().value, null);
    adapter = async () => { throw new Error('offline'); };
    await loader.reload();
    assert.ok(loader.getSnapshot().error);
    assert.equal(propertyRoomStatus(loader.getSnapshot(), 23), 'unknown');
    adapter = async config => reply(config, []);
    await loader.reload();
    assert.equal(propertyRoomStatus(loader.getSnapshot(), 23), 'unavailable');
    loader.cancel();
});

test('truncated availability keeps known rooms bookable and unknown rooms unconfirmed', () => {
    const partial = { ...ready, value: { ...ready.value, isTruncated: true, hasMore: true } };
    assert.equal(propertyRoomStatus(partial, 23), 'available');
    assert.equal(propertyRoomStatus(partial, 24), 'unknown');
    assert.equal(propertyRoomStatus({ ...ready, loading: true }, 23), 'unknown');
    assert.equal(propertyRoomStatus({ ...ready, error: new Error() }, 23), 'unknown');
});

test('room cards and sidebar render the same checkout target and preserve detail context', async () => {
    const booking = { propertyId: 7, context, availability: ready,
        search: '?startDate=2027-01-01&endDate=2027-01-03&adults=2&children=1', state: { catalogPage: '?page=2' } };
    const cards = await render('RoomsList', { rooms: [room], booking, selectedRoomId: 23, onSelect() {} });
    const panel = await render('PropertyBookingPanel', { property, context, availability: ready, selectedRoom: room, onRetry() {} });
    const extract = html => html.match(/href="(\/customer\/checkout\?[^" ]+)"/)[1].replaceAll('&amp;', '&');
    assert.equal(extract(cards), extract(panel));
    assert.deepEqual(Object.fromEntries(new URL(extract(panel), 'https://example.test').searchParams), {
        propertyId: '7', roomId: '23', checkIn: '2027-01-01', checkOut: '2027-01-03', adults: '2', children: '1',
    });
    assert.ok(cards.includes('/rooms/23?startDate=2027-01-01&amp;endDate=2027-01-03&amp;adults=2&amp;children=1'));
    assert.ok(cards.includes('Đã chọn phòng này'));
    const cardArticle = cards.match(/<article\b[\s\S]*?<\/article>/)[0];
    assert.ok(cardArticle.includes('Đã chọn phòng này'));
    assert.ok(cardArticle.includes(extract(cards).replaceAll('&', '&amp;')));
    assert.ok(cardArticle.includes('<footer'));
    assert.ok(panel.includes('Phòng thật'));
    assert.ok(!panel.includes('Không thể đặt phòng trực tuyến'));
});

test('missing dates, loading, errors and unavailable rooms have no checkout links; legacy consumers still select rooms', async () => {
    for (const availability of [{ ...ready, loading: true }, { ...ready, error: new Error('offline') },
        { ...ready, value: { data: null } }, { ...ready, value: { data: null, isTruncated: true } }]) {
        const cards = await render('RoomsList', { rooms: [room], booking: { propertyId: 7, context, availability, search: '' }, onSelect() {} });
        assert.ok(!cards.includes('/customer/checkout'));
        assert.ok(!cards.includes('Chọn phòng</button>'));
        const panel = await render('PropertyBookingPanel', { property, context, availability, selectedRoom: room, onRetry() {} });
        assert.ok(!panel.includes('/customer/checkout'));
    }
    const missing = await render('PropertyBookingPanel', { property, context: readPropertyBooking(''), availability: ready, selectedRoom: room });
    assert.ok(!missing.includes('/customer/checkout'));
    assert.ok(missing.includes('Chọn ngày nhận'));
    const legacy = await render('RoomsList', { rooms: [room], onSelect() {} });
    assert.ok(legacy.includes('Chọn phòng</button>'));
    assert.ok(!legacy.includes('/customer/checkout'));
});
