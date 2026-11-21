import test from 'node:test';
import assert from 'node:assert/strict';
import { createElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { MemoryRouter } from 'react-router-dom';
import { fileURLToPath } from 'node:url';

let server;
test.after(async () => { await server?.close(); });
async function render(component, props) {
    if (!server) {
        const { createServer } = await import('vite');
        server = await createServer({ root: fileURLToPath(new URL('..', import.meta.url)), logLevel: 'error',
            server: { middlewareMode: true, hmr: false, watch: null } });
    }
    const module = await server.ssrLoadModule('/src/components/hotel/' + component + '.jsx');
    return renderToStaticMarkup(createElement(MemoryRouter, null, createElement(module[component], props)));
}
const rooms = [1, 2].map(id => ({ id, name: 'Phòng ' + id, price: '550000.25', capacity: 2, photos: [], amenities: ['Wifi'] }));
const context = { status: 'valid', checkIn: '2027-01-01', checkOut: '2027-01-03', adults: 2, children: 0, errors: [] };
const booking = { propertyId: 7, context, search: '?startDate=2027-01-01&endDate=2027-01-03&adults=2',
    availability: { loading: false, error: null, value: { data: { id: 7, rooms } } } };

test('all room actions and availability text belong to the corresponding card article and footer', async () => {
    const html = await render('RoomsList', { rooms, booking, selectedRoomId: 1, onSelect() {} });
    const cards = [...html.matchAll(/<article\b[\s\S]*?<\/article>/g)].map(match => match[0]);
    assert.equal(cards.length, 2);
    for (const [index, card] of cards.entries()) {
        const footer = card.match(/<footer\b[\s\S]*?<\/footer>/)?.[0];
        assert.ok(footer, 'actions are inside a card footer');
        for (const text of ['Còn phòng theo ngày', 'Xem chi tiết phòng', 'Đặt phòng', index === 0 ? 'Đã chọn phòng này' : 'Chọn phòng']) assert.ok(footer.includes(text), text);
        assert.ok(footer.includes('/rooms/' + (index + 1) + '?startDate='));
        assert.ok(footer.includes('roomId=' + (index + 1)));
        assert.equal((footer.match(/<a /g) ?? []).length, 2);
        assert.equal((footer.match(/<button /g) ?? []).length, 1);
        assert.ok(!/<a\b[^>]*>[\s\S]*?<(?:a|button)\b/.test(footer.split('</a>')[0]));
    }
    assert.match(cards[0], /data-selected="true"/);
    assert.match(cards[0], /aria-pressed="true"/);
    assert.ok(!cards[1].includes('data-selected="true"'));
    const outside = html.replace(/<article\b[\s\S]*?<\/article>/g, '');
    assert.ok(!outside.includes('/customer/checkout'));
    assert.ok(!outside.includes('Xem chi tiết phòng'));
});

test('unavailable or unconfirmed cards keep detail links inside without enabling booking', async () => {
    for (const availability of [{ loading: true, error: null, value: null },
        { loading: false, error: null, value: { data: null } },
        { loading: false, error: null, value: { data: null, isTruncated: true } }]) {
        const html = await render('RoomsList', { rooms: [rooms[0]], booking: { ...booking, availability }, onSelect() {} });
        const article = html.match(/<article\b[\s\S]*?<\/article>/)[0];
        assert.ok(article.includes('Xem chi tiết phòng'));
        assert.ok(!html.includes('/customer/checkout'));
        assert.ok(!html.includes('<button'));
    }
});

test('RoomCard remains usable without a footer and escapes room text', async () => {
    const html = await render('RoomCard', { room: { ...rooms[0], name: '<script>alert(1)</script>' } });
    assert.ok(!html.includes('<footer'));
    assert.ok(html.includes('&lt;script&gt;'));
    assert.ok(!html.includes('<script>'));
    assert.ok(html.includes('550.000,25') || html.includes('550.000'));
});

test('detail actions preserve button semantics including disabled and pressed states', async () => {
    const html = await render('DetailAction', { tone: 'secondary', type: 'button', disabled: true, 'aria-pressed': true, children: 'Đã chọn phòng' });
    assert.match(html, /^<button\b/);
    assert.ok(html.includes('disabled=""'));
    assert.ok(html.includes('aria-pressed="true"'));
    assert.ok(html.includes('type="button"'));
});

test('breadcrumbs preserve destination URLs and mark only the current location', async () => {
    const href = '/properties?page=2&per_page=6';
    const html = await render('DetailBreadcrumbs', { items: [{ label: 'Cơ sở lưu trú', to: href }, { label: 'Cơ sở <A>' }] });
    assert.ok(html.includes('aria-label="Đường dẫn điều hướng"'));
    assert.ok(html.includes('href="/properties?page=2&amp;per_page=6"'));
    assert.equal((html.match(/aria-current="page"/g) ?? []).length, 1);
    assert.ok(html.includes('Cơ sở &lt;A&gt;'));
});

test('shared gallery preserves property labeling and supports room labels, thumbnails and safe empty images', async () => {
    const photos = ['https://example.test/room-1.jpg', 'https://example.test/room-2.jpg'];
    const property = await render('PropertyGallery', { photos, name: 'Cơ sở thật' });
    assert.ok(property.includes('aria-label="Ảnh cơ sở lưu trú"'));
    const room = await render('PropertyGallery', { photos, name: 'Phòng thật', label: 'Ảnh phòng' });
    for (const text of ['aria-label="Ảnh phòng"', 'aria-label="Xem ảnh phóng to"', 'aria-label="Ảnh trước"', 'aria-label="Ảnh sau"',
        'aria-label="Xem ảnh 1" aria-pressed="true"', 'aria-label="Xem ảnh 2" aria-pressed="false"', 'https://example.test/room-1.jpg', 'https://example.test/room-2.jpg']) assert.ok(room.includes(text), text);
    const empty = await render('PropertyGallery', { photos: [], name: 'Phòng thiếu ảnh', label: 'Ảnh phòng' });
    assert.ok(empty.includes('aria-label="Ảnh phòng"'));
    assert.ok(empty.includes('Chưa có ảnh'));
    assert.ok(!empty.includes('<img'));
    assert.ok(!empty.includes('<button'));
    const single = await render('PropertyGallery', { photos: [photos[0]], label: 'Ảnh phòng' });
    assert.ok(!single.includes('aria-label="Ảnh sau"'));
});

test('room breadcrumbs keep the property URL and guest/date context', async () => {
    const html = await render('DetailBreadcrumbs', { items: [
        { label: 'Cơ sở lưu trú', to: '/properties' },
        { label: 'Cơ sở thật', to: '/properties/7?checkIn=2027-01-01&checkOut=2027-01-03&adults=2&children=1', state: { catalogPage: '?page=2' } },
        { label: 'Phòng thật' },
    ] });
    assert.ok(html.includes('href="/properties/7?checkIn=2027-01-01&amp;checkOut=2027-01-03&amp;adults=2&amp;children=1"'));
    assert.ok(html.includes('aria-current="page"'));
    assert.ok(html.includes('Phòng thật'));
});
