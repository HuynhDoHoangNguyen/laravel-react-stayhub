function invalidResponse() {
  throw Object.assign(new Error('Dữ liệu tổng quan từ máy chủ không hợp lệ. Vui lòng thử lại sau.'), {
    isApiError: true, status: 0, kind: 'contract', code: 'INVALID_ADMIN_DASHBOARD_RESPONSE',
    fields: {}, isCancelled: false,
  });
}

const object = (value) => value !== null && typeof value === 'object' && !Array.isArray(value);
const count = (value) => Number.isSafeInteger(value) && value >= 0;
export const dashboardStatuses = ['PENDING', 'CONFIRMED', 'CHECKED_IN', 'COMPLETED', 'CANCELLED', 'REJECTED'];
export const isDashboardDecimal = (value) => typeof value === 'string' && /^(0|[1-9]\d*)\.\d{2}$/.test(value);

export function mapAdminDashboardResponse(body) {
  const data = body?.data;
  if (!object(body) || !object(data) || !object(data.summary)) invalidResponse();
  const summary = {};
  for (const field of ['users_count', 'hosts_count', 'properties_count', 'rooms_count', 'bookings_count']) {
    if (!count(data.summary[field])) invalidResponse();
    summary[field] = data.summary[field];
  }
  if (summary.hosts_count > summary.users_count || !isDashboardDecimal(data.summary.revenue_total)
      || !count(data.excluded_paid_invoices_count)
      || typeof data.generated_at !== 'string'
      || !/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?Z$/.test(data.generated_at)
      || !Number.isFinite(Date.parse(data.generated_at))) invalidResponse();
  summary.revenue_total = data.summary.revenue_total;
  if (data.report_timezone !== 'Asia/Ho_Chi_Minh' || !Array.isArray(data.booking_status_counts)
      || data.booking_status_counts.length !== dashboardStatuses.length
      || !Array.isArray(data.revenue_by_month) || data.revenue_by_month.length !== 12) invalidResponse();
  const statuses = data.booking_status_counts.map((row, index) => {
    if (!object(row) || row.status !== dashboardStatuses[index] || !count(row.count)) invalidResponse();
    return { status: row.status, count: row.count };
  });
  if (statuses.reduce((total, row) => total + BigInt(row.count), 0n) !== BigInt(summary.bookings_count)) invalidResponse();
  const localParts = new Intl.DateTimeFormat('en-US', {
    timeZone: data.report_timezone, year: 'numeric', month: '2-digit',
  }).formatToParts(new Date(data.generated_at));
  const year = Number(localParts.find((part) => part.type === 'year').value);
  const month = Number(localParts.find((part) => part.type === 'month').value);
  const lastMonthIndex = year * 12 + month - 1;
  const revenue = data.revenue_by_month.map((row, index) => {
    const expectedIndex = lastMonthIndex - 11 + index;
    const expected = `${Math.floor(expectedIndex / 12)}-${String(expectedIndex % 12 + 1).padStart(2, '0')}`;
    if (!object(row) || row.month !== expected || !isDashboardDecimal(row.total_amount)) invalidResponse();
    return { month: row.month, total_amount: row.total_amount };
  });
  return { summary, excluded_paid_invoices_count: data.excluded_paid_invoices_count, generated_at: data.generated_at,
    booking_status_counts: statuses, revenue_by_month: revenue, report_timezone: data.report_timezone };
}
