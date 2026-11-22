import api from '../api/axios.js';
import { createAdminPropertyApi } from '../api/adminPropertyApi.js';
import { localValidationError, normalizeApiError } from '../api/apiError.js';
import { mapAdminProperties, mapAdminPropertyDetail, propertyStatuses, propertyTypes } from '../mappers/adminPropertyMapper.js';

export function normalizePropertyId(value) {
  const id = typeof value === 'string' || typeof value === 'number' ? Number(value) : NaN;
  if (!Number.isSafeInteger(id) || id < 1 || String(id) !== String(value)) {
    throw localValidationError('Mã cơ sở không hợp lệ.', { property: ['Mã cơ sở phải là số nguyên dương dạng chuẩn, tối đa 9007199254740991.'] });
  }
  return id;
}

export function normalizePropertyQuery(input = {}) {
  const fields = Object.create(null);
  if (!input || typeof input !== 'object' || Array.isArray(input)) {
    throw localValidationError('Bộ lọc không hợp lệ.', { query: ['Bộ lọc không hợp lệ.'] });
  }
  for (const key of Object.keys(input)) {
    if (!['q', 'type', 'host_id', 'status', 'page', 'per_page'].includes(key)) fields[key] = ['Tham số truy vấn không được hỗ trợ.'];
  }
  const q = typeof input.q === 'string' ? input.q.trim() : input.q === undefined ? '' : null;
  if (q === null || [...q].length > 100) fields.q = ['Từ khóa phải là chuỗi tối đa 100 ký tự.'];
  const type = input.type === undefined ? '' : input.type;
  if (typeof type !== 'string' || (type !== '' && !Object.hasOwn(propertyTypes, type))) fields.type = ['Loại cơ sở không hợp lệ.'];
  const rawHostId = input.host_id === undefined ? '' : input.host_id;
  const host_id = rawHostId === '' ? '' : /^(?:[1-9][0-9]*)$/.test(String(rawHostId)) ? Number(rawHostId) : NaN;
  if (!['number', 'string'].includes(typeof rawHostId) || (host_id !== '' && (!Number.isSafeInteger(host_id) || host_id < 1))) {
    fields.host_id = ['Mã Host phải là số nguyên từ 1 đến 9007199254740991, không có số 0 đứng đầu.'];
  }
  const status = input.status === undefined ? '' : input.status;
  if (typeof status !== 'string' || (status !== '' && !Object.hasOwn(propertyStatuses, status))) fields.status = ['Trạng thái cơ sở không hợp lệ.'];
  const rawPage = input.page === undefined ? 1 : input.page;
  const page = /^(?:[1-9][0-9]*)$/.test(String(rawPage)) ? Number(rawPage) : NaN;
  if (!['number', 'string'].includes(typeof rawPage) || !Number.isSafeInteger(page) || page > 2147483647) {
    fields.page = ['Trang phải là số nguyên từ 1 đến 2147483647.'];
  }
  const rawSize = input.per_page === undefined ? 10 : input.per_page;
  const per_page = /^(10|20|50)$/.test(String(rawSize)) ? Number(rawSize) : NaN;
  if (!['number', 'string'].includes(typeof rawSize) || ![10, 20, 50].includes(per_page)) {
    fields.per_page = ['Số dòng mỗi trang phải là 10, 20 hoặc 50.'];
  }
  if (Object.keys(fields).length) throw localValidationError('Bộ lọc không hợp lệ. Vui lòng kiểm tra lại.', fields);
  return { q, type, host_id, status, page, per_page };
}

export function readPropertySearch(search) {
  const query = Object.create(null);
  for (const [key, value] of new URLSearchParams(search)) {
    if (Object.hasOwn(query, key)) throw localValidationError('Tham số truy vấn bị lặp.', { [key]: ['Tham số chỉ được xuất hiện một lần.'] });
    query[key] = value;
  }
  return normalizePropertyQuery(query);
}

export function propertySearchParams(input) {
  const query = normalizePropertyQuery(input);
  const params = new URLSearchParams();
  if (query.q) params.set('q', query.q);
  if (query.type) params.set('type', query.type);
  if (query.host_id !== '') params.set('host_id', String(query.host_id));
  if (query.status) params.set('status', query.status);
  if (query.page !== 1) params.set('page', String(query.page));
  if (query.per_page !== 10) params.set('per_page', String(query.per_page));
  return params;
}

export function createAdminPropertyService(propertyApi) {
  return {
    async getProperty(propertyId, options = {}) {
      const id = normalizePropertyId(propertyId);
      return mapAdminPropertyDetail(await propertyApi.getProperty(id, options), id);
    },
    async getProperties(input = {}, options = {}) {
      const query = normalizePropertyQuery(input);
      const params = { page: query.page, per_page: query.per_page };
      if (query.q) params.q = query.q;
      if (query.type) params.type = query.type;
      if (query.host_id !== '') params.host_id = query.host_id;
      if (query.status) params.status = query.status;
      return mapAdminProperties(await propertyApi.getProperties(params, options));
    },
  };
}

const service = createAdminPropertyService(createAdminPropertyApi(api));
export const getAdminProperties = (query, options) => service.getProperties(query, options);
export const getAdminProperty = (propertyId, options) => service.getProperty(propertyId, options);
export const parseAdminPropertyError = normalizeApiError;
