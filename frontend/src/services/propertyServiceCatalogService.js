import { propertyServiceCatalogApi } from '../api/propertyServiceCatalogApi.js';
import {
  mapHostPropertiesResponse,
  mapPropertyServiceListResponse,
  mapPropertyServiceResponse,
} from '../mappers/propertyServiceCatalogMapper.js';

export const propertyServiceCatalogService = {
  async getHostProperties(options = {}) {
    return mapHostPropertiesResponse(await propertyServiceCatalogApi.getHostProperties(options));
  },

  async list({ propertyId, page = 1, perPage = 10, status = '', keyword = '' } = {}, options = {}) {
    const params = { property_id: propertyId, page, per_page: perPage };
    if (status !== '') params.status = status;
    if (keyword.trim()) params.keyword = keyword.trim();
    return mapPropertyServiceListResponse(await propertyServiceCatalogApi.list(params, options));
  },

  async listActiveForProperty(propertyId, options = {}) {
    const allServices = [];
    let page = 1;
    const perPage = 50;
    const maxPages = 5; // R08: Bounded pagination up to 250 services
    let serverTotal = null;
    let hasMore = false;
    let isTruncated = false;

    while (page <= maxPages) {
      const res = await propertyServiceCatalogApi.list({
        property_id: propertyId,
        status: true,
        per_page: perPage,
        page,
      }, options);

      const mapped = mapPropertyServiceListResponse(res);
      allServices.push(...mapped.data);

      if (serverTotal === null && mapped.meta?.total !== undefined) {
        serverTotal = Number(mapped.meta.total);
      }

      const lastPage = mapped.meta?.last_page ?? 1;
      if (page >= lastPage || mapped.data.length < perPage) {
        break;
      }
      if (page === maxPages && page < lastPage) {
        hasMore = true;
        isTruncated = true;
        break;
      }
      page += 1;
    }

    return {
      success: true,
      data: allServices,
      total: serverTotal !== null ? serverTotal : allServices.length,
      hasMore,
      isTruncated,
    };
  },

  async create(payload) {
    return mapPropertyServiceResponse(await propertyServiceCatalogApi.create(payload));
  },

  async update(serviceId, payload) {
    return mapPropertyServiceResponse(await propertyServiceCatalogApi.update(serviceId, payload));
  },

  async setStatus(serviceId, status) {
    return mapPropertyServiceResponse(await propertyServiceCatalogApi.setStatus(serviceId, status));
  },
};
