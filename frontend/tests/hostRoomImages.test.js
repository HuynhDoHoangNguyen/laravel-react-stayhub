import test from 'node:test';
import assert from 'node:assert/strict';
import { createElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { fileURLToPath } from 'node:url';

let server;
test.after(async () => { await server?.close(); });
async function render(props = {}) {
    if (!server) {
        const { createServer } = await import('vite');
        server = await createServer({ root: fileURLToPath(new URL('..', import.meta.url)), logLevel: 'error',
            server: { middlewareMode: true, hmr: false, watch: null } });
    }
    const { HostRoomImageView } = await server.ssrLoadModule('/src/components/catalog/HostRoomImageManager.jsx');
    return renderToStaticMarkup(createElement(HostRoomImageView, {
        state: { status: 'ready', images: [] }, pending: false, uncertain: false, ...props,
    }));
}

test('production view distinguishes loading, empty, error and success (SSR only)', async () => {
    assert.match(await render(), /Chưa có hình ảnh phòng/);
    const loading = await render({ state: { status: 'loading', images: [] } });
    assert.match(loading, /Đang tải danh sách ảnh phòng/);
    assert.match(loading, /<fieldset disabled/);
    const error = await render({ state: { status: 'error', images: [], message: 'Không có quyền' } });
    assert.match(error, /role="alert"/); assert.match(error, /Không có quyền/);
    assert.match(await render({ success: 'Tải ảnh phòng thành công.' }), /Tải ảnh phòng thành công/);
});

test('production gallery renders primary badge and responsive grid with delete action', async () => {
    const html = await render({ state: { status: 'ready', images: [{ id: 1, url: 'https://example.test/room.png', is_primary: true }] } });
    for (const expected of ['Ảnh đại diện', 'https://example.test/room.png', 'sm:grid-cols-2', 'lg:grid-cols-3', '5 MiB', '4096', '20 ảnh mỗi phòng']) assert.ok(html.includes(expected), expected);
    assert.match(html, /Xóa ảnh/);
    assert.doesNotMatch(html, /Đặt làm ảnh đại diện/);
});

test('secondary image has primary action; deletion requires explicit confirmation and supports cancel', async () => {
    const state = { status: 'ready', images: [{ id: 2, url: 'https://example.test/room.png', is_primary: false }] };
    assert.match(await render({ state }), /Đặt làm ảnh đại diện/);
    assert.doesNotMatch(await render({ state }), /Xác nhận xóa/);
    const confirm = await render({ state, deleting: 2 });
    assert.match(confirm, /Xác nhận xóa/); assert.match(confirm, /Hủy/); assert.match(confirm, /<fieldset disabled/);
    const pending = await render({ state, deleting: 2, pending: true });
    assert.match(pending, /disabled=""/); assert.match(pending, /Đang xử lý/);
    assert.match(await render({ warning: 'Ảnh đã xóa, cleanup cần kiểm tra.' }), /cleanup cần kiểm tra/);
});

test('uncertain and pending states disable upload; preview is rendered safely', async () => {
    const uncertain = await render({ uncertain: true });
    assert.match(uncertain, /Kiểm tra danh sách ảnh/); assert.match(uncertain, /<fieldset disabled/);
    const pending = await render({ pending: true });
    assert.match(pending, /Đang tải ảnh/); assert.match(pending, /<fieldset disabled/);
    const preview = await render({ file: { name: '<script>.png' }, preview: 'blob:fixture', error: 'Ảnh không hợp lệ' });
    assert.match(preview, /Ảnh phòng xem trước/); assert.match(preview, /&lt;script&gt;/);
    assert.match(preview, /aria-describedby=/); assert.match(preview, /aria-invalid="true"/);
});
