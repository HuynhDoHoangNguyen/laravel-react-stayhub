export const propertyStatusLabels = Object.freeze({
  ACTIVE: 'Đang hoạt động',
  INACTIVE: 'Ngừng hoạt động',
  BLOCKED: 'Đã khóa',
  PENDING: 'Chờ duyệt',
});

export const propertyTypeLabels = Object.freeze({
  HOTEL: 'Khách sạn',
  HOMESTAY: 'Homestay',
});

const record = (value) => value !== null && typeof value === 'object' && !Array.isArray(value);
const integer = (value) => Number.isSafeInteger(value) && value >= 0;
const positive = (value) => integer(value) && value > 0;
const string = (value) => typeof value === 'string';

function invalidPage() {
  throw Object.assign(new Error('Dữ liệu cơ sở lưu trú từ máy chủ không hợp lệ.'), {
    isApiError: true, kind: 'contract', status: 0, code: 'INVALID_ADMIN_PROPERTY_RESPONSE',
    fields: {}, isCancelled: false,
  });
}

export function mapAdminPropertyPage(body) {
  if (!record(body) || !Array.isArray(body.data) || !record(body.links) || !record(body.meta)) invalidPage();
  const { links, meta } = body;
  if (!['first', 'last', 'prev', 'next'].every((key) => links[key] === null || string(links[key]))
    || !positive(meta.current_page) || !positive(meta.last_page) || !positive(meta.per_page)
    || meta.per_page > 50 || !integer(meta.total) || body.data.length > meta.per_page
    || !(meta.from === null || positive(meta.from)) || !(meta.to === null || positive(meta.to))) invalidPage();

  const data = body.data.map((row) => {
    if (!record(row) || !positive(row.id) || !string(row.name) || !string(row.address)
      || !string(row.type) || !Object.hasOwn(propertyTypeLabels, row.type)
      || !string(row.status) || !Object.hasOwn(propertyStatusLabels, row.status)
      || !(row.host_name === null || string(row.host_name))) invalidPage();
    const { id, name, address, type, status, host_name } = row;
    return { id, name, address, type, status, host_name };
  });

  return { data, links, meta };
}
