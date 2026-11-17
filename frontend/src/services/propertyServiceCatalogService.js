import { propertyServiceCatalogApi } from '@/api/propertyServiceCatalogApi';
import {
  mapHostPropertiesResponse,
  mapPropertyServiceListResponse,
  mapPropertyServiceResponse,
} from '@/mappers/propertyServiceCatalogMapper';

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
    const params = { property_id: propertyId, status: true, per_page: 50 };
    return mapPropertyServiceListResponse(await propertyServiceCatalogApi.list(params, options));
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
