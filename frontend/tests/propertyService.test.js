import test, { mock } from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import axios from 'axios';

// Only the Vite-configured transport is replaced; the service itself is the production module.
const sharedClient = axios.create({ adapter: async () => { throw new Error('unexpected network call'); } });
mock.module(new URL('../src/api/axios.js', import.meta.url).href, { defaultExport: sharedClient });
const { createPropertyService } = await import('../src/services/propertyService.js');
test.after(() => mock.restoreAll());

const checkoutPath = fileURLToPath(new URL('../src/pages/customer/CustomerCheckoutPage.jsx', import.meta.url));

function createService({ search, catalogApi = {}, dev = false, enableMocks = 'false', properties = [] } = {}) {
  return createPropertyService(catalogApi, { dev, enableMocks, properties, roomSearchApi: { search } });
}

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
  const service = createService({
    search: async (params) => {
      requestedParams = params;
      return { data: { data: [room] } };
    },
  });

  const result = await service.getBookableProperty(7, {
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
  const service = createService({ search: async () => ({ data: { data: [] } }) });
  assert.deepEqual(await service.getBookableProperty(7, {
    startDate: '2026-12-10', endDate: '2026-12-12',
  }), { data: null, source: 'room-search' });
});

test('getBookableProperty uses mock data only when DEV mocks are explicitly enabled', async () => {
  let searched = false;
  const service = createService({
    search: async () => { searched = true; return { data: { data: [] } }; },
    dev: true,
    enableMocks: 'true',
    properties: [{ id: 7, name: 'Mock' }],
  });
  const result = await service.getBookableProperty('7', { startDate: '2026-12-10', endDate: '2026-12-12' });
  assert.deepEqual(result, { data: { id: 7, name: 'Mock' }, source: 'mock' });
  assert.equal(searched, false);
});

test('getPropertyById stays on the Catalog API and never calls Room Search', async () => {
  let searched = false;
  let requestedId;
  const service = createService({
    search: async () => { searched = true; return { data: { data: [] } }; },
    catalogApi: {
      getPropertyById: async (id) => {
        requestedId = id;
        return { data: { id: 7, name: 'Chỗ nghỉ mẫu', rooms: [] } };
      },
    },
  });
  const result = await service.getPropertyById(7);
  assert.equal(result.source, 'api');
  assert.equal(String(requestedId), '7');
  assert.equal(searched, false);
});

test('checkout keeps rejecting a response marked as API data', async () => {
  const checkout = await readFile(checkoutPath, 'utf8');
  assert.match(checkout, /if\s*\(res\?\.source\s*===\s*'api'\)/);
});
