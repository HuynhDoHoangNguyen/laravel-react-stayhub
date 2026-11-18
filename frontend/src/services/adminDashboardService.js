import api from '../api/axios.js';
import { createAdminDashboardApi } from '../api/adminDashboardApi.js';
import { normalizeApiError } from '../api/apiError.js';
import { isDashboardDecimal, mapAdminDashboardResponse } from '../mappers/adminDashboardMapper.js';

export function createAdminDashboardService(dashboardApi) {
  return {
    async getOverview(options = {}) {
      return mapAdminDashboardResponse(await dashboardApi.getOverview(options));
    },
  };
}
const service = createAdminDashboardService(createAdminDashboardApi(api));
export const getAdminDashboard = (options = {}) => service.getOverview(options);
export const parseDashboardError = normalizeApiError;

const moneyFormat = new Intl.NumberFormat('vi-VN', {
  style: 'currency', currency: 'VND', minimumFractionDigits: 2, maximumFractionDigits: 2,
});
/** Presentation only: preserve all integer digits and the stored decimal cents. */
export function formatDashboardRevenue(amount) {
  if (!isDashboardDecimal(amount)) throw new TypeError('Giá trị doanh thu không hợp lệ.');
  const [whole, fraction] = amount.split('.');
  return moneyFormat.formatToParts(BigInt(whole))
    .map((part) => part.type === 'fraction' ? fraction : part.value).join('');
}

/** Percent widths for presentation only; raw decimal amounts are never converted to Number. */
export function dashboardBarWidths(values) {
  const scaled = values.map((value) => {
    if (Number.isSafeInteger(value) && value >= 0) return BigInt(value) * 100n;
    if (isDashboardDecimal(value)) return BigInt(value.replace('.', ''));
    throw new TypeError('Giá trị biểu đồ không hợp lệ.');
  });
  const maximum = scaled.reduce((max, value) => value > max ? value : max, 0n);
  return scaled.map((value) => maximum === 0n ? 0 : Number(value * 10000n / maximum) / 100);
}
