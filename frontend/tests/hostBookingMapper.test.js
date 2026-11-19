import test from 'node:test';
import assert from 'node:assert/strict';
import {
  mapHostBookingListResponse,
  mapHostBookingResponse,
  mapHostBookingServiceResponse,
  mapHostBookingServicesListResponse,
} from '../src/mappers/hostBookingMapper.js';

test('host list mapper preserves pagination and booking values unchanged', () => {
  const response = {
    success: true,
    data: [{ id: 8, room_total: '450000.00', note: null }],
    meta: { current_page: 2, last_page: 4, per_page: 5, total: 18 },
  };
  assert.deepEqual(mapHostBookingListResponse(response), response);
});

test('host detail and transition mapper unwraps success envelope and preserves null and omitted fields', () => {
  const booking = { id: 8, price_per_night: '225000.00', note: null };
  assert.deepEqual(mapHostBookingResponse({ success: true, data: booking }), booking);
  assert.deepEqual(mapHostBookingResponse({ id: 8, room_total: null }), { id: 8, room_total: null });
  // R07: Malformed empty envelope { data: null } must throw mapperError rather than returning raw envelope
  assert.throws(() => mapHostBookingResponse({ success: true, data: null }), /không tồn tại hoặc rỗng/);
});

test('booking-service mappers preserve snapshot decimals, nulls, omitted fields, and empty lists', () => {
  const line = { id: 21, service_name: 'Breakfast', unit: 'set', quantity: '2.50', unit_price: '100.25', amount: '250.63', note: null };
  assert.deepEqual(mapHostBookingServicesListResponse({ success: true, data: [line] }), [line]);
  assert.deepEqual(mapHostBookingServicesListResponse({ success: true, data: [] }), []);
  assert.deepEqual(mapHostBookingServiceResponse({ success: true, data: line }), line);
  assert.deepEqual(mapHostBookingServiceResponse({ id: 21, amount: null }), { id: 21, amount: null });
});

test('host booking mappers reject malformed envelopes with mapper errors', () => {
  assert.throws(() => mapHostBookingListResponse(null), /không hợp lệ/);
  assert.throws(() => mapHostBookingListResponse({ data: 'not-array' }), /không đúng định dạng mảng/);
  assert.throws(() => mapHostBookingListResponse({ data: [], meta: null }), /phân trang meta không hợp lệ/);
  assert.throws(() => mapHostBookingListResponse({ data: [] }), /phân trang meta không hợp lệ/);
  assert.throws(() => mapHostBookingResponse('bad-data'), /không hợp lệ/);
  assert.throws(() => mapHostBookingResponse({}), /không hợp lệ/);
  assert.throws(() => mapHostBookingServicesListResponse({ data: 'invalid' }), /không đúng định dạng mảng/);
  assert.throws(() => mapHostBookingServiceResponse(123), /không hợp lệ/);
  assert.throws(() => mapHostBookingServiceResponse({ success: true, data: null }), /không tồn tại hoặc rỗng/);
});
