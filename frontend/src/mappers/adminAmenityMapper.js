const object = (value) => value !== null && typeof value === 'object' && !Array.isArray(value);
const integer = (value, minimum) => Number.isSafeInteger(value) && value >= minimum;
const textOrNull = (value) => value === null || typeof value === 'string';

function invalid() {
  throw Object.assign(new Error('Dữ liệu tiện nghi từ máy chủ không hợp lệ.'), {
    isApiError: true, status: 0, kind: 'contract', code: 'INVALID_ADMIN_AMENITY_RESPONSE', fields: {},
  });
}

function timestamp(value) {
  if (value === null) return true;
  if (typeof value !== 'string' || !/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?Z$/.test(value)) return false;
  const date = new Date(value);
  return Number.isFinite(+date) && date.toISOString().slice(0, 19) === value.slice(0, 19);
}

function mapAmenity(value) {
  if (!object(value) || !integer(value.id, 1) || typeof value.name !== 'string' || !value.name.trim()
    || !textOrNull(value.icon) || !textOrNull(value.description) || typeof value.status !== 'boolean'
    || !integer(value.rooms_count, 0) || !timestamp(value.created_at) || !timestamp(value.updated_at)
    || typeof value.version !== 'string' || !/^[a-f0-9]{64}$/.test(value.version)) invalid();

  return {
    id: value.id, name: value.name, icon: value.icon, description: value.description, status: value.status,
    rooms_count: value.rooms_count, created_at: value.created_at, updated_at: value.updated_at, version: value.version,
  };
}

export function mapAdminAmenity(body, expectedId) {
  if (!object(body)) invalid();
  const amenity = mapAmenity(body.data);
  if (expectedId !== undefined && amenity.id !== expectedId) invalid();
  return amenity;
}

export function mapAdminAmenities(body) {
  if (!object(body) || !Array.isArray(body.data) || !object(body.meta) || !object(body.links)) invalid();
  const { current_page, last_page, per_page, total } = body.meta;
  if (!integer(current_page, 1) || !integer(last_page, 1) || ![10, 20, 50].includes(per_page)
    || !integer(total, 0) || last_page !== Math.max(1, Math.ceil(total / per_page))) invalid();
  if (body.data.length !== Math.max(0, Math.min(per_page, total - (current_page - 1) * per_page))) invalid();
  const data = body.data.map(mapAmenity);
  if (new Set(data.map(({ id }) => id)).size !== data.length) invalid();

  const links = {};
  for (const key of ['first', 'last', 'prev', 'next']) {
    const value = body.links[key];
    if (!textOrNull(value)) invalid();
    links[key] = value;
  }
  return { data, links, meta: { current_page, last_page, per_page, total } };
}
