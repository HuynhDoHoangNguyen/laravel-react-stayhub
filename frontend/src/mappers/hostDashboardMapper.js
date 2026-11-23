const object = (value) => value !== null && typeof value === 'object' && !Array.isArray(value);
const fullMatch = (pattern, value) => typeof value === 'string' && value.match(pattern)?.[0] === value;
const decimal = (value) => fullMatch(/^(0|[1-9][0-9]*)\.[0-9]{2}/, value);
const id = (value) => fullMatch(/^[1-9][0-9]*/, value);
const count = (value) => Number.isSafeInteger(value) && value >= 0;
const month = (value) => fullMatch(/^(?!0000)[0-9]{4}-(0[1-9]|1[0-2])/, value);
const monthIndex = (value) => Number(value.slice(0, 4)) * 12 + Number(value.slice(5)) - 1;
const statuses = new Set(['PENDING', 'CONFIRMED', 'CHECKED_IN', 'COMPLETED', 'CANCELLED', 'REJECTED']);
const localTime = new Intl.DateTimeFormat('en-US', { timeZone: 'Asia/Ho_Chi_Minh', year: 'numeric',
  month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23', era: 'short' });

function invalid() {
  throw Object.assign(new Error('Dữ liệu tổng quan Host không hợp lệ. Vui lòng thử lại.'), {
    isApiError: true, kind: 'contract', code: 'INVALID_HOST_DASHBOARD_RESPONSE', status: 0,
    fields: {}, isCancelled: false,
  });
}

/** Parse UTC without Date.parse's year-0001/10000 and invalid-calendar normalization. */
function instant(value) {
  if (typeof value !== 'string') invalid();
  const parts = value.match(/^([0-9]{4}|10000)-([0-9]{2})-([0-9]{2})T([0-9]{2}):([0-9]{2}):([0-9]{2})(?:\.([0-9]{1,6}))?Z/);
  if (!parts || parts[0] !== value) invalid();
  const [, year, m, day, hour, minute, second, fraction = ''] = parts;
  const date = new Date(0);
  date.setUTCFullYear(Number(year), Number(m) - 1, Number(day));
  date.setUTCHours(Number(hour), Number(minute), Number(second), 0);
  if (date.getUTCFullYear() !== Number(year) || date.getUTCMonth() !== Number(m) - 1
    || date.getUTCDate() !== Number(day) || date.getUTCHours() !== Number(hour)
    || date.getUTCMinutes() !== Number(minute) || date.getUTCSeconds() !== Number(second)) invalid();
  return BigInt(date.getTime()) * 1000n + BigInt(fraction.padEnd(6, '0'));
}

function calendarDate(value) {
  if (!fullMatch(/^(?!0000)[0-9]{4}-[0-9]{2}-[0-9]{2}/, value)) invalid();
  instant(`${value}T00:00:00Z`);
  return value;
}

function monthBoundary(value, expectedIndex) {
  const micros = instant(value);
  const parts = Object.fromEntries(localTime.formatToParts(new Date(Number(micros / 1000n)))
    .filter((part) => part.type !== 'literal').map((part) => [part.type, part.value]));
  if (micros % 1000000n !== 0n || parts.era !== 'AD' || Number(parts.year) * 12 + Number(parts.month) - 1 !== expectedIndex
    || parts.day !== '01' || parts.hour !== '00' || parts.minute !== '00' || parts.second !== '00') invalid();
  return micros;
}

export function mapHostDashboardResponse(body) {
  const d = body?.data;
  if (!object(body) || !object(d) || !object(d.scope) || !object(d.snapshot) || !object(d.summary)
    || !object(d.period) || !object(d.warnings) || d.report_timezone !== 'Asia/Ho_Chi_Minh') invalid();
  const generated = instant(d.generated_at);
  const p = d.period;
  if (!month(p.from_month) || !month(p.to_month)) invalid();
  const first = monthIndex(p.from_month), last = monthIndex(p.to_month);
  if (last < first || last - first > 11 || monthBoundary(p.start_at, first) >= monthBoundary(p.end_at_exclusive, last + 1)) invalid();
  if (!count(d.scope.properties_count) || !decimal(d.summary.revenue_total)
    || !count(d.warnings.paid_invoices_missing_paid_at_scope_count)) invalid();
  const snapshot = {};
  for (const field of ['rooms_count', 'pending_bookings_count', 'in_progress_maintenances_count']) {
    if (!count(d.snapshot[field])) invalid();
    snapshot[field] = d.snapshot[field];
  }
  if (!Array.isArray(d.revenue_by_month) || d.revenue_by_month.length !== last - first + 1) invalid();
  const revenue = d.revenue_by_month.map((row, index) => {
    if (!object(row) || !month(row.month) || monthIndex(row.month) !== first + index || !decimal(row.revenue_total)) invalid();
    return { month: row.month, revenue_total: row.revenue_total };
  });
  if (revenue.reduce((sum, row) => sum + BigInt(row.revenue_total.replace('.', '')), 0n)
    !== BigInt(d.summary.revenue_total.replace('.', ''))) invalid();
  if (!Array.isArray(d.recent_bookings) || d.recent_bookings.length > 5) invalid();
  const seen = new Set();
  let previous = null;
  const recent = d.recent_bookings.map((row) => {
    if (!object(row) || !id(row.id) || seen.has(row.id) || typeof row.booking_code !== 'string'
      || !statuses.has(row.status)) invalid();
    const created = instant(row.created_at);
    if (created > generated || (previous && (created > previous.created
      || (created === previous.created && BigInt(row.id) >= previous.id)))) invalid();
    seen.add(row.id); previous = { created, id: BigInt(row.id) };
    const related = (value) => {
      if (!object(value) || !id(value.id) || typeof value.name !== 'string') invalid();
      return { id: value.id, name: value.name };
    };
    const checkIn = calendarDate(row.check_in_date), checkOut = calendarDate(row.check_out_date);
    if (checkOut <= checkIn) invalid();
    return { id: row.id, booking_code: row.booking_code, status: row.status, created_at: row.created_at,
      check_in_date: checkIn, check_out_date: checkOut, property: related(row.property), room: related(row.room) };
  });
  return {
    scope: { properties_count: d.scope.properties_count }, snapshot,
    summary: { revenue_total: d.summary.revenue_total },
    period: { from_month: p.from_month, to_month: p.to_month, start_at: p.start_at, end_at_exclusive: p.end_at_exclusive },
    report_timezone: d.report_timezone, generated_at: d.generated_at, revenue_by_month: revenue,
    recent_bookings: recent, warnings: { paid_invoices_missing_paid_at_scope_count: d.warnings.paid_invoices_missing_paid_at_scope_count },
  };
}
