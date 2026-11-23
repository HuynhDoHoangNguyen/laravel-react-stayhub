export const propertyStatuses = {
  ACTIVE: 'Đang hoạt động', INACTIVE: 'Ngừng hoạt động', BLOCKED: 'Đã khóa', PENDING: 'Chờ duyệt',
};
export const propertyTypes = { HOTEL: 'Khách sạn', HOMESTAY: 'Homestay' };
const object = (value) => value !== null && typeof value === 'object' && !Array.isArray(value);
const integer = (value, minimum) => Number.isSafeInteger(value) && value >= minimum;

function invalid() {
  throw Object.assign(new Error('Dữ liệu cơ sở từ máy chủ không hợp lệ. Vui lòng thử lại.'), {
    isApiError: true, status: 0, kind: 'contract', code: 'INVALID_ADMIN_PROPERTY_RESPONSE', fields: {}, isCancelled: false,
  });
}

function timestamp(value) {
  if (value === null) return true;
  if (typeof value !== 'string' || !/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?Z$/.test(value)) return false;
  const date = new Date(value);
  return Number.isFinite(date.getTime()) && date.toISOString().slice(0, 19) === value.slice(0, 19);
}

function mapProperty(row) {
  if (!object(row) || !integer(row.id, 1) || typeof row.name !== 'string'
    || typeof row.address !== 'string' || typeof row.type !== 'string' || !Object.hasOwn(propertyTypes, row.type)
    || typeof row.status !== 'string' || !Object.hasOwn(propertyStatuses, row.status)
    || !integer(row.rooms_count, 0) || !timestamp(row.created_at)
    || !object(row.host) || !integer(row.host.id, 1) || typeof row.host.name !== 'string' || typeof row.host.email !== 'string') invalid();
  return { id: row.id, name: row.name, type: row.type, address: row.address, status: row.status,
    rooms_count: row.rooms_count, created_at: row.created_at,
    host: { id: row.host.id, name: row.host.name, email: row.host.email } };
}

export function mapAdminProperties(body) {
  if (!object(body) || !Array.isArray(body.data) || !object(body.meta) || !object(body.links)) invalid();
  const { current_page, last_page, per_page, total } = body.meta;
  if (!integer(current_page, 1) || !integer(last_page, 1) || ![10, 20, 50].includes(per_page)
    || !integer(total, 0) || last_page !== Math.max(1, Math.ceil(total / per_page))) invalid();
  const expectedRows = Math.max(0, Math.min(per_page, total - (current_page - 1) * per_page));
  if (body.data.length !== expectedRows) invalid();
  const links = {};
  for (const key of ['first', 'last', 'prev', 'next']) {
    const value = body.links[key];
    if (value !== null && typeof value !== 'string') invalid();
    links[key] = value;
  }
  const ids = new Set();
  const data = body.data.map((row) => {
    const property = mapProperty(row);
    if (ids.has(row.id)) invalid();
    ids.add(row.id);
    return property;
  });
  return { data, links, meta: { current_page, last_page, per_page, total } };
}

export function mapAdminPropertyDetail(body, expectedId) {
  const row = body?.data;
  const property = mapProperty(row);
  const nullableString = (value) => value === null || typeof value === 'string';
  const time = (value) => typeof value === 'string' && /^(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d$/.test(value);
  if (!integer(expectedId, 1) || property.id !== expectedId || !nullableString(row.description) || !nullableString(row.phone)
    || !time(row.check_in_time) || !time(row.check_out_time) || !integer(row.room_types_count, 0) || !timestamp(row.updated_at)) invalid();
  return { ...property, description: row.description, phone: row.phone, check_in_time: row.check_in_time,
    check_out_time: row.check_out_time, room_types_count: row.room_types_count, updated_at: row.updated_at };
}
