export const statisticsStatuses = ['PENDING', 'CONFIRMED', 'CHECKED_IN', 'COMPLETED', 'CANCELLED', 'REJECTED'];
export const isStatisticsDecimal = (value) => typeof value === 'string' && /^(0|[1-9]\d*)\.\d{2}$/.test(value);
const object = (value) => value !== null && typeof value === 'object' && !Array.isArray(value);
const count = (value) => Number.isSafeInteger(value) && value >= 0;
const monthIndex = (value) => {
  if (typeof value !== 'string' || !/^(?!0000)\d{4}-(0[1-9]|1[0-2])$/.test(value)) invalid();
  return Number(value.slice(0, 4)) * 12 + Number(value.slice(5)) - 1;
};
function invalid() {
  throw Object.assign(new Error('Dữ liệu thống kê từ máy chủ không hợp lệ. Vui lòng thử lại sau.'), {
    isApiError: true, status: 0, kind: 'contract', code: 'INVALID_ADMIN_STATISTICS_RESPONSE', fields: {}, isCancelled: false,
  });
}
function timestamp(value) {
  if (typeof value !== 'string' || !/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?Z$/.test(value)) invalid();
  const date = new Date(value);
  // ISO roundtrip rejects invalid dates but supports UTC year 0000 at the lower bound.
  if (!Number.isFinite(date.getTime()) || date.toISOString().slice(0, 19) !== value.slice(0, 19)) invalid();
  return value;
}
function statuses(rows, total) {
  if (!Array.isArray(rows) || rows.length !== 6) invalid();
  const result = rows.map((row, i) => {
    if (!object(row) || row.status !== statisticsStatuses[i] || !count(row.count)) invalid();
    return { status: row.status, count: row.count };
  });
  if (result.reduce((sum, row) => sum + BigInt(row.count), 0n) !== BigInt(total)) invalid();
  return result;
}
const cents = (value) => BigInt(value.replace('.', ''));
const localDate = new Intl.DateTimeFormat('en-US', { timeZone: 'Asia/Ho_Chi_Minh',
  era: 'short', year: 'numeric', month: 'numeric', day: 'numeric', hour: 'numeric', minute: 'numeric', second: 'numeric', hourCycle: 'h23' });
function boundary(value, index) {
  const parts = Object.fromEntries(localDate.formatToParts(new Date(value)).map(({ type, value: part }) => [type, part]));
  const fraction = value.match(/\.(\d+)Z$/)?.[1];
  if (parts.era !== 'AD' || Number(parts.year) !== Math.floor(index / 12) || Number(parts.month) !== index % 12 + 1
      || Number(parts.day) !== 1 || Number(parts.hour) !== 0 || Number(parts.minute) !== 0 || Number(parts.second) !== 0
      || (fraction && !/^0+$/.test(fraction))) invalid();
}

export function mapStatisticsResponse(body) {
  const data = body?.data;
  if (!object(body) || !object(data) || !object(data.period) || !object(data.summary) || !object(data.warnings)) invalid();
  const start = monthIndex(data.period.from_month);
  const end = monthIndex(data.period.to_month);
  if (end < start || end - start >= 12 || data.report_timezone !== 'Asia/Ho_Chi_Minh'
      || !count(data.summary.bookings_count) || !isStatisticsDecimal(data.summary.revenue_total)
      || !isStatisticsDecimal(data.summary.commission_revenue_total)
      || !count(data.warnings.paid_invoices_missing_paid_at_global_count)
      || !count(data.warnings.settled_commissions_missing_settled_at_global_count)
      || !Array.isArray(data.monthly) || data.monthly.length !== end - start + 1) invalid();
  const period = { from_month: data.period.from_month, to_month: data.period.to_month,
    start_at: timestamp(data.period.start_at), end_at_exclusive: timestamp(data.period.end_at_exclusive) };
  boundary(period.start_at, start);
  boundary(period.end_at_exclusive, end + 1);
  const summary = { bookings_count: data.summary.bookings_count, revenue_total: data.summary.revenue_total,
    commission_revenue_total: data.summary.commission_revenue_total };
  const totals = statuses(data.booking_status_counts, summary.bookings_count);
  const monthly = data.monthly.map((row, i) => {
    if (!object(row) || monthIndex(row.month) !== start + i || !count(row.bookings_count)
        || !isStatisticsDecimal(row.revenue_total) || !isStatisticsDecimal(row.commission_revenue_total)) invalid();
    return { month: row.month, bookings_count: row.bookings_count, revenue_total: row.revenue_total,
      commission_revenue_total: row.commission_revenue_total,
      booking_status_counts: statuses(row.booking_status_counts, row.bookings_count) };
  });
  if (monthly.reduce((sum, row) => sum + BigInt(row.bookings_count), 0n) !== BigInt(summary.bookings_count)
      || monthly.reduce((sum, row) => sum + cents(row.revenue_total), 0n) !== cents(summary.revenue_total)
      || monthly.reduce((sum, row) => sum + cents(row.commission_revenue_total), 0n) !== cents(summary.commission_revenue_total)) invalid();
  totals.forEach((row, i) => {
    if (monthly.reduce((sum, month) => sum + BigInt(month.booking_status_counts[i].count), 0n) !== BigInt(row.count)) invalid();
  });
  return { period, report_timezone: data.report_timezone, generated_at: timestamp(data.generated_at), summary,
    booking_status_counts: totals, monthly,
    warnings: { paid_invoices_missing_paid_at_global_count: data.warnings.paid_invoices_missing_paid_at_global_count,
      settled_commissions_missing_settled_at_global_count: data.warnings.settled_commissions_missing_settled_at_global_count } };
}
