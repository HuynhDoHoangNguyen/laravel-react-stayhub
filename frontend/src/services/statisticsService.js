import api from '../api/axios.js';
import { createStatisticsApi } from '../api/statisticsApi.js';
import { normalizeApiError } from '../api/apiError.js';
import { isStatisticsDecimal, mapStatisticsResponse } from '../mappers/statisticsMapper.js';
import { formatVnd } from '../utils/formatVnd.js';

export function createStatisticsService(statisticsApi) {
  return {
    async getStatistics(params = {}, options = {}) {
      return mapStatisticsResponse(await statisticsApi.getStatistics(params, options));
    },
  };
}
const service = createStatisticsService(createStatisticsApi(api));
export const getStatistics = (params = {}, options = {}) => service.getStatistics(params, options);
export const parseStatisticsError = normalizeApiError;
export function formatStatisticsRevenue(amount) {
  if (!isStatisticsDecimal(amount)) throw new TypeError('Giá trị doanh thu không hợp lệ.');
  return formatVnd(amount);
}

/** Shared by the React hook and deferred tests. A stale transport may ignore abort. */
export function createStatisticsLoader(load = getStatistics) {
  let serial = 0;
  let controller;
  const invalidate = () => { ++serial; controller?.abort(); };
  return {
    invalidate,
    start(params, { captureAuth, isAuthCurrent, success, failure }) {
      invalidate();
      const currentSerial = serial;
      controller = new AbortController();
      const ownController = controller;
      const auth = captureAuth();
      const current = () => serial === currentSerial && !ownController.signal.aborted && isAuthCurrent(auth);
      if (current()) {
        Promise.resolve().then(() => current() ? load(params, { signal: ownController.signal }) : undefined).then((result) => {
          if (current()) success(result, auth);
        }, (error) => {
          if (current()) {
            const parsed = parseStatisticsError(error);
            if (!parsed.isCancelled) failure(parsed, auth);
          }
        });
      }
      return () => { if (serial === currentSerial) invalidate(); };
    },
  };
}
