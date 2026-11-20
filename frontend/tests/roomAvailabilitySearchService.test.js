import test, { mock } from 'node:test';
import assert from 'node:assert/strict';
import axios from 'axios';

let adapterMock = null;
const client = axios.create({ adapter: (config) => adapterMock(config) });
mock.module(new URL('../src/api/axios.js', import.meta.url).href, { defaultExport: client });

const {
  isAvailabilitySearch,
  parseAvailabilitySearchParams,
  mapAvailabilityRooms,
  searchAvailableRooms,
} = await import('../src/services/roomAvailabilitySearchService.js');

test.after(() => mock.restoreAll());

test('PR10-02: isAvailabilitySearch identifies availability search when dates are provided', () => {
  assert.equal(isAvailabilitySearch('startDate=2026-12-01&endDate=2026-12-03&city=Đà Nẵng'), true);
  assert.equal(isAvailabilitySearch('check_in_date=2026-12-01&check_out_date=2026-12-03'), true);
  assert.equal(isAvailabilitySearch('startDate=2026-12-01'), false);
  assert.equal(isAvailabilitySearch('city=Hà Nội&adults=2'), false);
  assert.equal(isAvailabilitySearch(''), false);
  assert.equal(isAvailabilitySearch(null), false);
});

test('PR10-02: parseAvailabilitySearchParams normalizes search parameters from URL', () => {
  const search = 'city=Nha Trang&startDate=2026-12-10&endDate=2026-12-15&adults=2&children=1&min_price=500000&max_price=2000000&sort=price_asc&amenity_ids[]=1&amenity_ids[]=3&page=2&per_page=10';
  const parsed = parseAvailabilitySearchParams(search);

  assert.equal(parsed.checkIn, '2026-12-10');
  assert.equal(parsed.checkOut, '2026-12-15');
  assert.equal(parsed.keyword, 'Nha Trang');
  assert.equal(parsed.adults, 2);
  assert.equal(parsed.children, 1);
  assert.equal(parsed.guests, 3);

  assert.deepEqual(parsed.params, {
    check_in_date: '2026-12-10',
    check_out_date: '2026-12-15',
    guest_count: 3,
    keyword: 'Nha Trang',
    min_price: '500000',
    max_price: '2000000',
    sort: 'price_asc',
    page: 2,
    per_page: 10,
    amenity_ids: [1, 3],
  });
});

test('PR10-02: mapAvailabilityRooms maps room fields and stringifies images for CatalogImage', () => {
  const rawRooms = [
    {
      id: 1,
      name: 'Phòng Deluxe Hướng Biển',
      capacity: 3,
      price_per_night: '750000.00',
      property: {
        id: 10,
        name: 'Resort Biển Đông',
        address: '123 Đường Biển',
        rating: 4.9,
        review_count: 15,
      },
      room_type: { id: 2, name: 'Deluxe' },
      amenities: [{ id: 1, name: 'Wifi' }, { id: 4, name: 'Điều hòa' }],
      images: [
        { id: 101, image_path: 'https://example.com/img1.jpg', is_primary: true },
        { id: 102, image_path: 'https://example.com/img2.jpg', is_primary: false },
      ],
    },
  ];

  const mapped = mapAvailabilityRooms(rawRooms);
  assert.equal(mapped.length, 1);
  assert.equal(mapped[0].id, 1);
  assert.equal(mapped[0].name, 'Phòng Deluxe Hướng Biển');
  assert.equal(mapped[0].price_per_night, '750000.00');
  assert.equal(mapped[0].property.name, 'Resort Biển Đông');
  assert.deepEqual(mapped[0].images, [
    'https://example.com/img1.jpg',
    'https://example.com/img2.jpg',
  ]);
  assert.deepEqual(mapped[0].amenities, [
    { id: 1, name: 'Wifi' },
    { id: 4, name: 'Điều hòa' },
  ]);
});

test('PR10-02: searchAvailableRooms connects to /api/public/rooms/search and returns paginated result', async () => {
  let capturedConfig = null;
  adapterMock = async (config) => {
    capturedConfig = config;
    return {
      status: 200,
      config,
      data: {
        success: true,
        message: 'Tìm kiếm phòng thành công.',
        data: [
          {
            id: 5,
            name: 'Phòng Superior',
            capacity: 2,
            price_per_night: '600000.00',
            property: { id: 20, name: 'Khách sạn Sài Gòn', address: 'Quận 1', rating: null, review_count: 0 },
            room_type: { id: 1, name: 'Superior' },
            amenities: [],
            images: [],
          },
        ],
        meta: {
          current_page: 1,
          last_page: 1,
          per_page: 15,
          total: 1,
        },
      },
    };
  };

  const controller = new AbortController();
  const searchUrl = 'city=Sài Gòn&startDate=2026-12-20&endDate=2026-12-22&adults=2';
  const result = await searchAvailableRooms(searchUrl, { signal: controller.signal });

  assert.equal(capturedConfig.url, '/api/public/rooms/search');
  assert.equal(capturedConfig.method, 'get');
  assert.equal(capturedConfig.params.check_in_date, '2026-12-20');
  assert.equal(capturedConfig.params.check_out_date, '2026-12-22');
  assert.equal(capturedConfig.params.guest_count, 2);
  assert.equal(capturedConfig.params.keyword, 'Sài Gòn');
  assert.equal(capturedConfig.signal, controller.signal);

  assert.equal(result.data.length, 1);
  assert.equal(result.data[0].id, 5);
  assert.equal(result.meta.total, 1);
  assert.equal(result.meta.current_page, 1);
});
