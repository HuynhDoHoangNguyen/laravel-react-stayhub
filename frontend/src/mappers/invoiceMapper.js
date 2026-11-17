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
export function mapInvoiceDetailResponse(body) {
  requireValue(isObject(body));
  return { ...structuredClone(body), data: mapInvoice(body.data, true) };
}
