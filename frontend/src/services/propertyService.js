import api from '../api/axios.js';
import { createPublicPropertyApi } from '../api/publicPropertyApi.js';
import { roomSearchApi as defaultRoomSearchApi } from '../api/roomSearchApi.js';
import { mapPropertyFromRooms, mapPropertyRooms } from '../mappers/propertyMapper.js';
import { mapPropertySearchResponse } from '../mappers/catalogMapper.js';
import { MOCK_PROPERTIES } from '../mocks/propertyMockData.js';
import { createCatalogService } from './catalog.js';

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
        const lastPage = meta?.last_page ? Number(meta.last_page) : 1;
        if (page >= lastPage || items.length < perPage) {
          break;
        }
        page += 1;
      }

      return {
        data: mapPropertyFromRooms(mapPropertyRooms(allRooms)),
        source: 'room-search',
      };
    },
  };
}

export const propertyService = createPropertyService(createPublicPropertyApi(api), {
  dev: import.meta.env?.DEV,
  enableMocks: import.meta.env?.VITE_ENABLE_PROPERTY_MOCKS,
  properties: MOCK_PROPERTIES,
});
