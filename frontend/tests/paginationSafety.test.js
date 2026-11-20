import test, { mock } from 'node:test';
import assert from 'node:assert/strict';
import axios from 'axios';

const sharedClient = axios.create({ adapter: async () => { throw new Error('unexpected network call'); } });
mock.module(new URL('../src/api/axios.js', import.meta.url).href, { defaultExport: sharedClient });
const { createPropertyService } = await import('../src/services/propertyService.js');
const { propertyServiceCatalogService } = await import('../src/services/propertyServiceCatalogService.js');
const { propertyServiceCatalogApi } = await import('../src/api/propertyServiceCatalogApi.js');

test.after(() => mock.restoreAll());

test('R08: getBookableProperty safely iterates subsequent pages and collects all rooms beyond first 50', async () => {
  const page1Rooms = Array.from({ length: 50 }, (_, i) => ({
    id: i + 1,
    property_id: 10,
    name: `Phòng ${i + 1}`,
    capacity: 2,
    price_per_night: '500000.00',
    property: { id: 10, name: 'Khách sạn lớn', address: 'Đà Nẵng' },
    room_type: { id: 1, name: 'Standard' },
  }));

  const page2Rooms = Array.from({ length: 25 }, (_, i) => ({
    id: i + 51,
    property_id: 10,
    name: `Phòng ${i + 51}`,
    capacity: 2,
    price_per_night: '600000.00',
    property: { id: 10, name: 'Khách sạn lớn', address: 'Đà Nẵng' },
    room_type: { id: 2, name: 'Deluxe' },
  }));

  const requestedPages = [];
  const searchMock = async (params) => {
    requestedPages.push(params.page);
    if (params.page === 1) {
      return { data: { data: page1Rooms, meta: { current_page: 1, last_page: 2, per_page: 50, total: 75 } } };
    }
    if (params.page === 2) {
      return { data: { data: page2Rooms, meta: { current_page: 2, last_page: 2, per_page: 50, total: 75 } } };
    }
    return { data: { data: [], meta: { current_page: params.page, last_page: 2, per_page: 50, total: 75 } } };
  };

  const service = createPropertyService({}, { roomSearchApi: { search: searchMock } });
  const result = await service.getBookableProperty(10, { startDate: '2026-12-10', endDate: '2026-12-12' });

  assert.equal(result.source, 'room-search');
  assert.deepEqual(requestedPages, [1, 2]);
  assert.equal(result.data.rooms.length, 75);
  assert.equal(result.data.rooms[0].id, 1);
  assert.equal(result.data.rooms[74].id, 75);
});

test('R08: getBookableProperty passes options/signal and propagates errors without returning partial rooms', async () => {
  let callCount = 0;
  const fiftyRooms = Array.from({ length: 50 }, (_, i) => ({
    id: i + 1,
    property_id: 10,
    name: `P${i + 1}`,
    capacity: 2,
    price_per_night: '500000.00',
    property: { id: 10, name: 'H' },
  }));

  const searchMock = async () => {
    callCount += 1;
    if (callCount === 2) {
      const err = new Error('Network error on page 2');
      err.name = 'AbortError';
      throw err;
    }
    return {
      data: {
        data: fiftyRooms,
        meta: { current_page: 1, last_page: 2, per_page: 50, total: 60 },
      },
    };
  };

  const service = createPropertyService({}, { roomSearchApi: { search: searchMock } });
  const controller = new AbortController();

  await assert.rejects(
    () => service.getBookableProperty(10, { startDate: '2026-12-10', endDate: '2026-12-12' }, { signal: controller.signal }),
    /Network error on page 2/
  );
  assert.equal(callCount, 2);
});

test('R08: listActiveForProperty iterates across pages and collects all active services', async () => {
  const originalList = propertyServiceCatalogApi.list;

  const page1Services = Array.from({ length: 50 }, (_, i) => ({
    id: i + 1,
    name: `Dịch vụ ${i + 1}`,
    price: '50000.00',
    is_active: true,
  }));

  const page2Services = Array.from({ length: 15 }, (_, i) => ({
    id: i + 51,
    name: `Dịch vụ ${i + 51}`,
    price: '75000.00',
    is_active: true,
  }));

  const requestedPages = [];
  propertyServiceCatalogApi.list = async (params) => {
    requestedPages.push(params.page);
    if (params.page === 1) {
      return {
        success: true,
        data: page1Services,
        meta: { current_page: 1, last_page: 2, per_page: 50, total: 65 },
      };
    }
    if (params.page === 2) {
      return {
        success: true,
        data: page2Services,
        meta: { current_page: 2, last_page: 2, per_page: 50, total: 65 },
      };
    }
    return { success: true, data: [], meta: { current_page: params.page, last_page: 2, per_page: 50, total: 65 } };
  };

  try {
    const result = await propertyServiceCatalogService.listActiveForProperty(5);
    assert.equal(result.success, true);
    assert.deepEqual(requestedPages, [1, 2]);
    assert.equal(result.data.length, 65);
    assert.equal(result.data[0].id, 1);
    assert.equal(result.data[64].id, 65);
  } finally {
    propertyServiceCatalogApi.list = originalList;
  }
});

test('PR10-01: listActiveForProperty directly consumes API response body without double-unwrapping data', async () => {
  const originalList = propertyServiceCatalogApi.list;
  const mockServices = [
    { id: 101, name: 'Bữa sáng Buffet', price: '120000.00', is_active: true },
    { id: 102, name: 'Đưa đón sân bay', price: '350000.00', is_active: true },
  ];

  let passedOptions = null;
  propertyServiceCatalogApi.list = async (params, options) => {
    passedOptions = options;
    // Exactly matches real Laravel response shape returned by client.get(...).data
    return {
      success: true,
      message: 'Lấy danh sách dịch vụ thành công.',
      data: mockServices,
      meta: { current_page: 1, last_page: 1, per_page: 50, total: 2 },
    };
  };

  try {
    const controller = new AbortController();
    const result = await propertyServiceCatalogService.listActiveForProperty(8, { signal: controller.signal });
    assert.equal(result.success, true);
    assert.equal(result.total, 2);
    assert.equal(result.data.length, 2);
    assert.equal(result.data[0].id, 101);
    assert.equal(result.data[1].id, 102);
    assert.equal(passedOptions?.signal, controller.signal);
  } finally {
    propertyServiceCatalogApi.list = originalList;
  }
});
