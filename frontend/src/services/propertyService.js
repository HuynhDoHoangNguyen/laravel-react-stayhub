import api from '@/api/axios';
import { MOCK_PROPERTIES } from '@/mocks/propertyMockData';
import { createCatalogService } from './catalog';

const catalog = createCatalogService(api, { dev: import.meta.env.DEV, enableMocks: import.meta.env.VITE_ENABLE_PROPERTY_MOCKS, properties: MOCK_PROPERTIES });

/**
 * Service to fetch and filter property data for public catalog, landing page, and search results.
 * Strictly enforces mock safety rules:
 * - Mock data is ONLY used when DEV === true AND VITE_ENABLE_PROPERTY_MOCKS === 'true'.
 * - When mock is off or in production, attempts real API call.
 * - Does NOT silently fallback to mock if real API fails.
 */
export const propertyService = {
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
  } = {}) {
    const isMockEnabled =
      import.meta.env.DEV && import.meta.env.VITE_ENABLE_PROPERTY_MOCKS === 'true';

    if (isMockEnabled) {
      let results = [...MOCK_PROPERTIES];

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
      const response = await api.get('/api/public/properties/search', {
        params: {
          city,
          minPrice,
          maxPrice,
          minRating,
          sortBy,
          amenities: amenities.join(','),
          propertyTypes: propertyTypes.join(','),
          keyword,
        },
      });

      const list = response.data?.data || response.data || [];
      return {
        data: list,
        total: list.length,
        source: 'api',
      };
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
};
