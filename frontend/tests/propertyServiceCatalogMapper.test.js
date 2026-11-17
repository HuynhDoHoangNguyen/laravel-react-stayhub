import test from 'node:test';
import assert from 'node:assert/strict';
import {
  mapHostPropertiesResponse,
  mapPropertyServiceListResponse,
  mapPropertyServiceResponse,
} from '../src/mappers/propertyServiceCatalogMapper.js';

test('host property response maps the collection and service list keeps pagination envelope', () => {
  const properties = [{ id: 4, name: 'StayHub', owner: 'omitted' }];
  const list = { success: true, data: [{ id: 11, price: '0.00', description: null }], meta: { page: 2, total: 8 } };
  assert.deepEqual(mapHostPropertiesResponse({ success: true, data: properties }), properties);
  assert.equal(mapPropertyServiceListResponse(list), list);
  assert.deepEqual(mapPropertyServiceListResponse(list).meta, { page: 2, total: 8 });
});

test('service command mapping unwraps the envelope and preserves null, decimals, and omitted fields', () => {
  const service = { id: 11, price: '123.45', description: null };
  assert.deepEqual(mapPropertyServiceResponse({ success: true, data: service }), service);
  assert.deepEqual(mapPropertyServiceResponse({ id: 11, price: null }), { id: 11, price: null });
});
