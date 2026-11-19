import test from 'node:test';
import assert from 'node:assert/strict';
import {
  mapHostPropertiesResponse,
  mapPropertyServiceListResponse,
  mapPropertyServiceResponse,
} from '../src/mappers/propertyServiceCatalogMapper.js';

test('host property response maps the collection and service list keeps pagination envelope', () => {
  const properties = [{ id: 4, name: 'StayHub', owner: 'omitted' }];
  const list = { success: true, data: [{ id: 11, price: '0.00', description: null }], meta: { current_page: 2, last_page: 3, per_page: 15, total: 8 } };
  assert.deepEqual(mapHostPropertiesResponse({ success: true, data: properties }), properties);
  const mappedList = mapPropertyServiceListResponse(list);
  assert.deepEqual(mappedList.data, list.data);
  assert.equal(mappedList.meta.current_page, 2);
  assert.equal(mappedList.meta.total, 8);
});

test('service command mapping unwraps the envelope and preserves null, decimals, and omitted fields', () => {
  const service = { id: 11, price: '123.45', description: null };
  assert.deepEqual(mapPropertyServiceResponse({ success: true, data: service }), service);
  assert.deepEqual(mapPropertyServiceResponse({ id: 11, price: null }), { id: 11, price: null });
});

test('property service catalog mapper rejects malformed envelopes with mapper errors', () => {
  assert.throws(() => mapHostPropertiesResponse(null), /không hợp lệ/);
  assert.throws(() => mapHostPropertiesResponse({ data: 'not-array' }), /không đúng định dạng mảng/);
  assert.throws(() => mapPropertyServiceListResponse(null), /không hợp lệ/);
  assert.throws(() => mapPropertyServiceListResponse({ data: 'not-array' }), /không đúng định dạng mảng/);
  assert.throws(() => mapPropertyServiceListResponse({ data: [] }), /phân trang dịch vụ không hợp lệ/);
  assert.throws(() => mapPropertyServiceResponse('bad'), /không hợp lệ/);
  assert.throws(() => mapPropertyServiceResponse({ success: true, data: null }), /không tồn tại hoặc rỗng/);
});
