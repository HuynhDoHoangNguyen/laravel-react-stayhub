import client from '../api/axios.js';
import { createHostDashboardApi } from '../api/hostDashboardApi.js';
import { mapHostDashboardResponse } from '../mappers/hostDashboardMapper.js';
import { normalizeApiError } from '../api/apiError.js';

export function createHostDashboardService(api) {
  return { async getOverview(options = {}) { return mapHostDashboardResponse(await api.getOverview(options)); } };
}
const service = createHostDashboardService(createHostDashboardApi(client));
export const getHostDashboard = (options = {}) => service.getOverview(options);

export function hostDashboardPeriodErrors(from, to) {
  const errors = {};
  if (from === '' && to === '') return errors;
  const valid = (value) => typeof value === 'string' && /^(?!0000)[0-9]{4}-(0[1-9]|1[0-2])$/.test(value)
    && value.length === 7;
  if (!valid(from)) errors.from_month = ['Chọn tháng bắt đầu hợp lệ (năm 0001–9999).'];
  if (!valid(to)) errors.to_month = ['Chọn tháng kết thúc hợp lệ (năm 0001–9999).'];
  if (Object.keys(errors).length === 0) {
    const index = (value) => Number(value.slice(0, 4)) * 12 + Number(value.slice(5));
    if (to < from) errors.to_month = ['Tháng kết thúc phải từ tháng bắt đầu trở đi.'];
    else if (index(to) - index(from) > 11) errors.to_month = ['Kỳ báo cáo không được vượt quá 12 tháng.'];
  }
  return errors;
}

/** Presentation ratios only: convert to Number after bounding the scaled percentage. */
export function hostDashboardBarWidths(values) {
  const amounts = values.map((value) => {
    if (typeof value !== 'string' || !/^(0|[1-9][0-9]*)\.[0-9]{2}$/.test(value)
      || value.trim() !== value) throw new TypeError('Doanh thu biểu đồ không hợp lệ.');
    return BigInt(value.replace('.', ''));
  });
  const max = amounts.reduce((a, b) => a > b ? a : b, 0n);
  return amounts.map((amount) => max === 0n ? 0 : Number(amount * 10000n / max) / 100);
}

/** Request coordination used by the page; late success/error cannot cross request or Auth scope. */
export function createHostDashboardLoader({ getOverview = getHostDashboard, captureAuth, isAuthCurrent, onChange }) {
  let serial = 0;
  let controller;
  function cancel() { ++serial; controller?.abort(); }
  return {
    cancel,
    async load(options = {}) {
      cancel();
      const request = serial;
      controller = new AbortController();
      const auth = captureAuth();
      const current = () => request === serial && isAuthCurrent(auth);
      if (!current()) return;
      onChange({ auth, result: null, error: null, loading: true });
      try {
        const result = await getOverview({ ...options, signal: controller.signal });
        if (current()) onChange({ auth, result, error: null, loading: false });
      } catch (failure) {
        if (!current()) return;
        const error = normalizeApiError(failure);
        onChange({ auth, result: null, error: error.isCancelled ? null : error, loading: false });
      }
    },
  };
}
