import { hostReportApi } from '../api/hostReportApi.js';

function invalidResponse() {
  return Object.assign(new Error('Dữ liệu báo cáo từ máy chủ không hợp lệ.'), {
    isApiError: true, status: 0, kind: 'contract', fields: {}, isCancelled: false,
  });
}

function mapReport(entry) {
  if (!Number.isSafeInteger(entry?.id) || entry.id <= 0 || entry.status !== 'SUBMITTED'
    || typeof entry.submitted_at !== 'string' || !Number.isFinite(Date.parse(entry.submitted_at))) {
    throw invalidResponse();
  }
  const { id, status, submitted_at } = entry;
  return { id, status, submitted_at };
}

export const hostReportService = {
  async current(id, options) {
    const data = (await hostReportApi.current(id, options))?.data;
    if (!data || typeof data.can_submit !== 'boolean' || typeof data.deadline !== 'string'
      || !/^\d{4}-\d{2}-\d{2}$/.test(data.deadline) || !Number.isFinite(Date.parse(data.deadline))
      || !Array.isArray(data.reasons)
      || data.reasons.some((reason) => !reason || typeof reason.value !== 'string' || typeof reason.label !== 'string')
      || (data.can_submit
        ? data.blocked_reason !== null || data.reasons.length === 0
        : !['ALREADY_SUBMITTED', 'BOOKING_STATUS', 'DEADLINE_PASSED'].includes(data.blocked_reason) || data.reasons.length !== 0)
      || ((data.blocked_reason === 'ALREADY_SUBMITTED') !== (data.report !== null))) {
      throw invalidResponse();
    }
    return {
      canSubmit: data.can_submit,
      blockedReason: data.blocked_reason,
      deadline: data.deadline,
      reasons: data.reasons.map(({ value, label }) => ({ value, label })),
      report: data.report === null ? null : mapReport(data.report),
    };
  },
  async submit(id, { reason, description, evidence }, options) {
    const result = await hostReportApi.submit(id, { reason, description: description.trim(), evidence }, options);
    return mapReport(result?.data);
  },
};
