import api from '../api/axios.js';
import { createAdminHostApi } from '../api/adminHostApi.js';
import { localValidationError, normalizeApiError } from '../api/apiError.js';
import { hostStatuses, mapAdminHosts, mapAdminHostDetail } from '../mappers/adminHostMapper.js';

export function normalizeHostId(value) {
  const id = typeof value === 'string' || typeof value === 'number' ? Number(value) : NaN;
  if (!Number.isSafeInteger(id) || id < 1 || String(id) !== String(value)) {
    throw localValidationError('Mã Host không hợp lệ.', { host: ['Mã Host phải là số nguyên dương hợp lệ.'] });
  }
  return id;
}

export function normalizeHostQuery(input = {}) {
  const fields = Object.create(null);
  if (!input || typeof input !== 'object' || Array.isArray(input)) {
    throw localValidationError('Bộ lọc không hợp lệ.', { query: ['Bộ lọc không hợp lệ.'] });
  }
  for (const key of Object.keys(input)) {
    if (!['q', 'status', 'page', 'per_page'].includes(key)) fields[key] = ['Tham số truy vấn không được hỗ trợ.'];
  }
  const q = typeof input.q === 'string' ? input.q.trim() : input.q === undefined ? '' : null;
  if (q === null || [...q].length > 100) fields.q = ['Từ khóa phải là chuỗi tối đa 100 ký tự.'];
  const status = input.status === undefined ? '' : input.status;
  if (typeof status !== 'string' || (status !== '' && !Object.hasOwn(hostStatuses, status))) fields.status = ['Trạng thái không hợp lệ.'];
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
  return { q, status, page, per_page };
}

export function readHostSearch(search) {
  const query = {};
  for (const [key, value] of new URLSearchParams(search)) {
    if (Object.hasOwn(query, key)) throw localValidationError('Tham số truy vấn bị lặp.', { [key]: ['Tham số chỉ được xuất hiện một lần.'] });
    Object.defineProperty(query, key, { value, enumerable: true });
  }
  return normalizeHostQuery(query);
}

export function hostSearchParams(input) {
  const query = normalizeHostQuery(input);
  const params = new URLSearchParams();
  if (query.q) params.set('q', query.q);
  if (query.status) params.set('status', query.status);
  if (query.page !== 1) params.set('page', String(query.page));
  if (query.per_page !== 10) params.set('per_page', String(query.per_page));
  return params;
}

export function createAdminHostService(hostApi) {
  return {
    async getHost(hostId, options = {}) {
      const id = normalizeHostId(hostId);
      return mapAdminHostDetail(await hostApi.getHost(id, options), id);
    },
    async getHosts(input = {}, options = {}) {
      const query = normalizeHostQuery(input);
      const params = { page: query.page, per_page: query.per_page };
      if (query.q) params.q = query.q;
      if (query.status) params.status = query.status;
      return mapAdminHosts(await hostApi.getHosts(params, options));
    },
  };
}

const service = createAdminHostService(createAdminHostApi(api));
export const getAdminHosts = (query, options) => service.getHosts(query, options);
export const getAdminHost = (hostId, options) => service.getHost(hostId, options);
export const parseAdminHostError = normalizeApiError;
