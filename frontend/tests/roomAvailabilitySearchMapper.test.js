import test from 'node:test';
import assert from 'node:assert/strict';
import {
  mapRoomAvailabilitySearchResponse,
  mapAvailabilityRoomItem,
} from '../src/mappers/roomAvailabilitySearchMapper.js';

test('PR12-05: mapRoomAvailabilitySearchResponse maps valid response with rooms', () => {
  const apiPayload = {
    success: true,
    data: [
      {
        id: 101,
        name: 'Deluxe Suite',
        capacity: 3,
        price_per_night: '750000.00',
        property: {
          id: 12,
          name: 'StayHub Da Nang',
          address: '123 Beach Road',
          rating: 4.8,
          review_count: 24,
        },
        room_type: {
          id: 2,
          name: 'Suite',
        },
        amenities: [
          { id: 1, name: 'Wifi' },
          { id: 2, name: 'Ban công' },
        ],
        images: ['/storage/rooms/101_1.jpg'],
      },
    ],
    meta: {
      current_page: 1,
      last_page: 2,
      per_page: 15,
      total: 25,
    },
  };

  const result = mapRoomAvailabilitySearchResponse(apiPayload);
  assert.equal(result.success, true);
  assert.equal(result.data.length, 1);
  assert.equal(result.data[0].id, 101);
  assert.equal(result.data[0].name, 'Deluxe Suite');
  assert.equal(result.data[0].price_per_night, '750000.00');
  assert.equal(result.data[0].property.name, 'StayHub Da Nang');
  assert.equal(result.data[0].property.rating, 4.8);
  assert.equal(result.data[0].room_type.name, 'Suite');
  assert.equal(result.data[0].amenities.length, 2);
  assert.equal(result.meta.total, 25);
  assert.equal(result.meta.last_page, 2);
});

test('PR12-05: mapRoomAvailabilitySearchResponse handles valid empty response', () => {
  const apiPayload = {
    success: true,
    data: [],
    meta: {
      current_page: 1,
      last_page: 1,
      per_page: 15,
      total: 0,
    },
  };

  const result = mapRoomAvailabilitySearchResponse(apiPayload);
  assert.equal(result.success, true);
  assert.deepEqual(result.data, []);
  assert.equal(result.meta.total, 0);
  assert.equal(result.meta.current_page, 1);
});

test('PR12-05: mapRoomAvailabilitySearchResponse rejects null or non-object response', () => {
  assert.throws(() => mapRoomAvailabilitySearchResponse(null), { isMapperError: true });
  assert.throws(() => mapRoomAvailabilitySearchResponse(undefined), { isMapperError: true });
  assert.throws(() => mapRoomAvailabilitySearchResponse('invalid string'), { isMapperError: true });
});

test('PR12-05: mapRoomAvailabilitySearchResponse rejects missing or non-array data', () => {
  assert.throws(() => mapRoomAvailabilitySearchResponse({ success: true }), { isMapperError: true });
  assert.throws(() => mapRoomAvailabilitySearchResponse({ success: true, data: null, meta: {} }), { isMapperError: true });
  assert.throws(() => mapRoomAvailabilitySearchResponse({ success: true, data: 'not an array', meta: {} }), { isMapperError: true });
});

test('PR12-05: mapRoomAvailabilitySearchResponse rejects missing or invalid pagination meta', () => {
  assert.throws(() => mapRoomAvailabilitySearchResponse({ success: true, data: [] }), { isMapperError: true });
  assert.throws(() => mapRoomAvailabilitySearchResponse({ success: true, data: [], meta: null }), { isMapperError: true });
  assert.throws(() => mapRoomAvailabilitySearchResponse({ success: true, data: [], meta: { per_page: 15 } }), { isMapperError: true });
});

test('PR12-05: mapAvailabilityRoomItem rejects invalid room structure without id', () => {
  assert.throws(() => mapAvailabilityRoomItem(null), { isMapperError: true });
  assert.throws(() => mapAvailabilityRoomItem({ name: 'No ID Room' }), { isMapperError: true });
});
