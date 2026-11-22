export const bookingStatusLabels = Object.freeze({
  PENDING: 'Chờ xác nhận',
  CONFIRMED: 'Đã xác nhận',
  CHECKED_IN: 'Đang lưu trú',
  COMPLETED: 'Đã hoàn thành lưu trú',
  CANCELLED: 'Đã hủy',
  REJECTED: 'Bị từ chối',
});

const record = (value) => value !== null && typeof value === 'object' && !Array.isArray(value);
const integer = (value) => Number.isSafeInteger(value) && value >= 0;
const positive = (value) => integer(value) && value > 0;
const string = (value) => typeof value === 'string';
const nullable = (check) => (value) => value === null || check(value);
const decimal = (value) => string(value) && /^(?:0|[1-9]\d{0,9})\.\d{2}(?![\s\S])/.test(value);
const date = (value) => string(value) && /^[1-9]\d{3}-\d{2}-\d{2}$/.test(value) && value.length === 10
  && Number.isFinite(Date.parse(value)) && new Date(value).toISOString().slice(0, 10) === value;
const timestamp = (value) => string(value) && /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?Z(?![\s\S])/.test(value)
  && date(value.slice(0, 10)) && Number.isFinite(Date.parse(value))
  && new Date(value).toISOString().slice(0, 19) === value.slice(0, 19);

const fields = {
  id: positive, booking_code: string, customer_id: positive, customer_name: nullable(string),
  room_id: positive, room_name: nullable(string), room_number: nullable(string),
  property_id: nullable(positive), property_name: nullable(string), host_id: nullable(positive), host_name: nullable(string),
  check_in_date: date, check_out_date: date, guest_count: integer, number_of_nights: integer,
  price_per_night: decimal, room_total: decimal,
  status: (value) => string(value) && Object.hasOwn(bookingStatusLabels, value),
  created_at: nullable(timestamp),
};

function invalidPage() {
  throw Object.assign(new Error('Dữ liệu đặt phòng từ máy chủ không hợp lệ.'), {
    isApiError: true, kind: 'contract', status: 0, code: 'INVALID_ADMIN_BOOKING_RESPONSE',
    fields: {}, isCancelled: false,
  });
}

function mapPage(body, mapRow) {
  if (!record(body) || !Array.isArray(body.data) || !record(body.links) || !record(body.meta)) invalidPage();
  const { links, meta } = body;
  if (!['first', 'last', 'prev', 'next'].every((key) => nullable(string)(links[key]))
      || !positive(meta.current_page) || !positive(meta.last_page) || !positive(meta.per_page)
      || meta.per_page > 50 || !integer(meta.total) || !nullable(positive)(meta.from) || !nullable(positive)(meta.to)
      || body.data.length > meta.per_page) invalidPage();

  return { data: body.data.map(mapRow), links: { ...links }, meta: { ...meta } };
}

export function mapAdminBookingPage(body) {
  return mapPage(body, (row) => {
    if (!record(row) || !Object.entries(fields).every(([key, check]) => Object.hasOwn(row, key) && check(row[key]))) invalidPage();
    return Object.fromEntries(Object.keys(fields).map((key) => [key, row[key]]));
  });
}

export function mapAdminBookingOptionsPage(body, type) {
  if (!['hosts', 'properties', 'customers'].includes(type)) invalidPage();
  return mapPage(body, (row) => {
    if (!record(row) || !positive(row.id) || !string(row.name)) invalidPage();
    const option = { id: row.id, name: row.name };
    if (type === 'properties') {
      if (!positive(row.host_id)) invalidPage();
      option.host_id = row.host_id;
    }
    return option;
  });
}
