import api from '@/api/axios';
import { roomSearchApi } from '@/api/roomSearchApi';
import { mapFeaturedProperty, mapPropertyFromRooms, mapPropertyRooms, mapSearchRoom } from '@/mappers/propertyMapper';
import { MOCK_PROPERTIES } from '@/mocks/propertyMockData';
import { createCatalogService } from './catalog';

const catalog = createCatalogService(api, { dev: import.meta.env.DEV, enableMocks: import.meta.env.VITE_ENABLE_PROPERTY_MOCKS, properties: MOCK_PROPERTIES });

const MOCKS_ENABLED =
  import.meta.env.DEV && import.meta.env.VITE_ENABLE_PROPERTY_MOCKS === 'true';

function addDays(date, days) {
  const next = new Date(`${date}T12:00:00`);
  next.setDate(next.getDate() + days);
  return `${next.getFullYear()}-${String(next.getMonth() + 1).padStart(2, '0')}-${String(next.getDate()).padStart(2, '0')}`;
}

function defaultStay() {
  const today = new Date();
  const checkIn = addDays(
    `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`,
    1,
  );
  return { check_in_date: checkIn, check_out_date: addDays(checkIn, 2) };
}

function buildSearchParams({
  city = '',
  keyword = '',
  startDate,
  endDate,
  adults = 2,
  children = 0,
  minPrice,
  maxPrice,
  amenities = [],
  sortBy = 'PRICE_ASC',
  page = 1,
  perPage = 15,
  propertyId,
}) {
  const stay = defaultStay();
  const params = {
    check_in_date: startDate || stay.check_in_date,
    check_out_date: endDate || stay.check_out_date,
    guest_count: Math.max(1, Number(adults) + Number(children)),
    per_page: perPage,
    page,
  };

  if (city || keyword) params.keyword = [city, keyword].filter(Boolean).join(' ');
  if (propertyId) params.property_id = propertyId;
  if (minPrice !== undefined) params.min_price = minPrice;
  if (maxPrice !== undefined) params.max_price = maxPrice;
  if (amenities.length) params.amenity_ids = amenities;

  const sort = {
    PRICE_ASC: 'price_asc',
    PRICE_DESC: 'price_desc',
    NEWEST: 'newest',
  }[sortBy] || 'price_asc';
  params.sort = sort;

  return params;
}

export const propertyService = {
  async getFeaturedProperties() {
    if (MOCKS_ENABLED) {
      return { data: MOCK_PROPERTIES.slice(0, 6), source: 'mock' };
    }

    // The booking API exposes date based room search, not a property catalog endpoint.
    const stay = defaultStay();
    const response = await roomSearchApi.search(buildSearchParams({ ...stay, adults: 2 }));
    const featured = new Map();
    (response.data?.data || []).forEach((room) => {
      if (!featured.has(room.property_id)) {
        featured.set(room.property_id, mapFeaturedProperty(room));
      }
    });
    return {
      data: [...featured.values()].slice(0, 6),
      source: 'api',
    };
  },
  getProperties: catalog.getProperties,

  async searchProperties({
    city = '',
    startDate,
    endDate,
    adults = 2,
    children = 0,
    minPrice,
    maxPrice,
    amenities = [],
    sortBy = 'PRICE_ASC',
    keyword = '',
    page = 1,
  } = {}) {
    if (MOCKS_ENABLED) {
      let results = [...MOCK_PROPERTIES];
      const term = [city, keyword].filter(Boolean).join(' ').toLowerCase();
      if (term) {
        results = results.filter((item) =>
          `${item.name} ${item.city} ${item.description || ''}`.toLowerCase().includes(term),
        );
      }
      if (minPrice != null) results = results.filter((item) => item.price >= Number(minPrice));
      if (maxPrice != null) results = results.filter((item) => item.price <= Number(maxPrice));
      if (sortBy === 'PRICE_ASC') results.sort((a, b) => a.price - b.price);
      if (sortBy === 'PRICE_DESC') results.sort((a, b) => b.price - a.price);
      const perPage = 15;
      const start = (page - 1) * perPage;
      return {
        data: results.slice(start, start + perPage),
        total: results.length,
        meta: { current_page: page, last_page: Math.max(1, Math.ceil(results.length / perPage)), per_page: perPage, total: results.length },
        source: 'mock',
      };
    }

    const response = await roomSearchApi.search(buildSearchParams({
        city,
        keyword,
        startDate,
        endDate,
        adults,
        children,
        minPrice,
        maxPrice,
        amenities,
        sortBy,
        page,
      }));

    return {
      data: (response.data?.data || []).map(mapSearchRoom),
      meta: response.data?.meta,
      source: 'api',
    };
  },

  async getPropertyById(id, { startDate, endDate, adults = 2, children = 0 } = {}) {
    if (MOCKS_ENABLED) {
      const found = MOCK_PROPERTIES.find((item) => String(item.id) === String(id));
      return { data: found || null, source: 'mock' };
    }

    const response = await roomSearchApi.search(buildSearchParams({
        propertyId: id,
        startDate,
        endDate,
        adults,
        children,
        perPage: 50,
      }));
    const rooms = mapPropertyRooms(response.data?.data || []);
    return {
      data: mapPropertyFromRooms(rooms),
      source: 'api',
    };
  },
};
