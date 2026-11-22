import test from 'node:test';
import assert from 'node:assert/strict';
import { mapInvoiceListResponse, mapInvoiceDetailResponse, mapAdminInvoiceListResponse, mapAdminInvoiceHostOptionsResponse, mapAdminInvoicePropertyOptionsResponse, mapHostInvoiceBookingContext, mapHostEligibleBookings } from '../src/mappers/invoiceMapper.js';
import { normalizeApiError } from '../src/api/apiError.js';

test('booking context projects fields and rejects inconsistent eligibility or a different booking', () => {
  const data = {
    booking_id: 13, booking_code: 'BK-13', customer_name: 'Khách', property_name: 'Cơ sở', room_name: 'Phòng',
    check_in_date: '2026-11-20', check_out_date: '2026-11-22', booking_status: 'COMPLETED', can_create: true, invoice: null,
  };
  assert.deepEqual(mapHostInvoiceBookingContext({ data: { ...data, customer_email: 'private@example.test' } }, '13'), data);
  const existing = { ...data, can_create: false, invoice: { id: 7, invoice_code: 'INV-7' } };
  assert.deepEqual(mapHostInvoiceBookingContext({ data: existing }, 13), existing);

  for (const change of [{ booking_id: 12 }, { booking_status: 'CONFIRMED' }, { invoice: undefined },
    { invoice: existing.invoice }, { can_create: 'true' }, { check_in_date: '2026-02-30' }]) {
    assert.throws(() => mapHostInvoiceBookingContext({ data: { ...data, ...change } }, 13),
      (error) => error.code === 'INVALID_INVOICE_RESPONSE');
  }
});

test('create result cannot be displayed for a different booking', () => {
  assert.throws(() => mapInvoiceDetailResponse({ data: { ...invoice(), items: [] } }, 13),
    (error) => error.code === 'INVALID_INVOICE_RESPONSE');
});

test('eligible bookings project public picker fields and reject malformed rows and pages', () => {
  const booking = {
    booking_id: 13, booking_code: 'BK-13', customer_name: 'Khách', property_name: 'Cơ sở', room_name: 'Phòng',
    check_in_date: '2026-11-20', check_out_date: '2026-11-22',
  };
  const source = freeze(list([{ ...booking, customer_email: 'private@example.test' }]));
  const result = mapHostEligibleBookings(source);
  assert.deepEqual(result.data, [booking]);
  assert.deepEqual(result.meta, source.meta);
  assert.deepEqual(mapHostEligibleBookings(list([])).data, []);

  for (const data of [[booking, booking], [{ ...booking, booking_id: 0 }], [{ ...booking, check_out_date: '2026-02-30' }]]) {
    assert.throws(() => mapHostEligibleBookings(list(data)), (error) => error.code === 'INVALID_INVOICE_RESPONSE');
  }
  const invalid = { ...source, meta: { ...source.meta, per_page: 51 } };
  assert.throws(() => mapHostEligibleBookings(invalid), (error) => error.code === 'INVALID_INVOICE_RESPONSE');
});

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

test('Property options project three fields and preserve immutable pagination with optional host scope', () => {
  const body = freeze(list([{ id: 21, name: 'Cơ sở hiện tại', host_id: 17, address: 'Private' }]));
  const output = mapAdminInvoicePropertyOptionsResponse(body, 17);
  assert.deepEqual(output.data, [{ id: 21, name: 'Cơ sở hiện tại', host_id: 17 }]);
  assert.notEqual(output.data[0], body.data[0]);
  assert.notEqual(output.meta, body.meta);
  assert.notEqual(output.links, body.links);
  assert.deepEqual(mapAdminInvoicePropertyOptionsResponse(body).data, output.data);
  assert.deepEqual(mapAdminInvoicePropertyOptionsResponse(list([]), 17).data, []);
  assert.deepEqual(mapAdminInvoicePropertyOptionsResponse(list([{ id: 21, name: '0', host_id: Number.MAX_SAFE_INTEGER }])).data,
    [{ id: 21, name: '0', host_id: Number.MAX_SAFE_INTEGER }]);
});

test('Property options reject missing or unsafe owners, duplicates, malformed success and wrong-host rows', () => {
  for (const row of [{ id: 21, name: 'Cơ sở' }, { id: 21, name: 'Cơ sở', host_id: '17' },
    { id: 21, name: 'Cơ sở', host_id: 0 }, { id: 21, name: 'Cơ sở', host_id: Number.MAX_SAFE_INTEGER + 1 },
    { id: 21, name: 'Cơ sở', host_id: 18 }, { id: '21', name: 'Cơ sở', host_id: 17 }]) {
    assert.throws(() => mapAdminInvoicePropertyOptionsResponse(list([row]), 17), (error) => error.kind === 'contract');
  }
  for (const body of [null, {}, { ...list(), links: {} }, { ...list(), meta: {} },
    list([{ id: 21, name: 'A', host_id: 17 }, { id: 21, name: 'B', host_id: 17 }])]) {
    assert.throws(() => mapAdminInvoicePropertyOptionsResponse(body), (error) => error.code === 'INVALID_INVOICE_RESPONSE');
  }
});

test('Host options project current names and safe IDs with immutable pagination and no private fields', () => {
  const body = freeze(list([{ id: 17, name: 'Tên hiện tại', email: 'private@example.test', status: 'BLOCKED' }]));
  const output = mapAdminInvoiceHostOptionsResponse(body);
  assert.deepEqual(output.data, [{ id: 17, name: 'Tên hiện tại' }]);
  assert.notEqual(output.data[0], body.data[0]);
  assert.deepEqual(output.meta, body.meta);
  assert.notEqual(output.meta, body.meta);
  assert.notEqual(output.links, body.links);
  assert.deepEqual(mapAdminInvoiceHostOptionsResponse(list([])).data, []);
  assert.deepEqual(mapAdminInvoiceHostOptionsResponse(list([{ id: Number.MAX_SAFE_INTEGER, name: '0' }])).data, [{ id: Number.MAX_SAFE_INTEGER, name: '0' }]);
});

test('Host options reject bad envelopes, IDs, names, duplicate rows and pagination', () => {
  const cases = [null, {}, { ...list(), data: {} }, { ...list(), links: {} },
    { ...list(), meta: { ...list().meta, total: '2' } }, { ...list(), meta: { ...list().meta, per_page: 51 } },
    ...[{}, { id: '17', name: 'Host' }, { id: 0, name: 'Host' }, { id: Number.MAX_SAFE_INTEGER + 1, name: 'Host' },
      { id: 17, name: null }, { id: 17 }].map((row) => list([row])),
    list([{ id: 17, name: 'A' }, { id: 17, name: 'B' }])];
  for (const body of cases) assert.throws(() => mapAdminInvoiceHostOptionsResponse(body), (error) => error.kind === 'contract');
});

test('Admin maps booking code with decimal/date/null snapshots and cloned pagination', () => {
  const source = { ...invoice(), booking_code: 'BK-11' };
  const body = freeze(list([{ ...source, items: [{ private: true }] }]));
  const output = mapAdminInvoiceListResponse(body);
  assert.equal(Object.keys(output.data[0]).length, 11);
  assert.equal(output.data[0].booking_code, 'BK-11');
  for (const field of ['customer_email', 'customer_phone', 'items', 'subtotal', 'discount_amount', 'extra_fee']) {
    assert.equal(field in output.data[0], false);
  }
  assert.equal(output.data[0].total_amount, '1234567.89');
  assert.equal(output.data[0].paid_at, null);
  assert.notEqual(output.meta, body.meta);
  assert.notEqual(output.links, body.links);
  const minimal = Object.fromEntries(Object.keys(output.data[0]).map((field) => [field, source[field]]));
  assert.deepEqual(mapAdminInvoiceListResponse(list([minimal])).data[0], minimal);
  assert.equal(mapAdminInvoiceListResponse(list([{ ...minimal, booking_code: null }])).data[0].booking_code, null);
  assert.equal(Object.hasOwn(mapAdminInvoiceListResponse(list([invoice()])).data[0], 'booking_code'), false);
  assert.deepEqual(mapAdminInvoiceListResponse(list([])).data, []);
  for (const payment_status of ['UNPAID', 'PAID', 'REFUNDED']) {
    assert.equal(mapAdminInvoiceListResponse(list([{ ...minimal, payment_status, paid_at: null, created_at: null }])).data[0].payment_status, payment_status);
  }
});

test('Admin rejects malformed success instead of disguising it as empty data', () => {
  const changes = [{ id: '7' }, { booking_id: 0 }, { booking_code: 11 }, { booking_code: ' ' }, { customer_name: null }, { total_amount: 123 },
    { total_amount: '1.2' }, { total_amount: '1e2' }, { payment_status: 'FUTURE' },
    { paid_at: undefined }, { created_at: 'invalid' }];
  const cases = [null, {}, { ...list(), data: null }, { ...list(), meta: {} },
    { ...list(), links: {} }, { ...list(), meta: { ...list().meta, per_page: 51 } },
    ...changes.map((change) => list([{ ...invoice(), ...change }]))];
  for (const body of cases) assert.throws(() => mapAdminInvoiceListResponse(body), (error) => error.kind === 'contract');
});

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
