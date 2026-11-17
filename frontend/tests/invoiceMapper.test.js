import test from 'node:test';
import assert from 'node:assert/strict';
import { mapInvoiceListResponse, mapInvoiceDetailResponse } from '../src/mappers/invoiceMapper.js';
import { normalizeApiError } from '../src/api/apiError.js';

function invoice() {
  return {
    id: 7, invoice_code: 'INV-7', booking_id: 11,
    customer_name: 'Khách', customer_email: 'fixture@example.test', customer_phone: '0900000000',
    property_name: 'Property', room_name: 'Room', subtotal: '9999999999.99',
    discount_amount: '0.00', extra_fee: '0.01', total_amount: '1234567.89',
    payment_status: 'UNPAID', paid_at: null, created_at: '2026-11-14T00:00:00.000000Z',
  };
}

function list(data = [invoice()]) {
  return { data, meta: { current_page: 2, last_page: 3, per_page: 10, total: 21, path: '/api/host/invoices', links: [{ url: null, label: 'Previous', active: false }] },
    links: { first: '?page=1', last: '?page=3', prev: '?page=1', next: '?page=3' }, summary: { fixture_count: 21 } };
}

function freeze(value) {
  if (value && typeof value === 'object') {
    Object.values(value).forEach(freeze);
    Object.freeze(value);
  }
  return value;
}

test('list preserves exact Page-facing values, decimal snapshots and metadata without mutation', () => {
  const body = freeze(list([invoice(), { ...invoice(), id: 8, payment_status: 'FUTURE_STATUS', paid_at: '2026-11-14T02:00:00.000000Z' }]));
  const output = mapInvoiceListResponse(body);
  assert.deepEqual(output, body);
  assert.notEqual(output, body);
  assert.notEqual(output.data[0], body.data[0]);
  assert.notEqual(output.meta, body.meta);
  assert.notEqual(output.meta.links, body.meta.links);
  assert.notEqual(output.summary, body.summary);
  assert.equal('items' in output.data[0], false);
  assert.equal(output.data[0].subtotal, '9999999999.99');
  assert.equal(output.data[0].total_amount, '1234567.89');
  assert.equal(output.data[1].payment_status, 'FUTURE_STATUS');
});

test('valid empty list preserves metadata and does not mean malformed response', () => {
  const body = list([]);
  body.meta = { current_page: 1, last_page: 1, per_page: 10, total: 0 };
  body.links.prev = null; body.links.next = null;
  assert.deepEqual(mapInvoiceListResponse(freeze(body)), body);
});

test('detail preserves item decimal strings, descriptions, nullable dates and empty items', () => {
  const room = { id: 1, invoice_id: 7, item_type: 'ROOM', item_name: 'Room snapshot', description: null, quantity: '2.00', unit_price: '100.00', amount: '200.00' };
  const service = { ...room, id: 2, item_type: 'SERVICE', description: 'Breakfast', quantity: '1.25', unit_price: '12.34', amount: '15.43' };
  const body = freeze({ data: { ...invoice(), items: [room, service] } });
  const output = mapInvoiceDetailResponse(body);
  assert.deepEqual(output, body);
  assert.notEqual(output.data.items[0], room);
  assert.deepEqual(mapInvoiceDetailResponse({ data: { ...invoice(), created_at: null, items: [] } }).data.items, []);
});

test('malformed envelopes, fields, money and items fail safely instead of falling back to empty', () => {
  const cases = [
    () => mapInvoiceListResponse(null),
    () => mapInvoiceListResponse({ ...list(), data: {} }),
    () => mapInvoiceListResponse({ ...list(), meta: null }),
    () => mapInvoiceListResponse({ ...list(), meta: { ...list().meta, total: '21' } }),
    () => mapInvoiceListResponse({ ...list(), links: {} }),
    () => mapInvoiceListResponse(list([{ ...invoice(), id: '7' }])),
    () => mapInvoiceListResponse(list([{ ...invoice(), total_amount: 123 }])),
    () => mapInvoiceListResponse(list([{ ...invoice(), subtotal: '1e2' }])),
    () => mapInvoiceListResponse(list([{ ...invoice(), created_at: 'not-a-date' }])),
    () => mapInvoiceDetailResponse({ data: invoice() }),
    () => mapInvoiceDetailResponse({ data: { ...invoice(), items: null } }),
    () => mapInvoiceDetailResponse({ data: { ...invoice(), items: [{ id: 1 }] } }),
  ];
  for (const invoke of cases) {
    assert.throws(invoke, (error) => {
      const parsed = normalizeApiError(error);
      assert.equal(parsed.code, 'INVALID_INVOICE_RESPONSE');
      assert.equal(parsed.kind, 'contract');
      assert.equal(parsed.isNetworkError, false);
      assert.equal('response' in parsed, false);
      return true;
    });
  }
});


test('create projection keeps exact decimal/null/SERVICE names without mutating a frozen response', () => {
  const body = freeze({ data: { ...invoice(), items: ['', '   ', '0'].map((name, index) => ({
    id: index + 1, invoice_id: 7, item_type: 'SERVICE', item_name: name, description: null,
    quantity: '1.25', unit_price: '12.34', amount: '15.43',
  })) } });
  const output = mapInvoiceDetailResponse(body);
  assert.deepEqual(output, body);
  assert.notEqual(output.data.items, body.data.items);
  assert.equal(output.data.subtotal, '9999999999.99');
  assert.equal(output.data.total_amount, '1234567.89');
  assert.equal(output.data.paid_at, null);
  assert.deepEqual(output.data.items.map(({ item_name }) => item_name), ['', '   ', '0']);
  for (const items of [undefined, null, {}, [{ ...body.data.items[0], amount: 15.43 }]]) {
    assert.throws(() => mapInvoiceDetailResponse({ data: { ...invoice(), items } }), (error) => error.code === 'INVALID_INVOICE_RESPONSE');
  }
});
