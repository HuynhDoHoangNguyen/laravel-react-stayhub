import test from 'node:test';
import assert from 'node:assert/strict';
import {
  mapBookingCommandResponse,
  mapCustomerBookingDetailResponse,
  mapCustomerBookingListResponse,
  mapCustomerBookingServicesResponse,
} from '../src/mappers/bookingMapper.js';

test('booking command mapper unwraps the existing success envelope without changing values', () => {
  const response = {
    id: 17,
    price_per_night: '123456.78',
    room_total: '246913.56',
    note: null,
  };
  assert.deepEqual(mapBookingCommandResponse({ success: true, data: response }), response);
});

test('booking command mapper preserves nulls, omitted fields, and unwrapped responses', () => {
  assert.deepEqual(mapBookingCommandResponse({ id: 17, room_total: null }), { id: 17, room_total: null });
  const emptyEnvelope = { success: true, data: null };
  assert.equal(mapBookingCommandResponse(emptyEnvelope), emptyEnvelope);
  assert.equal(mapBookingCommandResponse(null), null);
});

test('customer list mapper keeps server pagination, null values, and booking fields', () => {
  const booking = { id: 11, room_total: '250000.00', note: null };
  assert.deepEqual(mapCustomerBookingListResponse({
    data: [booking],
    meta: { current_page: 2, last_page: 4, per_page: 5, total: 18 },
  }, { page: 2, perPage: 5 }), {
    data: [booking], all: null, stats: null,
    meta: { current_page: 2, last_page: 4, per_page: 5, total: 18, page: 2, total_pages: 4 },
    source: 'api', isMock: false,
  });
});

test('customer detail mapper preserves decimal strings, nulls, and omitted fields', () => {
  const booking = { id: 11, price_per_night: '125000.25', room_total: '250000.50', note: null };
  assert.deepEqual(mapCustomerBookingDetailResponse({ success: true, data: booking }), {
    data: booking, source: 'api', isMock: false,
  });
});

test('customer booking services mapper preserves list and empty arrays', () => {
  const serviceLine = { id: 1, service_name: 'Laundry', quantity: '2.00', unit_price: '50000.00', amount: '100000.00' };
  assert.deepEqual(mapCustomerBookingServicesResponse({ success: true, data: [serviceLine] }), [serviceLine]);
  assert.deepEqual(mapCustomerBookingServicesResponse({ success: true, data: [] }), []);
});

test('booking mappers reject malformed envelopes with mapper errors', () => {
  assert.throws(() => mapCustomerBookingListResponse(null), /không hợp lệ/);
  assert.throws(() => mapCustomerBookingListResponse({ data: 'not-an-array' }), /không đúng định dạng mảng/);
  assert.throws(() => mapCustomerBookingDetailResponse('bad-payload'), /không hợp lệ/);
  assert.throws(() => mapCustomerBookingServicesResponse({ data: null }), /không đúng định dạng mảng/);
});
