import test, { mock } from 'node:test';
import assert from 'node:assert/strict';
import { readPropertyBooking, checkoutTarget, bookingSearchTarget } from '../src/utils/propertyBooking.js';
import { mapRoomAvailability } from '../src/mappers/roomAvailabilityMapper.js';
import { createElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { MemoryRouter } from 'react-router-dom';
import { fileURLToPath } from 'node:url';

mock.module(new URL('../src/api/axios.js', import.meta.url).href, { defaultExport: {} });
const { createRoomSearchLoader } = await import('../src/services/roomSearchService.js');
test.after(() => mock.restoreAll());

const today = new Date(2026, 10, 20);
const read = query => readPropertyBooking(query, today);
const dates = 'checkIn=2027-01-01&checkOut=2027-01-03';

test('date aliases preserve calendar dates and explicit guest breakdown', () => {
    for (const [start, end] of [['checkIn', 'checkOut'], ['startDate', 'endDate'], ['check_in_date', 'check_out_date']]) {
        const context = read(`${start}=2027-01-01&${end}=2027-01-03&adults=2&children=1&guest_count=3`);
        assert.equal(context.status, 'valid');
        assert.equal(context.adults + context.children, 3);
        assert.equal(context.checkIn, '2027-01-01');
    }
    assert.equal(read(dates + '&guest_count=4').adults, 4);
    assert.equal(read('').status, 'missing');
    assert.equal(read('checkIn=2027-01-01').status, 'missing');
});

test('invalid or contradictory input never becomes a checkout link', () => {
    for (const query of [
        'checkIn=2027-02-30&checkOut=2027-03-05', 'checkIn=2026-01-01&checkOut=2027-01-03',
        'checkIn=2027-01-03&checkOut=2027-01-01', 'checkIn=2027-01-01&checkOut=2027-01-01',
        dates + '&startDate=2027-01-02', dates + '&checkIn=2027-01-01',
        dates + '&adults=2x', dates + '&adults=0', dates + '&children=-1', dates + '&children=1.5',
        dates + '&adults=2&children=1&guest_count=4', dates + '&adults=9007199254740992',
    ]) {
        const context = read(query);
        assert.equal(context.status, 'invalid', query);
        assert.equal(checkoutTarget(5, 12, context), null);
    }
    assert.equal(checkoutTarget(5, 12, read('')), null);
});

test('checkout URL contains only selected resource and normalized stay context', () => {
    const context = read(dates + '&adults=2&children=1&redirect=https://evil.test&propertyId=99&roomId=99');
    const url = new URL(checkoutTarget(5, 12, context), 'https://example.test');
    assert.equal(url.pathname, '/customer/checkout');
    assert.deepEqual(Object.fromEntries(url.searchParams), { propertyId: '5', roomId: '12', checkIn: '2027-01-01', checkOut: '2027-01-03', adults: '2', children: '1' });
    assert.throws(() => checkoutTarget('../5', 12, context));
    const search = new URL(bookingSearchTarget(context), url);
    assert.equal(search.pathname, '/search');
    assert.equal(search.searchParams.get('startDate'), '2027-01-01');
    assert.equal(search.searchParams.get('adults'), '2');
});

test('availability mapper fails closed and copies the public response', () => {
    const body = { success: true, data: { available: true, reasons: [], blocked_ranges: [{ start: '2027-02-01', end: '2027-02-03' }] } };
    const result = mapRoomAvailability(body);
    assert.equal(result.available, true);
    assert.notEqual(result.blockedRanges[0], body.data.blocked_ranges[0]);
    for (const invalid of [null, {}, { ...body, success: false }, { success: true, data: { ...body.data, available: 1 } },
        { success: true, data: { ...body.data, reasons: [null] } }]) assert.throws(() => mapRoomAvailability(invalid));
});

test('availability request lifecycle aborts old requests and rejects late success after a new failure', async () => {
    const loader = createRoomSearchLoader();
    let finish;
    let oldSignal;
    const old = loader.load(signal => { oldSignal = signal; return new Promise(resolve => { finish = resolve; }); });
    await loader.load(async () => { throw new Error('offline'); });
    finish({ available: true });
    await old;
    assert.equal(oldSignal.aborted, true);
    assert.equal(loader.getSnapshot().value, null);
    assert.equal(loader.getSnapshot().error.message, 'offline');
    await loader.load(async () => ({ available: false }));
    assert.equal(loader.getSnapshot().value.available, false);
    loader.cancel();
});

test('booking panel renders missing and invalid guidance and no premature checkout link while checking', async () => {
    const { createServer } = await import('vite');
    const server = await createServer({ root: fileURLToPath(new URL('..', import.meta.url)), logLevel: 'error',
        server: { middlewareMode: true, hmr: false, watch: null } });
    try {
        const { RoomBookingPanel } = await server.ssrLoadModule('/src/components/hotel/RoomBookingPanel.jsx');
        const render = context => renderToStaticMarkup(createElement(MemoryRouter, null,
            createElement(RoomBookingPanel, { room: { id: 12, name: 'Phòng Deluxe', price_per_night: '850000.25', capacity: 3,
                room_type: { id: 3, name: 'Deluxe' }, property: { id: 5 } }, context })));
        const missing = render(read(''));
        assert.ok(missing.includes('Vui lòng chọn ngày'));
        assert.ok(!missing.includes('/customer/checkout'));
        const invalid = render(read(dates + '&adults=abc'));
        assert.ok(invalid.includes('role="alert"'));
        assert.ok(!invalid.includes('/customer/checkout'));
        const pending = render(read(dates + '&adults=2&children=1'));
        assert.ok(pending.includes('Đang kiểm tra phòng trống'));
        assert.ok(pending.includes('2 người lớn'));
        for (const text of ['Phòng Deluxe', 'Deluxe', '850.000,25', 'Sức chứa tối đa: 3', 'Nhận phòng', 'Trả phòng']) assert.ok(pending.includes(text), text);
        assert.ok(pending.includes('<aside'));
        assert.ok(!pending.includes('/customer/checkout'));
    } finally { await server.close(); }
});
