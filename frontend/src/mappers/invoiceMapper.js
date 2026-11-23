/** Invoice read/create contract projection. No HTTP, formatting or price calculation. */
function contractError() {
  return Object.assign(new Error('Dữ liệu hóa đơn từ máy chủ không hợp lệ. Vui lòng thử lại sau.'), {
    isApiError: true, status: 0, kind: 'contract', code: 'INVALID_INVOICE_RESPONSE',
    fields: {}, retryAfter: 0, isNetworkError: false, isCancelled: false,
    isTimeout: false, isNotFound: false, isConflict: false, isValidation: false,
  });
}

function requireValue(valid) {
  if (!valid) throw contractError();
}

const isObject = (value) => value !== null && typeof value === 'object' && !Array.isArray(value);
const isId = (value) => Number.isSafeInteger(value) && value > 0;
const isDecimal = (value) => typeof value === 'string' && /^\d+\.\d{2}$/.test(value);
const isNullableDate = (value) => value === null || (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}T/.test(value) && Number.isFinite(Date.parse(value)));

function mapInvoiceBookingSummary(data) {
  requireValue(isObject(data) && isId(data.booking_id));
  const context = { booking_id: data.booking_id };
  for (const field of ['booking_code', 'customer_name', 'property_name', 'room_name']) {
    requireValue(typeof data[field] === 'string' && data[field].trim().length > 0);
    context[field] = data[field];
  }
  for (const field of ['check_in_date', 'check_out_date']) {
    const value = data[field];
    requireValue(typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value) && Number.isFinite(Date.parse(value))
      && new Date(value).toISOString().slice(0, 10) === value);
    context[field] = value;
  }
  return context;
}

export function mapHostInvoiceBookingContext(body, bookingId) {
  const data = body?.data;
  const context = mapInvoiceBookingSummary(data);
  requireValue(context.booking_id === Number(bookingId) && typeof data.booking_status === 'string'
    && data.booking_status.trim().length > 0);
  const { can_create, invoice } = data;
  requireValue(invoice === null || (isObject(invoice) && isId(invoice.id)
    && typeof invoice.invoice_code === 'string' && invoice.invoice_code.trim().length > 0));
  requireValue(can_create === (data.booking_status === 'COMPLETED' && invoice === null));
  return { ...context, booking_status: data.booking_status, can_create,
    invoice: invoice === null ? null : { id: invoice.id, invoice_code: invoice.invoice_code } };
}

export function mapHostEligibleBookings(body) {
  requireValue(isObject(body) && Array.isArray(body.data) && isObject(body.meta) && isObject(body.links));
  const { current_page, last_page, per_page, total } = body.meta;
  requireValue(isId(current_page) && isId(last_page) && isId(per_page) && per_page <= 50
    && Number.isSafeInteger(total) && total >= 0 && body.data.length <= per_page);
  for (const field of ['first', 'last', 'prev', 'next']) {
    requireValue(body.links[field] === null || typeof body.links[field] === 'string');
  }
  const data = body.data.map(mapInvoiceBookingSummary);
  requireValue(new Set(data.map((row) => row.booking_id)).size === data.length);
  return { data, meta: structuredClone(body.meta), links: structuredClone(body.links) };
}

/** Host options use current names; never infer options from invoice snapshot rows. */
export function mapAdminInvoiceHostOptionsResponse(body) {
  requireValue(isObject(body) && Array.isArray(body.data) && isObject(body.meta) && isObject(body.links));
  const { current_page, last_page, per_page, total } = body.meta;
  requireValue(isId(current_page) && isId(last_page) && isId(per_page) && per_page <= 50 && Number.isSafeInteger(total) && total >= 0);
  for (const field of ['first', 'last', 'prev', 'next']) requireValue(body.links[field] === null || typeof body.links[field] === 'string');
  const data = body.data.map((row) => {
    requireValue(isObject(row) && isId(row.id) && typeof row.name === 'string');
    return { id: row.id, name: row.name };
  });
  requireValue(new Set(data.map(({ id }) => id)).size === data.length);
  return { data, meta: structuredClone(body.meta), links: structuredClone(body.links) };
}

export function mapAdminInvoicePropertyOptionsResponse(body, hostId = null) {
  // The shared envelope contract is identical; property rows have an additional required owner ID.
  const envelope = mapAdminInvoiceHostOptionsResponse(body);
  const data = body.data.map((row) => {
    requireValue(isId(row.host_id) && (hostId === null || row.host_id === hostId));
    return { id: row.id, name: row.name, host_id: row.host_id };
  });
  return { ...envelope, data };
}

function mapItem(item) {
  requireValue(isObject(item) && isId(item.id) && isId(item.invoice_id));
  for (const field of ['item_type', 'item_name']) requireValue(typeof item[field] === 'string');
  requireValue(item.description === null || typeof item.description === 'string');
  for (const field of ['quantity', 'unit_price', 'amount']) requireValue(isDecimal(item[field]));
  return {
    id: item.id, invoice_id: item.invoice_id, item_type: item.item_type,
    item_name: item.item_name, description: item.description,
    quantity: item.quantity, unit_price: item.unit_price, amount: item.amount,
  };
}

function mapInvoice(invoice, detail) {
  requireValue(isObject(invoice) && isId(invoice.id) && isId(invoice.booking_id));
  const result = { id: invoice.id };
  for (const field of ['invoice_code', 'customer_name', 'customer_email', 'customer_phone', 'property_name', 'room_name', 'payment_status']) {
    requireValue(typeof invoice[field] === 'string');
    result[field] = invoice[field];
  }
  requireValue(invoice.payment_status.length > 0);
  result.booking_id = invoice.booking_id;
  for (const field of ['subtotal', 'discount_amount', 'extra_fee', 'total_amount']) {
    requireValue(isDecimal(invoice[field]));
    result[field] = invoice[field];
  }
  for (const field of ['paid_at', 'created_at']) {
    requireValue(isNullableDate(invoice[field]));
    result[field] = invoice[field];
  }
  if (detail) {
    requireValue(Array.isArray(invoice.items));
    result.items = invoice.items.map(mapItem);
  }
  return result;
}

/** @returns {{data: Object[], meta: Object, links: Object}} Keeps Laravel snake_case. */
export function mapInvoiceListResponse(body) {
  requireValue(isObject(body) && Array.isArray(body.data) && isObject(body.meta) && isObject(body.links));
  const { current_page, last_page, per_page, total } = body.meta;
  requireValue(isId(current_page) && isId(last_page) && isId(per_page) && Number.isSafeInteger(total) && total >= 0);
  for (const field of ['first', 'last', 'prev', 'next']) requireValue(body.links[field] === null || typeof body.links[field] === 'string');
  // Preserve extra pagination metadata/summary without sharing mutable references.
  return { ...structuredClone(body), data: body.data.map((invoice) => mapInvoice(invoice, false)) };
}

/** @returns {{data: Object}} Detail requires explicit items, including a valid empty array. */
export function mapInvoiceDetailResponse(body, expectedBookingId = null) {
  requireValue(isObject(body));
  const data = mapInvoice(body.data, true);
  requireValue(expectedBookingId === null || data.booking_id === Number(expectedBookingId));
  return { ...structuredClone(body), data };
}

/** Admin projection is independent of the richer Host/Customer contract. */
export function mapAdminInvoiceListResponse(body) {
  requireValue(isObject(body) && Array.isArray(body.data) && isObject(body.meta) && isObject(body.links));
  const { current_page, last_page, per_page, total } = body.meta;
  requireValue(isId(current_page) && isId(last_page) && isId(per_page) && per_page <= 50 && Number.isSafeInteger(total) && total >= 0);
  for (const field of ['first', 'last', 'prev', 'next']) requireValue(body.links[field] === null || typeof body.links[field] === 'string');
  const data = body.data.map((row) => {
    requireValue(isObject(row) && isId(row.id) && isId(row.booking_id) && isDecimal(row.total_amount));
    const result = { id: row.id, booking_id: row.booking_id };
    if (Object.hasOwn(row, 'booking_code')) {
      requireValue(row.booking_code === null || (typeof row.booking_code === 'string' && row.booking_code.trim() !== ''));
      result.booking_code = row.booking_code;
    }
    for (const field of ['invoice_code', 'customer_name', 'property_name', 'room_name']) {
      requireValue(typeof row[field] === 'string');
      result[field] = row[field];
    }
    requireValue(['UNPAID', 'PAID', 'REFUNDED'].includes(row.payment_status));
    result.total_amount = row.total_amount;
    result.payment_status = row.payment_status;
    for (const field of ['paid_at', 'created_at']) {
      requireValue(isNullableDate(row[field]));
      result[field] = row[field];
    }
    return result;
  });
  return { data, meta: structuredClone(body.meta), links: structuredClone(body.links) };
}
