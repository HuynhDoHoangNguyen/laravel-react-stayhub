import api from '../api/axios.js';
import { createPublicPropertyApi } from '../api/publicPropertyApi.js';
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
export function createPropertyService(propertyApi, { dev = false, enableMocks = 'false', properties = [] } = {}) {
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
  };
}

export const propertyService = createPropertyService(createPublicPropertyApi(api), {
  dev: import.meta.env?.DEV,
  enableMocks: import.meta.env?.VITE_ENABLE_PROPERTY_MOCKS,
  properties: MOCK_PROPERTIES,
});
