import api from '../api/axios.js';
import { createPublicPropertyApi } from '../api/publicPropertyApi.js';
import { roomSearchApi as defaultRoomSearchApi } from '../api/roomSearchApi.js';
import { mapPropertyFromRooms, mapPropertyRooms } from '../mappers/propertyMapper.js';
import { mapCatalogListResponse, mapPropertySearchResponse } from '../mappers/catalogMapper.js';
import { MOCK_PROPERTIES } from '../mocks/propertyMockData.js';
import { catalogError, createCatalogService } from './catalog.js';

/**
 * Service to fetch and filter property data for public catalog, landing page, and search results.
 * Strictly enforces mock safety rules:
 * - Mock data is ONLY used when DEV === true AND VITE_ENABLE_PROPERTY_MOCKS === 'true'.
 * - When mock is off or in production, attempts real API call.
 * - Does NOT silently fallback to mock if real API fails.
 */
export function createPropertyService(propertyApi, { dev = false, enableMocks = 'false', properties = [], roomSearchApi = defaultRoomSearchApi } = {}) {
  const catalog = createCatalogService(propertyApi, { dev, enableMocks, properties });
  const isMockEnabled = dev === true && enableMocks === 'true';
  return {
    getFeaturedProperties: catalog.getFeaturedProperties,
    getProperties: catalog.getProperties,

    async searchProperties({
      city = '',
      minPrice,
      maxPrice,
      minRating,
      sortBy = 'PRICE_ASC',
      amenities = [],
      propertyTypes = [],
      keyword = '',
    } = {}, options = {}) {
      if (isMockEnabled) {
        let results = [...properties];

        // Filter by city
        if (city && city.trim()) {
          const cLower = city.trim().toLowerCase();
          results = results.filter((p) => p.city.toLowerCase().includes(cLower));
        }

        // Filter by keyword (q)
        if (keyword && keyword.trim()) {
          const kLower = keyword.trim().toLowerCase();
          results = results.filter(
            (p) =>
              p.name.toLowerCase().includes(kLower) ||
              p.city.toLowerCase().includes(kLower) ||
              (p.description && p.description.toLowerCase().includes(kLower))
          );
        }

        // Filter by min/max price
        if (minPrice != null && !isNaN(minPrice)) {
          results = results.filter((p) => p.price >= Number(minPrice));
        }
        if (maxPrice != null && !isNaN(maxPrice)) {
          results = results.filter((p) => p.price <= Number(maxPrice));
        }

        // Filter by minRating
        if (minRating != null && !isNaN(minRating)) {
          results = results.filter((p) => p.averageRating >= Number(minRating));
        }

        // Filter by amenities
        if (amenities && amenities.length > 0) {
          results = results.filter((p) => {
            const propAmenities = p.amenityIds || [];
            return amenities.every((aId) => propAmenities.includes(aId));
          });
        }

        // Filter by property types
        if (propertyTypes && propertyTypes.length > 0) {
          results = results.filter((p) => propertyTypes.includes(p.propertyType));
        }

        // Sorting
        if (sortBy === 'PRICE_ASC') {
          results.sort((a, b) => a.price - b.price);
        } else if (sortBy === 'PRICE_DESC') {
          results.sort((a, b) => b.price - a.price);
        } else if (sortBy === 'RATING_DESC') {
          results.sort((a, b) => b.averageRating - a.averageRating);
        }

        return {
          data: results,
          total: results.length,
          source: 'mock',
        };
      }

      try {
        const body = await propertyApi.searchProperties({
          city,
          minPrice,
          maxPrice,
          minRating,
          sortBy,
          amenities: amenities.join(','),
          propertyTypes: propertyTypes.join(','),
          keyword,
        }, options);
        return { ...mapPropertySearchResponse(body), source: 'api' };
      } catch (error) {
        if (error?.response?.status === 404) {
          return {
            data: [],
            total: 0,
            source: 'api',
            unavailable: true,
          };
        }
        throw error;
      }
    },

    getPropertyById: catalog.getPropertyById,

    /**
     * Paginated public properties filtered by keyword and/or guest capacity on the backend BEFORE pagination.
     */
    async searchPaginatedProperties({ page = 1, per_page = 12, keyword = '', guest_count = null } = {}, options = {}) {
      const trimmedKeyword = typeof keyword === 'string' ? keyword.trim() : '';
      const parsedGuests = guest_count != null && Number.isInteger(Number(guest_count)) && Number(guest_count) >= 1
        ? Number(guest_count)
        : null;

      if (!trimmedKeyword && parsedGuests === null) {
        return catalog.getProperties({ page, per_page }, options);
      }

      if (isMockEnabled) {
        let filtered = [...properties];
        if (trimmedKeyword) {
          const lower = trimmedKeyword.toLowerCase();
          filtered = filtered.filter((p) =>
            (p.name ?? '').toLowerCase().includes(lower) ||
            (p.address ?? p.city ?? '').toLowerCase().includes(lower)
          );
        }
        if (parsedGuests !== null) {
          filtered = filtered.filter((p) =>
            !Array.isArray(p.rooms) || p.rooms.some((r) => Number(r.capacity ?? 0) >= parsedGuests)
          );
        }
        const total = filtered.length;
        const lastPage = Math.max(1, Math.ceil(total / per_page));
        const sliced = filtered.slice((page - 1) * per_page, page * per_page);
        return {
          data: sliced,
          source: 'mock',
          meta: {
            current_page: page,
            per_page,
            total,
            last_page: lastPage,
          },
          links: {},
        };
      }

      try {
        const query = { page, per_page };
        if (trimmedKeyword) query.keyword = trimmedKeyword;
        if (parsedGuests !== null) query.guest_count = parsedGuests;
        const response = await api.get('/api/public/properties-search', {
          timeout: 10000,
          ...options,
          params: query,
        });
        return { ...mapCatalogListResponse(response.data), source: 'api' };
      } catch (error) {
        throw catalogError(error);
      }
    },

    /**
     * TV2 checkout source: rooms that are bookable for the requested stay, from Room Search.
     * Kept separate from getPropertyById (Catalog) so the Catalog delegate stays untouched.
     */
    async getBookableProperty(id, { startDate, endDate, adults = 2, children = 0 } = {}, options = {}) {
      if (isMockEnabled) {
        const found = properties.find((item) => String(item.id) === String(id));
        return { data: found || null, source: 'mock' };
      }

      const allRooms = [];
      let page = 1;
      const perPage = 50;
      const maxPages = 10; // R08: Bounded pagination up to 500 rooms
      let serverTotal = null;
      let hasMore = false;
      let isTruncated = false;

      while (page <= maxPages) {
        const response = await roomSearchApi.search({
          check_in_date: startDate,
          check_out_date: endDate,
          guest_count: Math.max(1, Number(adults) + Number(children)),
          per_page: perPage,
          page,
          property_id: id,
          sort: 'price_asc',
        }, options);

        const items = response.data?.data;
        if (!Array.isArray(items)) {
          throw new Error('Dữ liệu danh sách phòng từ Room Search không đúng định dạng.');
        }
        allRooms.push(...items);

        const meta = response.data?.meta;
        if (serverTotal === null && meta?.total !== undefined) {
          serverTotal = Number(meta.total);
        }

        const lastPage = meta?.last_page ? Number(meta.last_page) : 1;
        if (page >= lastPage || items.length < perPage) {
          break;
        }
        if (page === maxPages && page < lastPage) {
          hasMore = true;
          isTruncated = true;
          break;
        }
        page += 1;
      }

      const propertyData = mapPropertyFromRooms(mapPropertyRooms(allRooms));
      if (propertyData) {
        propertyData.hasMoreRooms = hasMore;
        propertyData.totalRooms = serverTotal !== null ? serverTotal : allRooms.length;
      }

      const result = {
        data: propertyData,
        source: 'room-search',
      };
      if (serverTotal !== null || hasMore || isTruncated) {
        result.total = serverTotal !== null ? serverTotal : allRooms.length;
        result.hasMore = hasMore;
        result.isTruncated = isTruncated;
      }
      return result;
    },
  };
}

export const propertyService = createPropertyService(createPublicPropertyApi(api), {
  dev: import.meta.env?.DEV,
  enableMocks: import.meta.env?.VITE_ENABLE_PROPERTY_MOCKS,
  properties: MOCK_PROPERTIES,
});
