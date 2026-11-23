import api from '../api/axios.js';
import { createAdminAmenityApi, UncertainAmenityWrite } from '../api/adminAmenityApi.js';
import { localValidationError, normalizeApiError } from '../api/apiError.js';
import { mapAdminAmenity, mapAdminAmenities } from '../mappers/adminAmenityMapper.js';

const queryKeys = ['q', 'status', 'page', 'per_page'];
const valueKeys = ['name', 'icon', 'description', 'status'];
const plainRecord = (input) => input && typeof input === 'object' && !Array.isArray(input);
const length = (value) => [...value].length;

function rejectFields(fields) {
  if (Object.keys(fields).length) throw localValidationError('Vui lòng kiểm tra lại thông tin.', fields);
}

function normalizeId(value) {
  const id = typeof value === 'number' || typeof value === 'string' ? Number(value) : NaN;
  if (!Number.isSafeInteger(id) || id < 1 || String(id) !== String(value)) {
    throw localValidationError('Mã tiện nghi không hợp lệ.', { amenity: ['Mã tiện nghi phải là số nguyên dương.'] });
  }
  return id;
}

function positive(value, fallback) {
  if (value === undefined) return fallback;
  return (typeof value === 'string' || typeof value === 'number') && /^[1-9]\d*$/.test(String(value)) ? Number(value) : NaN;
}

function normalizeQuery(input) {
  const fields = Object.create(null);
  if (!plainRecord(input)) throw localValidationError('Bộ lọc không hợp lệ.');
  for (const key of Object.keys(input)) {
    if (!queryKeys.includes(key)) fields[key] = ['Tham số không được hỗ trợ.'];
  }

  const q = typeof input.q === 'string' ? input.q.trim() : input.q === undefined ? '' : null;
  const status = input.status === undefined ? '' : input.status;
  const page = positive(input.page, 1);
  const per_page = positive(input.per_page, 10);

  if (q === null || length(q) > 100) fields.q = ['Từ khóa tối đa 100 ký tự.'];
  if (!['', 'active', 'inactive'].includes(status)) fields.status = ['Trạng thái không hợp lệ.'];
  if (!Number.isSafeInteger(page) || page > 2147483647) fields.page = ['Số trang không hợp lệ.'];
  if (![10, 20, 50].includes(per_page)) fields.per_page = ['Số dòng mỗi trang phải là 10, 20 hoặc 50.'];
  rejectFields(fields);
  return { q, status, page, per_page };
}

export function readAmenitySearch(search) {
  const values = Object.create(null);
  for (const [key, value] of new URLSearchParams(search)) {
    if (Object.hasOwn(values, key)) throw localValidationError('Tham số truy vấn bị lặp.', { [key]: ['Chỉ dùng một giá trị.'] });
    values[key] = value;
  }
  return normalizeQuery(values);
}

export function amenitySearchParams(input) {
  const { q, status, page, per_page } = normalizeQuery(input);
  const params = new URLSearchParams();
  if (q) params.set('q', q);
  if (status) params.set('status', status);
  if (page !== 1) params.set('page', String(page));
  if (per_page !== 10) params.set('per_page', String(per_page));
  return params;
}

function normalizeWrite(input, update) {
  if (!plainRecord(input)) throw localValidationError('Dữ liệu tiện nghi không hợp lệ.');
  const fields = Object.create(null);
  const keys = update ? [...valueKeys, 'version'] : valueKeys;
  for (const key of Object.keys(input)) {
    if (!keys.includes(key)) fields[key] = ['Trường dữ liệu không được hỗ trợ.'];
  }
  const payload = {};
  const name = typeof input.name === 'string' ? input.name.trim() : '';
  if (!name || length(name) > 255) fields.name = ['Tên tiện nghi là bắt buộc và tối đa 255 ký tự.'];
  payload.name = name;
  for (const key of ['icon', 'description']) {
    const value = input[key];
    if (typeof value === 'string') payload[key] = value.trim() || null;
    else if (value === null || (!update && value === undefined)) payload[key] = null;
    else fields[key] = ['Trường này phải là văn bản hoặc để trống.'];
  }
  if (!update && payload.icon && (length(payload.icon) > 255 || !/^[\p{L}\p{M}\p{N}]+(?:-[\p{L}\p{M}\p{N}]+)*$/u.test(payload.icon))) {
    fields.icon = ['Mã icon tối đa 255 ký tự, gồm chữ, số và dấu gạch nối.'];
  }
  if (!update && payload.description && length(payload.description) > 2000) fields.description = ['Mô tả tối đa 2.000 ký tự.'];
  payload.status = !update && input.status === undefined ? true : input.status;
  if (typeof payload.status !== 'boolean') fields.status = ['Trạng thái không hợp lệ.'];
  if (update) {
    if (typeof input.version !== 'string' || !/^[a-f0-9]{64}$/.test(input.version)) fields.version = ['Hãy tải lại dữ liệu tiện nghi.'];
    payload.version = input.version;
  }
  rejectFields(fields);
  return payload;
}

function mapWrite(response, status, payload, id) {
  try {
    if (response.status !== status) throw new Error('Unexpected status');
    const row = mapAdminAmenity(response.data, id);
    if (row.status !== payload.status) throw new Error('Unexpected amenity status');
    return row;
  } catch (failure) {
    throw new UncertainAmenityWrite(failure);
  }
}

export function createAdminAmenityService(transport) {
  return {
    async list(input = {}, options) {
      const { q, status, page, per_page } = normalizeQuery(input);
      const params = { page, per_page };
      if (q) params.q = q;
      if (status) params.status = status;
      return mapAdminAmenities(await transport.list(params, options));
    },
    async get(value, options) {
      const id = normalizeId(value);
      return mapAdminAmenity(await transport.get(id, options), id);
    },
    async create(values, options) {
      const payload = normalizeWrite(values, false);
      return mapWrite(await transport.create(payload, options), 201, payload);
    },
    async update(value, values, options) {
      const id = normalizeId(value);
      const payload = normalizeWrite(values, true);
      return mapWrite(await transport.update(id, payload, options), 200, payload, id);
    },
  };
}

export const adminAmenityService = createAdminAmenityService(createAdminAmenityApi(api));

export function parseAdminAmenityError(failure) {
  if (failure instanceof UncertainAmenityWrite) {
    return { ...normalizeApiError(failure.cause), outcomeUnknown: true,
      message: 'Chưa xác nhận được kết quả lưu. Hãy kiểm tra dữ liệu trên máy chủ trước khi tiếp tục.' };
  }
  return normalizeApiError(failure);
}
