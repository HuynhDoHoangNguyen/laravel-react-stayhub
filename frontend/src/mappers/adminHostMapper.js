export const hostStatuses = { ACTIVE: 'Đang hoạt động', INACTIVE: 'Chưa hoạt động', BLOCKED: 'Đã khóa' };
const object = (value) => value !== null && typeof value === 'object' && !Array.isArray(value);
const integer = (value, minimum) => Number.isSafeInteger(value) && value >= minimum;

function invalid() {
  throw Object.assign(new Error('Dữ liệu Host từ máy chủ không hợp lệ. Vui lòng thử lại.'), {
    isApiError: true, status: 0, kind: 'contract', code: 'INVALID_ADMIN_HOST_RESPONSE', fields: {}, isCancelled: false,
  });
}

export function mapAdminHosts(body) {
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
    if (!object(row) || !integer(row.id, 1) || ids.has(row.id) || typeof row.name !== 'string'
      || !integer(row.properties_count, 0) || typeof row.email !== 'string' || (row.phone !== null && typeof row.phone !== 'string')
      || typeof row.status !== 'string' || !Object.hasOwn(hostStatuses, row.status)
      || (row.created_at !== null && (typeof row.created_at !== 'string'
        || !/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?Z$/.test(row.created_at)
        || !Number.isFinite(Date.parse(row.created_at))))) invalid();
    ids.add(row.id);
    return { id: row.id, name: row.name, email: row.email, phone: row.phone, properties_count: row.properties_count, status: row.status, created_at: row.created_at };
  });
  return { data, links, meta: { current_page, last_page, per_page, total } };
}


const nullableString = (value) => value === null || typeof value === 'string';
function timestamp(value) {
  if (value === null) return true;
  if (typeof value !== 'string' || !/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?Z$/.test(value)) return false;
  const date = new Date(value);
  return Number.isFinite(date.getTime()) && date.toISOString().slice(0, 19) === value.slice(0, 19);
}
function birthday(value) {
  if (value === null) return true;
  if (typeof value !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(value) || Number(value.slice(0, 4)) < 1) return false;
  const date = new Date(`${value}T00:00:00.000Z`);
  return Number.isFinite(date.getTime()) && date.toISOString().slice(0, 10) === value;
}

export function mapAdminHostDetail(body, expectedId) {
  const row = body?.data;
  if (!object(body) || !object(row) || !integer(row.id, 1) || row.id !== expectedId
    || typeof row.name !== 'string' || typeof row.email !== 'string'
    || !nullableString(row.phone) || !nullableString(row.address)
    || ![null, 'MALE', 'FEMALE', 'OTHER'].includes(row.gender) || !birthday(row.date_of_birth)
    || !integer(row.properties_count, 0) || typeof row.status !== 'string' || !Object.hasOwn(hostStatuses, row.status)
    || !timestamp(row.created_at) || !timestamp(row.updated_at)) invalid();

  return { id: row.id, name: row.name, email: row.email, phone: row.phone, properties_count: row.properties_count, address: row.address,
    gender: row.gender, date_of_birth: row.date_of_birth, status: row.status,
    created_at: row.created_at, updated_at: row.updated_at };
}
