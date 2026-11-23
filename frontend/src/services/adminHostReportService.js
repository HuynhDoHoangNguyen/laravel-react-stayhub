import { adminHostReportApi } from '../api/adminHostReportApi.js';

export const reportStatusLabels = {
  SUBMITTED: 'Chờ xử lý',
  SUBSTANTIATED: 'Có căn cứ',
  UNSUBSTANTIATED: 'Chưa đủ căn cứ',
};

const submittedTime = new Intl.DateTimeFormat('vi-VN', {
  dateStyle: 'short', timeStyle: 'short', timeZone: 'Asia/Ho_Chi_Minh',
});
export const formatReportTime = (value) => submittedTime.format(new Date(value));

const summaryFields = ['booking_code', 'property_name', 'host_name', 'reason', 'reason_label', 'submitted_at'];
const detailFields = ['booking_status', 'check_in_date', 'check_out_date', 'customer_name', 'description'];
const isId = (value) => Number.isSafeInteger(value) && value > 0;
const isTime = (value) => typeof value === 'string' && Number.isFinite(Date.parse(value));
const evidenceExtensions = { 'application/pdf': 'pdf', 'image/jpeg': 'jpg', 'image/png': 'png' };

function invalidResponse() {
  return Object.assign(new Error('Dữ liệu báo cáo từ máy chủ không hợp lệ.'), {
    isApiError: true, status: 0, kind: 'contract', fields: {}, isCancelled: false,
  });
}

function mapEvidence(entry, id) {
  if (entry === null) return null;
  const extension = entry && evidenceExtensions[entry.mime];
  if (!extension || entry.filename !== `chung-cu-bao-cao-${id}.${extension}`
    || !isId(entry.size) || entry.size > 2 * 1024 * 1024) throw invalidResponse();
  const { filename, mime, size } = entry;
  return { filename, mime, size };
}

function mapReport(entry, detail = false) {
  if (!isId(entry?.id) || !Object.hasOwn(reportStatusLabels, entry.status)
    || summaryFields.some((key) => typeof entry[key] !== 'string') || !isTime(entry.submitted_at)) {
    throw invalidResponse();
  }
  const keys = ['id', 'status', ...summaryFields];
  if (detail) {
    const reviewFields = ['reviewed_by', 'reviewer_name', 'reviewed_at', 'review_note'];
    if (detailFields.some((key) => typeof entry[key] !== 'string')
      || (entry.status === 'SUBMITTED'
        ? reviewFields.some((key) => entry[key] !== null)
        : !isId(entry.reviewed_by) || typeof entry.reviewer_name !== 'string'
          || !isTime(entry.reviewed_at) || typeof entry.review_note !== 'string' || !entry.review_note.trim())) {
      throw invalidResponse();
    }
    keys.push(...detailFields, ...reviewFields);
  }
  const mapped = Object.fromEntries(keys.map((key) => [key, entry[key]]));
  return detail ? { ...mapped, evidence: mapEvidence(entry.evidence, entry.id) } : mapped;
}

export const adminHostReportService = {
  async list(query, options) {
    const body = await adminHostReportApi.list({ status: query.status, booking_code: query.booking_code.trim(), page: query.page }, options);
    const meta = body?.meta;
    if (!Array.isArray(body?.data) || !isId(meta?.current_page) || !isId(meta?.last_page)
      || !Number.isSafeInteger(meta?.total) || meta.total < 0) throw invalidResponse();
    return {
      data: body.data.map((entry) => mapReport(entry)),
      meta: { current_page: meta.current_page, last_page: meta.last_page, total: meta.total },
    };
  },
  async detail(id, options) {
    return mapReport((await adminHostReportApi.detail(id, options))?.data, true);
  },
  async download(id, evidence, options) {
    const blob = await adminHostReportApi.download(id, options);
    if (!(blob instanceof Blob) || blob.type !== evidence.mime || blob.size !== evidence.size) throw invalidResponse();
    return blob;
  },
  async decide(id, { decision, note }, options) {
    const report = mapReport((await adminHostReportApi.decide(id, { decision, note: note.trim() }, options))?.data, true);
    if (report.status !== decision) throw invalidResponse();
    return report;
  },
};
