import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { fileURLToPath, pathToFileURL } from 'node:url';
import path from 'node:path';

const servicePath = fileURLToPath(new URL('../src/services/propertyService.js', import.meta.url));
const mapperUrl = pathToFileURL(path.resolve(path.dirname(servicePath), '../mappers/propertyMapper.js')).href;
const checkoutPath = fileURLToPath(new URL('../src/pages/customer/CustomerCheckoutPage.jsx', import.meta.url));
const fixtures = { search: async () => ({ data: { data: [] } }), mockProperties: [] };
globalThis.__propertyServiceTestFixtures = fixtures;

let source = await readFile(servicePath, 'utf8');
source = source
  .replace("import api from '@/api/axios';", 'const api = {};')
  .replace("import { roomSearchApi } from '@/api/roomSearchApi';", 'const roomSearchApi = { search: (...args) => globalThis.__propertyServiceTestFixtures.search(...args) };')
  .replace("import { mapFeaturedProperty, mapPropertyFromRooms, mapPropertyRooms, mapSearchRoom } from '@/mappers/propertyMapper';", `import { mapFeaturedProperty, mapPropertyFromRooms, mapPropertyRooms, mapSearchRoom } from '${mapperUrl}';`)
  .replace("import { MOCK_PROPERTIES } from '@/mocks/propertyMockData';", 'const MOCK_PROPERTIES = globalThis.__propertyServiceTestFixtures.mockProperties;')
  .replace("import { createCatalogService } from './catalog';", 'const createCatalogService = () => ({ getProperties: async () => ({ data: [], source: \'api\' }) });')
  .replaceAll('import.meta.env.DEV', 'false')
  .replaceAll('import.meta.env.VITE_ENABLE_PROPERTY_MOCKS', "'false'");

const serviceUrl = `data:text/javascript;charset=utf-8,${encodeURIComponent(source)}`;
const { propertyService } = await import(serviceUrl);

test.after(() => {
  delete globalThis.__propertyServiceTestFixtures;
});

test('getBookableProperty maps Room Search rooms and returns the booking source', async () => {
  const room = {
    id: 23,
    property_id: 7,
    name: 'Phòng đôi',
    capacity: 2,
    price_per_night: '520000.00',
    property: { id: 7, name: 'Chỗ nghỉ mẫu', address: 'Hà Nội' },
    room_type: { id: 3, name: 'Phòng đôi' },
  };
  let requestedParams;
  fixtures.search = async (params) => {
    requestedParams = params;
    return { data: { data: [room] } };
  };

  const result = await propertyService.getBookableProperty(7, {
    startDate: '2026-12-10', endDate: '2026-12-12', adults: 2, children: 1,
  });

  assert.equal(result.source, 'room-search');
  assert.equal(result.data.id, 7);
  assert.equal(result.data.rooms[0].id, 23);
  assert.equal(result.data.rooms[0].price, 520000);
  assert.equal(result.data.rooms[0].capacity, 2);
  assert.deepEqual(requestedParams, {
    check_in_date: '2026-12-10', check_out_date: '2026-12-12', guest_count: 3,
    per_page: 50, page: 1, property_id: 7, sort: 'price_asc',
  });
});

test('getBookableProperty returns null data when Room Search has no rooms', async () => {
  fixtures.search = async () => ({ data: { data: [] } });
  assert.deepEqual(await propertyService.getBookableProperty(7, {
    startDate: '2026-12-10', endDate: '2026-12-12',
  }), { data: null, source: 'room-search' });
});

test('getPropertyById keeps its existing API source and mapped result', async () => {
  fixtures.search = async () => ({ data: { data: [{
    id: 23, property_id: 7, name: 'Phòng đôi', capacity: 2,
    price_per_night: '520000.00', property: { id: 7, name: 'Chỗ nghỉ mẫu' },
  }] } });
  const result = await propertyService.getPropertyById(7, {
    startDate: '2026-12-10', endDate: '2026-12-12',
  });
  assert.equal(result.source, 'api');
  assert.equal(result.data.rooms[0].id, 23);
});

test('checkout keeps rejecting a response marked as API data', async () => {
  const checkout = await readFile(checkoutPath, 'utf8');
  assert.match(checkout, /if\s*\(res\?\.source\s*===\s*'api'\)/);
});
