import test from 'node:test';
import assert from 'node:assert/strict';
import { mapBookingCommandResponse } from '../src/mappers/bookingMapper.js';

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
